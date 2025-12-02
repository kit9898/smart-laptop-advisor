<?php
/**
 * Admin Logout Script
 * Destroys admin session and logs the logout activity
 */

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Log the logout activity if admin is logged in
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Log logout activity
    // Log logout activity
    logActivity($conn, $admin_id, 'logout', 'auth', 'Admin logged out');
    
    // Delete session from database
    if (isset($_SESSION['session_token'])) {
        $delete_query = "DELETE FROM admin_sessions WHERE session_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, 's', $_SESSION['session_token']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Destroy session
session_unset();
session_destroy();

// Delete remember me cookie
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, '/');
}

// Close database connection
mysqli_close($conn);

// Redirect to login page
header('Location: login.php?logout=success');
exit();
?>
