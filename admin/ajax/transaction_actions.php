<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$order_id = $_POST['order_id'] ?? 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID']);
    exit;
}

switch ($action) {
    case 'verify_payment':
        $stmt = $conn->prepare("UPDATE orders SET order_status = 'Completed' WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    case 'toggle_flag':
        // First get current status
        $stmt = $conn->prepare("SELECT is_flagged FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $new_status = $row['is_flagged'] ? 0 : 1;
        
        $update = $conn->prepare("UPDATE orders SET is_flagged = ? WHERE order_id = ?");
        $update->bind_param("ii", $new_status, $order_id);
        
        if ($update->execute()) {
            echo json_encode(['success' => true, 'is_flagged' => $new_status, 'message' => 'Flag status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    case 'delete_transaction':
        // Only allow deleting if status is Refunded or Cancelled (or maybe just allow it for admin power)
        // For this task "Remove the refund", we assume we delete the record.
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            // Also delete order items
            $stmt_items = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt_items->bind_param("i", $order_id);
            $stmt_items->execute();
            
            echo json_encode(['success' => true, 'message' => 'Transaction removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;
        
    case 'get_details':
        $stmt = $conn->prepare("
            SELECT oi.*, p.product_name AS model, p.image_url 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        // Get order info too
        $stmt_order = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $order_info = $stmt_order->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'items' => $items, 'order' => $order_info]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
