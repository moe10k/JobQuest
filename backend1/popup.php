<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/vendor/autoload.php';
$config = require __DIR__  . '/../messaging/config.php';

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

    // Declare the queue to listen to for popup requests
    $channel->queue_declare('popup_request_queue', false, true, false, false);

    // Declare the queue for MySQL popup data processing
    $channel->queue_declare('mysql_popup_request_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for popup messages from RabbitMQ: popup_request_queue\n";

    // This is the callback function to handle incoming message from popup_request_queue and will forward it to mysql_popup_request_queue
    $callback = function($msg) use ($channel) {
        echo " [x] Received popup Data: ", $msg->body, "\n";

        // Split the received message into the variables
        $data = explode(",", $msg->body);
        $first_name = $data[0];
        $last_name = $data[1];
        $country = $data[2];
        $state = $data[3];
        $zip_code = $data[4];
        $job_title = $data[5];
        $job_start_month = $data[6];
        $job_end_month = $data[7];
        $job_current = $data[8];
        $school_name = $data[9];
        $school_start_month = $data[10];
        $school_end_month = $data[11];
        $school_current = $data[12];
        $security_question_1 = $data[13];
        $security_question_2 = $data[14];
        $security_question_3 = $data[15];
        $popup_enabled = $data[16];
        $user_id = $data[17];

        // Confirmation message
        echo " [x] Recieved and Processing popup data...";

        // Create a new message to send back to RabbitMQ for MySQL login processing
        $processedMessage = json_encode([
            'first_name' => $first_name,
            'last_name' => $last_name,
            'country' => $country,
            'state' => $state,
            'zip_code' => $zip_code,
            'job_title' => $job_title,
            'job_start_month' => $job_start_month,
            'job_end_month' => $job_end_month,
            'job_current' => $job_current,
            'school_name' => $school_name,
            'school_start_month' => $school_start_month,
            'school_end_month' => $school_end_month,
            'school_current' => $school_current,
            'security_question_1' => $security_question_1,
            'security_question_2' => $security_question_2,
            'security_question_3' => $security_question_3,
            'popup_enabled' => $popup_enabled,
            'user_id' => $user_id
        ]);

        // Send processed data to RabbitMQ (mysql_popup_request_queue)
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_popup_request_queue');
        echo " [x] Sent popup data to RabbitMQ: mysql_popup_request_queue\n";

        $msg->ack();
    };

    // Consume messages from the queue: popup_request_queue
    $channel->basic_consume('popup_request_queue', '', false, false, false, false, $callback);

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
