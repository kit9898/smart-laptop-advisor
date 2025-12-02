<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode([]);
    exit;
}

$query = "SELECT * FROM inventory_logs WHERE product_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

echo json_encode($logs);

$stmt->close();
$conn->close();
?>
