<?php
// Database Connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'laptop_advisor_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start the session if it's not already started. This is crucial for all other files.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>