<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Check if order_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit();
}

$order_id = intval($_GET['id']);

try {
    // Fetch main order details with user information
    $order_stmt = $conn->prepare("
        SELECT o.*, 
               u.full_name, u.email, u.phone as user_phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = ?
    ");
    
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    
    if ($order_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit();
    }
    
    $order = $order_result->fetch_assoc();
    $order_stmt->close();
    
    // Fetch order items with product details
    $items_stmt = $conn->prepare("
        SELECT oi.quantity, oi.price_at_purchase,
               p.product_name, p.image_url, p.brand, p.product_category
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    $subtotal = 0;
    
    while ($item = $items_result->fetch_assoc()) {
        $item_total = $item['quantity'] * $item['price_at_purchase'];
        $subtotal += $item_total;
        
        // Fix image path
        if (!empty($item['image_url'])) {
            // If path doesn't start with http or /, add ../
            if (!preg_match('/^(http|\/)/i', $item['image_url'])) {
                $item['image_url'] = '../' . $item['image_url'];
            }
        } else {
            $item['image_url'] = '../LaptopAdvisor/images/placeholder.png';
        }
        
        $item['subtotal'] = $item_total;
        $items[] = $item;
    }
    $items_stmt->close();
    
    // Structure the response
    $response = [
        'success' => true,
        'order' => [
            'order_id' => $order['order_id'],
            'order_date' => $order['order_date'],
            'order_status' => $order['order_status'],
            'total_amount' => floatval($order['total_amount']),
            'customer' => [
                'full_name' => $order['full_name'] ?? 'Guest User',
                'email' => $order['email'] ?? 'N/A',
                'phone' => $order['user_phone'] ?? $order['shipping_phone'] ?? 'N/A'
            ],
            'shipping' => [
                'name' => $order['shipping_name'] ?? $order['full_name'] ?? 'N/A',
                'address' => $order['shipping_address'] ?? 'N/A',
                'city' => $order['shipping_city'] ?? 'N/A',
                'state' => $order['shipping_state'] ?? 'N/A',
                'zip' => $order['shipping_zip'] ?? 'N/A',
                'country' => $order['shipping_country'] ?? 'N/A',
                'phone' => $order['shipping_phone'] ?? 'N/A'
            ],
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping_cost' => 0.00, // Free shipping
            'tax' => 0.00,
            'total' => floatval($order['total_amount'])
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
