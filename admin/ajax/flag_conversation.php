<?php
// ============================================
// Flag Conversation Handler
// Module D: Smart Laptop Advisor Admin
// ============================================

header('Content-Type: application/json');
require_once '../../LaptopAdvisor/includes/db_connect.php';
require_once '../../LaptopAdvisor/includes/config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['conversation_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$conversationId = intval($input['conversation_id']);
$action = $input['action']; // 'flag' or 'unflag'
$reason = isset($input['reason']) ? trim($input['reason']) : null;

if ($action === 'flag') {
    $flagged = 1;
    $query = "UPDATE conversations SET flagged = ?, flag_reason = ? WHERE conversation_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isi", $flagged, $reason, $conversationId);
} else {
    $flagged = 0;
    $reason = null;
    $query = "UPDATE conversations SET flagged = ?, flag_reason = ? WHERE conversation_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isi", $flagged, $reason, $conversationId);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Conversation updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
