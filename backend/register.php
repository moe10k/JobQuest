<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establishing RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('10.147.17.65', 5672, 'guest', 'guest');
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen to (same queue name in Flask)
    $channel->queue_declare('registration_queue', false, true, false, false);

    // Declare the queue for MySQL data processing
    $channel->queue_declare('mysql_queue', false, true, false, false);

    // Script waiting for messages on the backend
    echo " [*] Waiting for messages from RabbitMQ\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received Registration Data: ", $msg->body, "\n";

        // Split the received message into username, email, and password
        $data = explode(",", $msg->body);
        $username = $data[0];
        $email = $data[1];
        $password = $data[2];

        // Confirmation message
        echo " [x] Processing Registration Data for user: $username, email: $email, password: $password\n";

        // Create a new message to send back to RabbitMQ for MySQL to process
        $processedMessage = json_encode([
            'username' => $username,
            'email' => $email,
            'password' => $password
        ]);

        // Send processed data to RabbitMQ (mysql_queue)
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_queue');
        echo " [x] Sent processed data to RabbitMQ: mysql_queue\n";
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('registration_queue', '', false, true, false, false, $callback);

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
