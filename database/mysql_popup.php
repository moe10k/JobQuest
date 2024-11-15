<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/../backend1/vendor/autoload.php';
require_once __DIR__ . '/../backend1/db.php';
$config = require DIR . '/../backend1/config.php';

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
        $popup_enabled = $data['popup_enabled'] ?? 0; // Retrieves the popup_enabled status
        $user_id = $data['user_id'] ?? ''; //retireives the user ID

       try {
            $dbConnection = getDB(); // Get database connection

            // Insert into User_info table
            $userInfoStmt = $dbConnection->prepare(
                "INSERT INTO User_info (user_id, first_name, last_name, country, state, zip_code, school_name, school_start_month, school_end_month, school_current)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $userInfoStmt->bind_param(
                "issssssssi",
                $user_id, $first_name, $last_name, $country, $state, $zip_code, $school_name, $school_start_month, $school_end_month, $school_current
            );
            $userInfoStmt->execute();
            $userInfoStmt->close();

            // Insert into Experience table
            $experienceStmt = $dbConnection->prepare(
                "INSERT INTO Experience (user_id, job_title, job_start_month, job_end_month, job_current, bullet_points)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $bullet_points = '';
            $experienceStmt->bind_param(
                "isssis",
                $user_id, $job_title, $job_start_month, $job_end_month, $job_current, $bullet_points
            );
            $experienceStmt->execute();
            $experienceStmt->close();

            // Insert into Security_Questions table
            $securityStmt = $dbConnection->prepare(
                "INSERT INTO Security_Questions (user_id, security_question_1, security_question_2, security_question_3)
                VALUES (?, ?, ?, ?)"
            );
            $securityStmt->bind_param(
                "isss",
                $user_id, $security_question_1, $security_question_2, $security_question_3
            );
            $securityStmt->execute();
            $securityStmt->close();

            // Update popup_enabled in User table
            $updatePopupStmt = $dbConnection->prepare(
                "UPDATE User SET popup_enabled = ? WHERE user_id = ?"
            );
            $updatePopupStmt->bind_param(
                "ii",
                $popup_enabled, $user_id
            );
            $updatePopupStmt->execute();
            $updatePopupStmt->close();

            echo " [x] Data inserted successfully in MySQL\n";
            $responseMessage = 'success';

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