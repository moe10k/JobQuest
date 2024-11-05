<?php
// PHP libraries for RabbitMQ
require_once __DIR__ . '/../backend1/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establish RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('10.147.17.65', 5672, 'guest', 'guest');
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen to for MySQL login responses
    $channel->queue_declare('mysql_login_response_queue', false, true, false, false);

    // Declare the queue to send login responses to app.py
    $channel->queue_declare('login_response_queue', false, true, false, false);

    // Declare the queue for listening to login request (backup for backend1)
    $channel->queue_declare('mysql_login_request_queue', false, true, false, false);

    // Declare the queue for forwarding login responses to mysql login request (backup for backend1)
    $channel->queue_declare('login_request_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for messages from RabbitMQ: mysql_login_response_queue\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received login Data: ", $msg->body, "\n";

        // Holds sucessful or failure data in variable
        $loginResponse = $msg->body;

        // Forward the login response to the login_response_queue
        $message = new AMQPMessage($loginResponse, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'login_response_queue');
        echo " [x] Sent login data to RabbitMQ: login_response_queue\n";
    };

    // This is the backup callback2 function to handle incoming message from login_request_queue and forward to mysql_login_request_queue
    $callback2 = function($msg) use ($channel) {
        echo " [x] Backup: Received login Data: ", $msg->body, "\n";

        // Split the received message into email and password
        $data = explode(",", $msg->body);
        $email = $data[0];
        $password = $data[1];

        // Create a new message to send back to RabbitMQ for MySQL login processing
        $processedMessage = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        // Forward the response to the mysql_login_request_queue
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]);
        $channel->basic_publish($message, '', 'mysql_login_request_queue');
        echo " [x] Backup: Sent login data to RabbitMQ: mysql_login_request_queue\n";

        // Acknowledge the message to rabbitmq
        $msg->ack();
    };

    // Consume messages from the queue: mysql_login_response_queue
    $channel->basic_consume('mysql_login_response_queue', '', false, true, false, false, $callback, null, ['x-priority' => ['I', 2]]); //consume messages from this file first - set as higher priority

    // Consume messages from the queue: login_request_queue (backup for backend1)
    $channel->basic_consume('login_request_queue', '', false, false, false, false, $callback2, null, ['x-priority' => ['I', 1]]); //and this has lower priority since its the backup

    // Keep the script running to listen for incoming messages
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    // Close RabbitMQ connection and channel after processing
    $channel->close();
    $rabbitMQConnection->close();

} catch (Exception $e) {
    echo " [x] RabbitMQ Error: " . $e->getMessage() . "\n";
}
?>
