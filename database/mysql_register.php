<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/../backend1/vendor/autoload.php';
require_once __DIR__ . '/../backend1/db.php';
$config = require __DIR__ . '/../backend1/config.php';

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

    // Declare the queue to listen for processed registration data
    $channel->queue_declare('mysql_registration_request_queue', false, true, false, false);

    // Declare the queue to send registration responses
    $channel->queue_declare('mysql_registration_response_queue', false, true, false, false);

    // Script waiting for messages on the mysql_registration_request_queue
    echo " [*] Waiting for messages from RabbitMQ: mysql_registration_request_queue\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received processed data: ", $msg->body, "\n";

        // Decode the received message (assumed to be JSON format)
        $data = json_decode($msg->body, true);
        $username = $data['username'];
        $email = $data['email'];
        $password = $data['password'];

        try {
            $dbConnection = getDB(); // Get database connection

            // Check if the email already exists in the User table
            $checkStmt = $dbConnection->prepare("SELECT email FROM User WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                // Email is already registered
                echo " [x] Email already exists in the database\n";
                $responseMessage = 'failure'; // Email exists, send failure message
            } else {
                // Email does not exist, insert new user data
                $insertStmt = $dbConnection->prepare("INSERT INTO User (username, email, password) VALUES (?, ?, ?)");
                $insertStmt->bind_param("sss", $username, $email, $password);

                if ($insertStmt->execute()) {
                    echo " [x] User data inserted successfully into MySQL\n";
                    $responseMessage = 'success';
                } else {
                    echo " [x] Error inserting data: " . $insertStmt->error . "\n";
                    $responseMessage = 'failure'; // Handle insert error
                }
                $insertStmt->close(); // Close insert statement
            }

            $checkStmt->close(); // Close check statement
        } catch (Exception $e) {
            echo " [x] Database Error: " . $e->getMessage() . "\n";
            $responseMessage = 'failure'; // Handle database error
        }

        // Send the result back to the mysql_registration_response_queue
        $message = new AMQPMessage($responseMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_registration_response_queue');
        echo " [x] Sent registration result to RabbitMQ: mysql_registration_response_queue\n";
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('mysql_registration_request_queue', '', false, true, false, false, $callback);

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
