<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$review_id = intval($_POST['review_id'] ?? 0);
$response_text = trim($_POST['response_text'] ?? '');
$admin_id = $_SESSION['admin_id'];

if ($review_id <= 0 || empty($response_text)) {
    echo json_encode(['success' => false, 'message' => 'Review ID and response text are required.']);
    exit;
}

// Debug Logging
file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Request: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Update Database
$sql = "UPDATE product_reviews 
        SET admin_response = ?, 
            admin_response_date = NOW(), 
            admin_id = ? 
        WHERE review_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $response_text, $admin_id, $review_id);

if ($stmt->execute()) {
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Execute Success. Rows affected: " . $stmt->affected_rows . "\n", FILE_APPEND);
    echo json_encode(['success' => true, 'message' => 'Response saved.']);
} else {
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Execute Failed: " . $stmt->error . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
?>
