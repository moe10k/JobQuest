<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/../backend1/vendor/autoload.php';
require_once __DIR__ . '/db.php';
$config = require __DIR__ . '/../messaging/config.php';

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

    // Declare the queue to listen for friend request requests
    $channel->queue_declare('mysql_friendrequest_request_queue', false, true, false, false);

    // Declare the queue to send friend request responses
    $channel->queue_declare('mysql_friendrequest_response_queue', false, true, false, false);

    echo " [*] Waiting for messages in mysql_friendrequest_request_queue...\n";

    // Callback function to process the friend request message
    $callback = function ($msg) use ($channel) {
        $responseMessage = 'failure'; // Default response message

        try {
            echo " [x] Received friendrequest data: ", $msg->body, "\n";

            // Decode the received message
            $data = json_decode($msg->body, true);

            // Extract data fields
            $user_id1 = $data['user_id'] ?? '';
            $email_user2 = $data['email'] ?? '';

            $dbConnection = getDB(); // Get database connection

            // Retrieve email_user1 using user_id1
            $emailQuery = "SELECT email FROM User WHERE user_id = ?";
            $emailStmt = $dbConnection->prepare($emailQuery);
            $emailStmt->bind_param("i", $user_id1);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();

            if ($emailResult->num_rows === 1) {
                $emailData = $emailResult->fetch_assoc();
                $email_user1 = $emailData['email'];
                echo " [DEBUG] Email of user_id1 retrieved successfully: $email_user1.\n";

                // Retrieve user_id2 using email_user2
                $user2Query = "SELECT user_id FROM User WHERE email = ?";
                $user2Stmt = $dbConnection->prepare($user2Query);
                $user2Stmt->bind_param("s", $email_user2);
                $user2Stmt->execute();
                $user2Result = $user2Stmt->get_result();

                if ($user2Result->num_rows === 1) {
                    $user2Data = $user2Result->fetch_assoc();
                    $user_id2 = $user2Data['user_id'];
                    echo " [DEBUG] User2 ID retrieved successfully: $user_id2.\n";

                    // Insert into FriendRequests table
                    $insertRequestQuery = "INSERT INTO FriendRequests (sender_id, sender_email, receiver_id, receiver_email, status) VALUES (?, ?, ?, ?, ?)";
                    $insertRequestStmt = $dbConnection->prepare($insertRequestQuery);
                    $status = 'pending';
                    $insertRequestStmt->bind_param("isiss", $user_id1, $email_user1, $user_id2, $email_user2, $status);

                    if ($insertRequestStmt->execute()) {
                        echo " [x] Friend request sent successfully from user $user_id1 to user $user_id2.\n";
                        $responseMessage = 'success';
                    } else {
                        echo " [x] Error inserting friend request: " . $insertRequestStmt->error . "\n";
                    }
                    $insertRequestStmt->close();
                } else {
                    echo " [x] User with email: $email_user2 not found.\n";
                }
                $user2Stmt->close();
            } else {
                echo " [x] User with user_id: $user_id1 not found.\n";
            }

            $emailStmt->close();
        } catch (Exception $e) {
            echo " [x] Error during message processing: " . $e->getMessage() . "\n";
        } finally {
            if (isset($dbConnection)) {
                $dbConnection->close();
            }
        }

        // Acknowledge the message
        $channel->basic_ack($msg->delivery_info['delivery_tag']);
        echo " [x] Acknowledged message.\n";

        // Send the result back to the mysql_friendrequest_response_queue
        $message = new AMQPMessage($responseMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_friendrequest_response_queue');
        echo " [x] Sent friendrequest result to RabbitMQ: mysql_friendrequest_response_queue\n";
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('mysql_friendrequest_request_queue', '', false, false, false, false, $callback);

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
