<?php
// PHP libraries for RabbitMQ
require_once __DIR__ . '/../backend1/vendor/autoload.php';
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


   // Declare the queue to listen for MySQL reset password responses
   $channel->queue_declare('mysql_resetpassword_response_queue', false, true, false, false);


   // Declare the queue to send reset password responses to app.py
   $channel->queue_declare('resetpassword_response_queue', false, true, false, false);


   // Script waiting for messages
   echo " [*] Waiting for messages from RabbitMQ: mysql_resetpassword_response_queue\n";


   // Callback function to handle incoming RabbitMQ messages
   $callback = function($msg) use ($channel) {
       echo " [x] Received reset password response: ", $msg->body, "\n";


       // Holds the success or failure data in a variable
       $resetPasswordResponse = $msg->body;


       // Forward the response to resetpassword_response_queue
       $message = new AMQPMessage($resetPasswordResponse, ['delivery_mode' => 2]); // Make message persistent
       $channel->basic_publish($message, '', 'resetpassword_response_queue');
       echo " [x] Sent reset password response to RabbitMQ: resetpassword_response_queue\n";
   };


   // Consume messages from the queue: mysql_resetpassword_response_queue
   $channel->basic_consume('mysql_resetpassword_response_queue', '', false, true, false, false, $callback);


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



