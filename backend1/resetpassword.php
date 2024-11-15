<?php
// PHP libraries for RabbitMQ and database connection
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';
$config = require DIR . '/config.php';

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

    // Declare the queue to listen to for reset password requests
    $channel->queue_declare('resetpassword_request_queue', false, true, false, false);

    // Declare the queue for MySQL resetpassword data processing
    $channel->queue_declare('mysql_resetpassword_request_queue', false, true, false, false);

    // Script waiting for messages
    echo " [*] Waiting for popup messages from RabbitMQ: resetpassword_request_queue\n";

    // This is the callback function to handle incoming message from resetpassword_request_queue and will forward it to mysql_resetpassword_request_queue
    $callback = function($msg) use ($channel) {
        echo " [x] Received reset password Data: ", $msg->body, "\n";

        // Split the received message into the variables
        $data = explode(",", $msg->body);
        $user_id = $data[0];
        $question1 = $data[1];
        $question2 = $data[2];
        $question3 = $data[3];
        $newpassword = $data[4];

        // Confirmation message
        echo " [x] Recieved and Processing reset password data...";

        // Create a new message to send back to RabbitMQ for MySQL processing
        $processedMessage = json_encode([
            'user_id' => $user_id,
            'question1' => $question1,
            'question2' => $question2,
            'question3' => $question3,
            'newpassword' => $newpassword
        ]);

        // Send processed data to RabbitMQ (mysql_resetpassword_request_queue)
        $message = new AMQPMessage($processedMessage, ['delivery_mode' => 2]); // Make message persistent
        $channel->basic_publish($message, '', 'mysql_resetpassword_request_queue');
        echo " [x] Sent popup data to RabbitMQ: mysql_resetpassword_request_queue\n";

        $msg->ack();
    };

    // Consume messages from the queue: resetpassword_request_queue
    $channel->basic_consume('resetpassword_request_queue', '', false, false, false, false, $callback);

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
