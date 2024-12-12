<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/vendor/autoload.php';
$config = require __DIR__  . '/../messaging/config.php';

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

    // Declare the queue to listen to for popup requests
    $channel->queue_declare('pending_friendrequest_request_queue', false, true, false, false);

    // Declare the queue for MySQL popup data processing
    $channel->queue_declare('mysql_pending_friendrequest_request_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for friendrequest messages from RabbitMQ: pending_friendrequest_request_queue\n";

    // This is the callback function to handle incoming message from popup_request_queue and will forward it to mysql_popup_request_queue
    $callback = function($msg) use ($channel) {
        echo " [x] Received pending friend Data: ", $msg->body, "\n";

        // Split the received message into the variables
        $data = explode(",", $msg->body);
        $user_id = $data[0];
        $user_email = $data[1];
        $friend_email = $data[2];
        $action = $data[3];

        // Confirmation message
        echo " [x] Recieved and Processing pending friend data...\n";

        // Create a new message to send back to RabbitMQ for MySQL login processing
        $processedMessage = json_encode([
            'user_id' => $user_id,
            'user_email' => $user_email,
            'friend_email' => $friend_email,
            'action' => $action
        ]);

        // Send processed data to RabbitMQ (mysql_popup_request_queue)
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_pending_friendrequest_request_queue');
        echo " [x] Sent pending friend data to RabbitMQ: mysql_pending_friendrequest_request_queue\n";

        $msg->ack();
    };

    // Consume messages from the queue: popup_request_queue
    $channel->basic_consume('pending_friendrequest_request_queue', '', false, false, false, false, $callback);

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