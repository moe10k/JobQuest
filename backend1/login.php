<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';
$config = require __DIR__  . '/config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establish RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection(
        $config['rabbitmq']['host'],
        $config['rabbitmq']['port'],
        $config['rabbitmq']['username'],
        $config['rabbitmq']['password']
    );
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen to for login requests
    $channel->queue_declare('login_request_queue', false, true, false, false);

    // Declare the queue for MySQL login data processing
    $channel->queue_declare('mysql_login_request_queue', false, true, false, false);

    // Declare the queue for listening to MySQL login response (backup for backend2)
    $channel->queue_declare('mysql_login_response_queue', false, true, false, false);

    // Declare the queue for forwarding login responses to app.py (backup for backend2)
    $channel->queue_declare('login_response_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for login messages from RabbitMQ: login_request_queue\n";

    // This is the callback function to handle incoming message from login_request_queue and will forward it to mysql_login_request_queue
    $callback = function($msg) use ($channel) {
        echo " [x] Received Login Data: ", $msg->body, "\n";

        // Split the received message into email and password
        $data = explode(",", $msg->body);
        $email = $data[0];
        $password = $data[1];


        // tracks all errors
        $errors = [];

        // Validate email format using built in php function
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        // Validate password - might change this later
        if (empty($password)) {
            $errors[] = "Password cannot be empty.";
        }

        // if any errors than the scripts ends early
        if (!empty($errors)) {
            echo " [x] Validation failed: " . implode(", ", $errors) . "\n";
            return;
        }


        // Confirmation message
        echo " [x] Processing Login Data for email: $email, password: $password\n";

        // Create a new message to send back to RabbitMQ for MySQL login processing
        $processedMessage = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        // Send processed data to RabbitMQ (mysql_login_request_queue)
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_login_request_queue');
        echo " [x] Sent login data to RabbitMQ: mysql_login_request_queue\n";

        $msg->ack();
    };

    // This is the backup callback2 function to handle incoming message from mysql_login_response_queue and forward to login_response_queue
    $callback2 = function($msg) use ($channel) {
        echo " [x] Backup: Received login Data: ", $msg->body, "\n";

        $loginResponse = $msg->body;

        // Forward the response to the login_response_queue
        $message = new AMQPMessage($loginResponse, ['delivery_mode' => 2]);
        $channel->basic_publish($message, '', 'login_response_queue');
        echo " [x] Backup: Sent login data to RabbitMQ: login_response_queue\n";

        // Acknowledge the message to rabbitmq
        $msg->ack();
    };

    // Consume messages from the queue: login_request_queue
    $channel->basic_consume('login_request_queue', '', false, false, false, false, $callback, null, ['x-priority' => ['I', 2]]); //consume messages from this file first - set as higher priority

    // Consume messages from the queue: mysql_login_response_queue (backup for backend2)
    $channel->basic_consume('mysql_login_response_queue', '', false, false, false, false, $callback2, null, ['x-priority' => ['I', 1]]); //and this has lower priortiy since its the backup

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
