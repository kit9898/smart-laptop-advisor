<?php
// FIXED: Use the same db connection file as other scripts
require_once 'includes/db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Validate request method
if ($_SERVER[' REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

// Fetch product details
$stmt = $conn->prepare("SELECT product_id, product_name, brand, price FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($product = $result->fetch_assoc()) {
    try {
        // Check if user already has an unused voucher for this product
        $check_stmt = $conn->prepare("
            SELECT voucher_id, voucher_code, discount_type, discount_value
            FROM temp_vouchers 
            WHERE user_id = ? AND product_id = ? AND used = 0
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($existing = $check_result->fetch_assoc()) {
            // Auto-apply existing voucher to session
            $_SESSION['temp_voucher'] = [
                'voucher_id' => $existing['voucher_id'],
                'voucher_code' => $existing['voucher_code'],
                'discount_type' => $existing['discount_type'],
                'discount_value' => $existing['discount_value'],
                'product_id' => $product_id,
                'product_name' => $product['product_name'],
                'brand' => $product['brand']
            ];
            
            // Return existing voucher
            echo json_encode([
                'success' => true,
                'existing' => true,
                'auto_applied' => true,
                'voucher' => $_SESSION['temp_voucher']
            ]);
            exit;
        }
        $check_stmt->close();
        
        // Generate unique voucher code
        $voucher_code = 'TEMP-' . $product_id . '-' . strtoupper(substr(md5(uniqid($user_id, true)), 0, 6));
        
        // Configuration
        $discount_type = 'percentage';
        $discount_value = 10.00; // 10% discount
        
        // EXPIRE IN 10 MINUTES
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Insert voucher into database
        $insert_stmt = $conn->prepare("
            INSERT INTO temp_vouchers (user_id, product_id, voucher_code, discount_type, discount_value, expires_at, used)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        $insert_stmt->bind_param("iissds", $user_id, $product_id, $voucher_code, $discount_type, $discount_value, $expires_at);
        
        if ($insert_stmt->execute()) {
            $voucher_data = [
                'voucher_id' => $insert_stmt->insert_id,
                'voucher_code' => $voucher_code,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'product_id' => $product_id,
                'product_name' => $product['product_name'],
                'brand' => $product['brand'],
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expires_at
            ];

            // AUTO-APPLY TO SESSION
            $_SESSION['temp_voucher'] = $voucher_data;

            // Success response
            echo json_encode([
                'success' => true,
                'existing' => false,
                'auto_applied' => true,
                'voucher' => $voucher_data
            ]);
        } else {
            throw new Exception('Failed to create voucher: ' . $insert_stmt->error);
        }
        
        $insert_stmt->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
}

$stmt->close();
$conn->close();
?>