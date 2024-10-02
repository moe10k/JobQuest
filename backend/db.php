<?php
// Database connection variables
$db_host = 'localhost';  // Or the IP address of your MySQL server
$db_user = 'root';  // MySQL username
$db_pass = 'your_password';  // MySQL password
$db_name = 'it490_db';   // MySQL database name

// Create a function to get the database connection
function getDB() {
    global $db_host, $db_user, $db_pass, $db_name;
    
    // Create a connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;  // Return the connection for use in other scripts
}
?>
