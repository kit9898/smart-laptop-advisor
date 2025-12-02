<?php
// This file's ONLY job is to check for a session and redirect if it doesn't exist.
// To check the session, it must be started. We do this by including db_connect.php.
require_once 'db_connect.php';

// Check for the 'user_id' we set during login. This is more robust than just 'loggedin'.
if (!isset($_SESSION['user_id'])) {
    // If the user is not logged in, redirect to the login page and stop the script.
    header("Location: login.php");
    exit();
}
?>