<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/../backend1/vendor/autoload.php';
require_once __DIR__ . '/../backend1/db.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

try {
    // Establish RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('10.147.17.228', 5672, 'guest', 'guest'); // Ensure correct IP
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen for processed registration data
    $channel->queue_declare('mysql_registration_queue', false, true, false, false);

    // Script waiting for messages on the mysql_registration_queue
    echo " [*] Waiting for messages from RabbitMQ: mysql_registration_queue. To exit press CTRL+C\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) {
        echo " [x] Received processed data: ", $msg->body, "\n";

        // Decode the received message (assumed to be JSON format)
        $data = json_decode($msg->body, true);
        $username = $data['username'];
        $email = $data['email'];
        $password = $data['password'];

        // Insert data into the MySQL database
        try {
            $dbConnection = getDB(); // Get database connection
            $stmt = $dbConnection->prepare("INSERT INTO User (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $password);

            if ($stmt->execute()) {
                echo " [x] User data inserted successfully into MySQL\n";
            } else {
                echo " [x] Error inserting data: " . $stmt->error . "\n";
            }
            $stmt->close(); // Close statement
        } catch (Exception $e) {
            echo " [x] Database Error: " . $e->getMessage() . "\n";
        }
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('mysql_registration_queue', '', false, true, false, false, $callback);

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
