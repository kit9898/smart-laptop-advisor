<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $stock_quantity = isset($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : 0;
    $min_stock_level = isset($_POST['min_stock_level']) ? intval($_POST['min_stock_level']) : 0;

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }

    if ($stock_quantity < 0 || $min_stock_level < 0) {
        echo json_encode(['success' => false, 'message' => 'Stock values cannot be negative']);
        exit;
    }

    // Get current stock first to calculate difference
    $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_stock = 0;
    if ($row = $result->fetch_assoc()) {
        $current_stock = $row['stock_quantity'];
    }
    $stmt->close();

    $diff = $stock_quantity - $current_stock;

    $stmt = $conn->prepare("UPDATE products SET stock_quantity = ?, min_stock_level = ? WHERE product_id = ?");
    $stmt->bind_param("iii", $stock_quantity, $min_stock_level, $product_id);

    if ($stmt->execute()) {
        // Log if stock changed
        if ($diff != 0) {
            $type = $diff > 0 ? 'adjustment' : 'adjustment'; // Could differentiate manual add/remove
            $note = 'Manual update via inventory page';
            $log_stmt = $conn->prepare("INSERT INTO inventory_logs (product_id, change_amount, change_type, note) VALUES (?, ?, ?, ?)");
            $log_stmt->bind_param("iiss", $product_id, $diff, $type, $note);
            $log_stmt->execute();
            $log_stmt->close();
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
$conn->close();
?>
