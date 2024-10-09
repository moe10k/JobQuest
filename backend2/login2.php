<?php
// PHP libraries for RabbitMQ
require_once __DIR__ . '/../backend1/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establish RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('10.147.17.228', 5672, 'guest', 'guest');
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen to for MySQL login responses
    $channel->queue_declare('mysql_login_response_queue', false, true, false, false);

    // Declare the queue to send login responses to app.py
    $channel->queue_declare('login_response_queue', false, true, false, false);

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

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('mysql_login_response_queue', '', false, true, false, false, $callback);

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
