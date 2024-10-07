<?php
// PHP libraries for RabbitMQ and also to connect to database
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

try {
    // Establishing RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen to (same queue name in Flask)
    $channel->queue_declare('registration_queue', false, true, false, false);

    //script waiting for messages on the backend
    echo " [*] Waiting for messages. To exit press CTRL+C\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) {
        echo " [x] Received: ", $msg->body, "\n";

        // Split the received message into username, email, and password
        $data = explode(",", $msg->body);
        $username = $data[0];
        $email = $data[1];
        $password = $data[2];

        //Confirmation message
        echo " [x] Processing registration for user: $username, email: $email\n";

        // Insert data into the MySQL database
        try {
            $dbConnection = getDB(); // Get database connection
            $stmt = $dbConnection->prepare("INSERT INTO User (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $password);

            if ($stmt->execute()) {
                echo " [x] User data inserted successfully\n";
            } else {
                echo " [x] Error inserting data: " . $stmt->error . "\n";
            }
            $stmt->close(); // Close statement
        } catch (Exception $e) {
            echo " [x] Database Error: " . $e->getMessage() . "\n";
        }
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