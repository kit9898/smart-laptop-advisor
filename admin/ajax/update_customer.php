<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($user_id <= 0 || empty($full_name) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit();
}

// Check if email already exists for another user
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$check_stmt->bind_param("si", $email, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Email already in use by another user']);
    exit();
}
$check_stmt->close();

// Update user
$update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, default_shipping_phone = ?, status = ? WHERE user_id = ?");
$update_stmt->bind_param("ssssi", $full_name, $email, $phone, $status, $user_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $conn->error]);
}

$update_stmt->close();
$conn->close();
?>
