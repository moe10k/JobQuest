<?php
require_once __DIR__ . '/vendor/autoload.php';  //php libraries for interacting with rabbitmq


require_once __DIR__ . '/db.php'; //use to connect to database



use PhpAmqpLib\Connection\AMQPStreamConnection;

// RabbitMQ connection details
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Declare the queue (same name as in Flask)
$channel->queue_declare('registration_queue', false, true, false, false); //same queue name from flask

echo " [*] Waiting for messages. To exit press CTRL+C\n"; //script waiting for messages

// Callback function to process messages
$callback = function($msg) use ($conn) {
    echo " [x] Received ", $msg->body, "\n"; //confirmation that script recieved message from rabbitmq
    
    // Split the received message into parts (username, email, password)
    $data = explode(",", $msg->body);
    $username = $data[0];
    $email = $data[1];
    $password = $data[2];
    
    // confirmation that data will be stored to database
    echo " [x] Processing registration for user: $username, email: $email\n";

    // Insert data into the MySQL database
    $stmt = $conn->prepare("INSERT INTO User (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        echo " [x] User data inserted successfully\n";
    } else {
        echo " [x] Error inserting data: " . $stmt->error . "\n";
    }

    // Closed statement
    $stmt->close();
    };

// Consume messages from the queue
$channel->basic_consume('registration_queue', '', false, true, false, false, $callback);

// Keep the script running to listen for incoming messages
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
