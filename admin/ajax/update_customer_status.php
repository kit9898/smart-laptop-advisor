<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($user_id <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

// Validate status
$allowed_statuses = ['pending', 'active', 'inactive', 'suspended'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status value']);
    exit();
}

// Update status
$stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
$stmt->bind_param("si", $status, $user_id);

if ($stmt->execute()) {
    logActivity($conn, $_SESSION['admin_id'], 'update', 'users', "Updated status for user ID: $user_id to $status", 'user', $user_id);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed']);
}

$stmt->close();
$conn->close();
?>
