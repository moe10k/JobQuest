<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establish RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('10.147.17.65', 5672, 'guest', 'guest');
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen to for login requests
    $channel->queue_declare('login_request_queue', false, true, false, false);

    // Declare the queue for MySQL login data processing
    $channel->queue_declare('mysql_login_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for login messages from RabbitMQ\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received Login Data: ", $msg->body, "\n";

        // Split the received message into email and password
        $data = explode(",", $msg->body);
        $email = $data[0];
        $password = $data[1];

        // Confirmation message
        echo " [x] Processing Login Data for email: $email, password: $password\n";

        // Create a new message to send back to RabbitMQ for MySQL login processing
        $processedMessage = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        // Send processed data to RabbitMQ (mysql_login_queue)
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_login_queue');
        echo " [x] Sent processed login data to RabbitMQ: mysql_login_queue\n";
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('login_request_queue', '', false, true, false, false, $callback);

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
