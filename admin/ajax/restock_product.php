<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';

    if ($product_id <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Update Product Stock
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
        $stmt->bind_param("ii", $quantity, $product_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update stock");
        }
        $stmt->close();

        // Log the change
        $stmt = $conn->prepare("INSERT INTO inventory_logs (product_id, change_amount, change_type, note) VALUES (?, ?, 'restock', ?)");
        $stmt->bind_param("iis", $product_id, $quantity, $note);
        if (!$stmt->execute()) {
            throw new Exception("Failed to log transaction");
        }
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
$conn->close();
?>
