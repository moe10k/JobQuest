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

   // Declare the queue to listen for password reset requests
   $channel->queue_declare('mysql_resetpassword_request_queue', false, true, false, false);

   // Declare the queue to send password reset responses
   $channel->queue_declare('mysql_resetpassword_response_queue', false, true, false, false);

   echo " [*] Waiting for messages in mysql_resetpassword_request_queue...\n";

   // Callback function to process the password reset message
   $callback = function ($msg) use ($channel) {
       echo " [x] Received reset password data: ", $msg->body, "\n";

       // Decode the received message
       $data = json_decode($msg->body, true);

       // Extract data fields
       $user_id = $data['user_id'] ?? '';
       $question1 = $data['question1'] ?? '';
       $question2 = $data['question2'] ?? '';
       $question3 = $data['question3'] ?? '';
       $new_password = $data['newpassword'] ?? '';

       // Hash the password before sending it further
       $hashedpassword = password_hash($new_password, PASSWORD_BCRYPT);
       echo " [DEBUG] Hashed password: $hashedpassword\n";

       try {
        $dbConnection = getDB(); // Get database connection

        // Check if security questions match in the Security_Questions table
        $query = "SELECT * FROM Security_Questions WHERE user_id = ? AND security_question_1 = ? AND security_question_2 = ? AND security_question_3 = ?";
        $stmt = $dbConnection->prepare($query);
        $stmt->bind_param("isss", $user_id, $question1, $question2, $question3);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            echo " [DEBUG] Preparing to update password for user_id: $user_id with hashed password: $hashedpassword\n";

            // If match found, update the password in the User table
            $updateQuery = "UPDATE User SET password = ? WHERE user_id = ?";
            $updateStmt = $dbConnection->prepare($updateQuery);
            $updateStmt->bind_param("si", $hashedpassword, $user_id);

            if ($updateStmt->execute()) {
                echo " [x] Password updated successfully for user_id: {$user_id}\n";
                $responseMessage = 'success';
            } else {
                echo " [x] Error updating password: " . $updateStmt->error . "\n";
                $responseMessage = 'failure';
            }
            $updateStmt->close();
        } else {
            echo " [x] Security answers do not match for user_id: {$user_id}\n";
            $responseMessage = 'failure';
        }
        
        $stmt->close();

        } catch (Exception $e) {
            echo " [x] Database Error: " . $e->getMessage() . "\n";
            $responseMessage = 'failure'; // Handle database error
        }

       // Send the result back to the mysql_resetpassword_response_queue
       $message = new AMQPMessage($responseMessage, ['delivery_mode' => 2]); // Make message persistent
       $channel->basic_publish($message, '', 'mysql_resetpassword_response_queue');
       echo " [x] Sent password reset result to RabbitMQ: mysql_resetpassword_response_queue\n";

       // Acknowledge the message
       $msg->ack();
   };

   // Consume messages from the RabbitMQ queue
   $channel->basic_consume('mysql_resetpassword_request_queue', '', false, false, false, false, $callback);

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