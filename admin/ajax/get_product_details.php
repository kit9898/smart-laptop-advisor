<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Product ID is required']);
    exit;
}

$product_id = intval($_GET['id']);

// Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit;
}

// Fetch product media (additional images/videos) - Check if table exists first
$media = [];
$table_check = $conn->query("SHOW TABLES LIKE 'product_media'");
if ($table_check && $table_check->num_rows > 0) {
    $media_stmt = $conn->prepare("SELECT * FROM product_media WHERE product_id = ? ORDER BY display_order ASC");
    $media_stmt->bind_param("i", $product_id);
    $media_stmt->execute();
    $media_result = $media_stmt->get_result();
    while ($row = $media_result->fetch_assoc()) {
        $media[] = $row;
    }
    $media_stmt->close();
}

// Fetch product reviews - Check if table exists first
$reviews = [];
$reviews_check = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($reviews_check && $reviews_check->num_rows > 0) {
    $reviews_stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name 
        FROM reviews r 
        LEFT JOIN users u ON r.user_id = u.user_id 
        WHERE r.product_id = ? 
        ORDER BY r.created_at DESC
    ");
    $reviews_stmt->bind_param("i", $product_id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    while ($row = $reviews_result->fetch_assoc()) {
        $reviews[] = $row;
    }
    $reviews_stmt->close();
}

echo json_encode([
    'success' => true,
    'product' => $product,
    'media' => $media,
    'reviews' => $reviews
]);
?>