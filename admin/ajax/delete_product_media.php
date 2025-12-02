<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['media_id'])) {
    echo json_encode(['success' => false, 'error' => 'Media ID is required']);
    exit;
}

// Check if product_media table exists
$table_check = $conn->query("SHOW TABLES LIKE 'product_media'");
if (!$table_check || $table_check->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Product media table does not exist']);
    exit;
}

$media_id = intval($data['media_id']);

// Get media info before deleting
$stmt = $conn->prepare("SELECT media_type, media_url FROM product_media WHERE media_id = ?");
$stmt->bind_param("i", $media_id);
$stmt->execute();
$result = $stmt->get_result();
$media = $result->fetch_assoc();
$stmt->close();

if (!$media) {
    echo json_encode(['success' => false, 'error' => 'Media not found']);
    exit;
}

// Delete from database
$delete_stmt = $conn->prepare("DELETE FROM product_media WHERE media_id = ?");
$delete_stmt->bind_param("i", $media_id);

if ($delete_stmt->execute()) {
    // Delete file if it's an image
    if ($media['media_type'] === 'image' && !empty($media['media_url'])) {
        $image_path = '../../' . $media['media_url'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Media deleted successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete media']);
}

$delete_stmt->close();
?>