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

    // Declare the queues
    $channel->queue_declare('mysql_fetch_pending_friendrequest_request_queue', false, true, false, false);
    $channel->queue_declare('mysql_fetch_pending_friendrequest_response_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for messages from RabbitMQ: mysql_fetch_pending_friendrequest_request_queue\n";

    // Callback function to handle incoming RabbitMQ messages
    $callback = function($msg) use ($channel) {
        echo " [x] Received message: ", $msg->body, "\n";

        // Decode the received message
        $data = json_decode($msg->body, true);
        $user_id = $data['user_id'];

        // Query the FriendRequests table
        try {
            $dbConnection = getDB(); // Get database connection
            $stmt = $dbConnection->prepare(
                "SELECT sender_email FROM FriendRequests WHERE receiver_id = ? AND status = 'pending'"
            );
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $pendingRequests = [];
            while ($row = $result->fetch_assoc()) {
                $pendingRequests[] = $row['sender_email'];
            }
            
            $stmt->close();

        } catch (Exception $e) {
            echo " [x] Database Error: " . $e->getMessage() . "\n";
            $pendingRequests = [];
        }

        // Prepare response message
        $responseMessage = json_encode($pendingRequests);

        // Send the result back to the mysql_fetch_pending_friendrequest_response_queue
        $message = new AMQPMessage($responseMessage, ['delivery_mode' => 2]);
        $channel->basic_publish($message, '', 'mysql_fetch_pending_friendrequest_response_queue');
        echo " [x] Sent response to RabbitMQ: mysql_fetch_pending_friendrequest_response_queue\n";
    };

    // Consume messages from the RabbitMQ queue
    $channel->basic_consume('mysql_fetch_pending_friendrequest_request_queue', '', false, true, false, false, $callback);

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
