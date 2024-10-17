<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/../backend1/vendor/autoload.php';
require_once __DIR__ . '/../backend1/db.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    // Establish RabbitMQ connection
    $rabbitMQConnection = new AMQPStreamConnection('10.147.17.228', 5672, 'guest', 'guest'); // Ensure correct IP
    $channel = $rabbitMQConnection->channel();

    // Declare the queue to listen for login verification
    $channel->queue_declare('mysql_login_request_queue', false, true, false, false);

    // Declare the queue to send login responses
    $channel->queue_declare('mysql_login_response_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for messages from RabbitMQ: mysql_login_request_queue\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received login data: ", $msg->body, "\n";

        // Decode the received message
        $data = json_decode($msg->body, true);
        $email = $data['email'];
        $password = $data['password']; //were retrieving the plaintext password

        // Check the database for the user credentials
        try {
            $dbConnection = getDB(); // Get database connection
            $stmt = $dbConnection->prepare("SELECT password, username FROM User WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            // Check if the user exists and verify the password
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $hashedPassword = $user['password']; // Retrieve the hashed password from the database
                $username = $user['username']; // also retrieve the username from the database

                // Verify the password using password_verify() and send result to rabbitmq
                if (password_verify($password, $hashedPassword)) {
                    $responseMessage = json_encode([
                        'status' => 'success',
                        'username' => $username
                    ]);
                    echo " [x] Login successful for email: $email\n";
                } else {
                    $responseMessage = json_encode(['status' => 'failure']);
                    echo " [x] Login failed for email: $email - Invalid password\n";
                }
            } else {
                $responseMessage = json_encode(['status' => 'failure']);
                echo " [x] Login failed for email: $email - No such user found\n";
            }

            // Close the statement
            $stmt->close();

        } catch (Exception $e) {
            echo " [x] Database Error: " . $e->getMessage() . "\n";
        }

        // Send the result back to the mysql_login_response_queue
        $message = new AMQPMessage($responseMessage, ['delivery_mode' => 2]);
        $channel->basic_publish($message, '', 'mysql_login_response_queue');
        echo " [x] Sent login result to RabbitMQ: mysql_login_response_queue\n";
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('mysql_login_request_queue', '', false, true, false, false, $callback);

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
