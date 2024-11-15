<?php
// PHP libraries for RabbitMQ
require_once __DIR__ . '/../backend1/vendor/autoload.php';
$config = require __DIR__  . '/../backend1/config.php';

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

    // Declare the queue to listen for MySQL registration responses
    $channel->queue_declare('mysql_registration_response_queue', false, true, false, false);

    // Declare the queue to send registration responses to app.py
    $channel->queue_declare('registration_response_queue', false, true, false, false);

    // Declare the queue to listen to (same queue name in Flask) (backup)
    $channel->queue_declare('registration_request_queue', false, true, false, false);

    // Declare the queue for MySQL data processing (backup)
    $channel->queue_declare('mysql_registration_request_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for messages from RabbitMQ: mysql_registration_response_queue\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received registration data: ", $msg->body, "\n";

        // Holds successful or failure data in variable
        $registrationResponse = $msg->body;

        // Forward the registration response to the registration_response_queue
        $message = new AMQPMessage($registrationResponse, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'registration_response_queue');
        echo " [x] Sent registration data to RabbitMQ: registration_response_queue\n";
    };

    // backup for backend1
    $callback2 = function($msg) use ($channel) {
        echo " [x] Backup: Received Registration Data: ", $msg->body, "\n";

        // Split the received message into username, email, and password
        $data = explode(",", $msg->body);
        $username = $data[0];
        $email = $data[1];
        $password = $data[2];

        // Hash the password before sending it further
        $hashedpassword = password_hash($password, PASSWORD_BCRYPT);

        // Confirmation message
        echo " [x] Backup: Processing Registration Data for user: $username, email: $email, password: $hashedpassword\n";

        // Create a new message to send back to RabbitMQ for MySQL to process
        $processedMessage = json_encode([
            'username' => $username,
            'email' => $email,
            'password' => $hashedpassword
        ]);

        // Send processed data to RabbitMQ (mysql_registration_request_queue)
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_registration_request_queue');
        echo " [x] Backup: Sent processed data to RabbitMQ: mysql_registration_request_queue\n";
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('mysql_registration_response_queue', '', false, true, false, false, $callback, null, ['x-priority' => ['I', 2]]); //first priority

    //backup
    $channel->basic_consume('registration_request_queue', '', false, true, false, false, $callback2, null, ['x-priority' => ['I', 1]]); //lower priority

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
