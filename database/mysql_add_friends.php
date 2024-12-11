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

    // Declare the queue to consume messages
    $channel->queue_declare('mysql_pending_friendrequest_request_queue', false, true, false, false);

    // Declare the queue to send responses
    $channel->queue_declare('mysql_pending_friendrequest_response_queue', false, true, false, false);

    echo " [*] Waiting for messages in mysql_pending_friendrequest_request_queue...\n";

    // Callback function to process the message
    $callback = function ($msg) use ($channel) {
        echo " [x] Received friend request data: ", $msg->body, "\n";

        // Decode the received message
        $data = json_decode($msg->body, true);

        // Extract data fields
        $user_id = $data['user_id'] ?? '';
        $user_email = $data['user_email'] ?? '';
        $friend_email = $data['friend_email'] ?? '';
        $action = $data['action'] ?? '';

        if ($action === 'accepted') {
            try {
                $dbConnection = getDB(); // Get database connection

                // Retrieve friend_id using friend_email
                $friendQuery = "SELECT user_id FROM User WHERE email = ?";
                $friendStmt = $dbConnection->prepare($friendQuery);
                $friendStmt->bind_param("s", $friend_email);
                $friendStmt->execute();
                $friendResult = $friendStmt->get_result();

                if ($friendResult->num_rows === 1) {
                    $friendData = $friendResult->fetch_assoc();
                    $friend_id = $friendData['user_id'];

                    // Update FriendRequests table
                    $updateRequestQuery = "UPDATE FriendRequests SET status = 'accepted' WHERE sender_id = ? AND receiver_email = ?";
                    $updateRequestStmt = $dbConnection->prepare($updateRequestQuery);
                    $updateRequestStmt->bind_param("is", $friend_id, $user_email);
                    if ($updateRequestStmt->execute()) {

                        // Insert bidirectional friendship into Friends table
                        $insertFriendQuery = "INSERT INTO Friends (user_id, user_email, friend_id, friend_email) VALUES (?, ?, ?, ?), (?, ?, ?, ?)";
                        $insertFriendStmt = $dbConnection->prepare($insertFriendQuery);
                        $insertFriendStmt->bind_param("isisisis", $user_id, $user_email, $friend_id, $friend_email, $friend_id, $friend_email, $user_id, $user_email);

                        if ($insertFriendStmt->execute()) {
                            echo " [x] Friendship created successfully between $user_email and $friend_email.\n";
                            $responseMessage = 'success';
                        } else {
                            echo " [x] Error inserting into Friends table: " . $insertFriendStmt->error . "\n";
                            $responseMessage = 'failure';
                        }
                        $insertFriendStmt->close();
                    } else {
                        echo " [x] Error updating FriendRequests table: " . $updateRequestStmt->error . "\n";
                        $responseMessage = 'failure';
                    }
                    $updateRequestStmt->close();
                } else {
                    echo " [x] Friend with email: $friend_email not found.\n";
                    $responseMessage = 'failure';
                }
                $friendStmt->close();
            } catch (Exception $e) {
                echo " [x] Database Error: " . $e->getMessage() . "\n";
                $responseMessage = 'failure';
            }
        } else {
            echo " [x] Unsupported action: $action.\n";
            $responseMessage = 'failure';
        }

        // Send the result back to the response queue
        $message = new AMQPMessage($responseMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_pending_friendrequest_response_queue');
        echo " [x] Sent result to RabbitMQ: mysql_pending_friendrequest_response_queue\n";

        $msg->ack();
    };

    // Consume messages from the queue
    $channel->basic_consume('mysql_pending_friendrequest_request_queue', '', false, false, false, false, $callback);

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
