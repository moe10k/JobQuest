<?php
// PHP libraries for RabbitMQ and also to connect to database
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Start session for user login tracking
session_start();


try {
    // Establishing RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('10.147.17.228', 5672, 'guest', 'guest');
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen to (same queue name in Flask)
    $channel->queue_declare('login_request_queue', false, true, false, false);
    // Declare the response queue (for sending login result back to frontend)
    $channel->queue_declare('login_response_queue', false, true, false, false);

    //script waiting for messages on the backend
    echo " [*] Waiting for messages from rabbitmq. To exit press CTRL+C\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received from rabbitmq: ", $msg->body, "\n";

        // Split the received message into email and password
        $data = explode(",", $msg->body);
        $email = $data[0];
        $password = $data[1];

        //Confirmation message
        echo " [x] Processing login for email: $email\n";

        // check data into the MySQL database
        try {
            $dbConnection = getDB(); // Get database connection
            $stmt = $dbConnection->prepare("SELECT * FROM User WHERE email = ? AND password = ?");
            $stmt->bind_param("ss", $email, $password);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo " [x] User $email authenticated successfully\n";
                    $response = 'success';
                } else {
                    echo " [x] Invalid credentials for user: $email\n";
                    $response = 'failure';
                }
            } else {
                echo " [x] Error executing query: " . $stmt->error . "\n";
                $response = 'failure';
            }
            $stmt->close(); // Close statement

            // Send response back to the frontend via RabbitMQ
            $msg_response = new AMQPMessage($response);
            $channel->basic_publish($msg_response, '', 'login_response_queue');
            echo " [x] Sent login response: $response\n";

        } catch (Exception $e) {
            echo " [x] Database Error: " . $e->getMessage() . "\n";
        }
    };

    // Consume messages from the RabbitMQ login queue
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