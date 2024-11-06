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


   // Declare the queue to listen for processed popup data
   $channel->queue_declare('mysql_popup_request_queue', false, true, false, false);


   // Declare the queue to send popup responses
   $channel->queue_declare('mysql_popup_response_queue', false, true, false, false);


   // Script waiting for messages on the mysql_popup_request_queue
   echo " [*] Waiting for messages from RabbitMQ: mysql_popup_request_queue\n";


   // Callback function to handle incoming RabbitMQ messages
   $callback = function($msg) use ($channel) {
       echo " [x] Received popup data: ", $msg->body, "\n";


       // Decode the received message (assumed to be JSON format)
       $data = json_decode($msg->body, true);


       // Extracts data fields with default values just in case
       $first_name = $data['first_name'] ?? '';
       $last_name = $data['last_name'] ?? '';
       $country = $data['country'] ?? '';
       $state = $data['state'] ?? '';
       $zip_code = $data['zip_code'] ?? '';
       $job_title = $data['job_title'] ?? '';
       $job_start_month = $data['job_start_month'] ?? '';
       $job_end_month = $data['job_end_month'] ?? '';


       // checks if its null and if it is then sets it to 0
       $job_current = isset($data['job_current']) && $data['job_current'] ? 1 : 0;
       $school_current = isset($data['school_current']) && $data['school_current'] ? 1 : 0;




       $school_name = $data['school_name'] ?? '';
       $school_start_month = $data['school_start_month'] ?? '';
       $school_end_month = $data['school_end_month'] ?? '';
       $security_question_1 = $data['security_question_1'] ?? '';
       $security_question_2 = $data['security_question_2'] ?? '';
       $security_question_3 = $data['security_question_3'] ?? '';


      
       $user_id = $data['user_id'] ?? ''; //retireives the user ID
       try {
           $dbConnection = getDB(); // Get database connection


           // Update new user data into the User table
           $updateStmt = $dbConnection->prepare(
               "UPDATE User SET
                   first_name = ?, last_name = ?, country = ?, state = ?, zip_code = ?,
                   job_title = ?, job_start_month = ?, job_end_month = ?, job_current = ?,
                   school_name = ?, school_start_month = ?, school_end_month = ?,
                   school_current = ?, security_question_1 = ?, security_question_2 = ?, security_question_3 = ?
               WHERE user_id = ?"
           );
      
           $updateStmt->bind_param(
               "sssssssssssssssss",
               $first_name, $last_name, $country, $state, $zip_code,
               $job_title, $job_start_month, $job_end_month, $job_current,
               $school_name, $school_start_month, $school_end_month, $school_current,
               $security_question_1, $security_question_2, $security_question_3, $user_id
           );
      
           if ($updateStmt->execute()) {
               echo " [x] User data updated successfully in MySQL\n";
               $responseMessage = 'success';
           } else {
               echo " [x] Error updating data: " . $updateStmt->error . "\n";
               $responseMessage = 'failure';
           }
           $updateStmt->close();


       } catch (Exception $e) {
           echo " [x] Database Error: " . $e->getMessage() . "\n";
           $responseMessage = 'failure'; // Handle database error
       }


       // Send the result back to the mysql_popup_response_queue
       $message = new AMQPMessage($responseMessage, ['delivery_mode' => 2]); // Make message persistent
       $channel->basic_publish($message, '', 'mysql_popup_response_queue');
       echo " [x] Sent popup result to RabbitMQ: mysql_popup_response_queue\n";
   };


   // Consume messages from the RabbitMQ queue
   $channel->basic_consume('mysql_popup_request_queue', '', false, true, false, false, $callback);


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



