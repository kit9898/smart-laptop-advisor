<?php
require_once 'includes/db_connect.php';

if (!isset($_POST['action'])) {
    header("Location: cart.php");
    exit();
}

$action = $_POST['action'];

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ACTION: ADD TO CART
if ($action == 'add' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    if ($quantity > 0) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
    }
    header("Location: cart.php");
    exit();
}

// ACTION: UPDATE CART
if ($action == 'update' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    if ($quantity > 0) {
        $_SESSION['cart'][$product_id] = $quantity;
    } else {
        unset($_SESSION['cart'][$product_id]);
    }
    header("Location: cart.php");
    exit();
}

// ACTION: REMOVE FROM CART
if ($action == 'remove' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    unset($_SESSION['cart'][$product_id]);
    header("Location: cart.php");
    exit();
}

// ACTION: CHECKOUT
if ($action == 'checkout') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) {
        header("Location: cart.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $sub_total = 0;
    
    // Validate and sanitize shipping address information
    $shipping_name = trim($_POST['shipping_name'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $shipping_city = trim($_POST['shipping_city'] ?? '');
    $shipping_state = trim($_POST['shipping_state'] ?? '');
    $shipping_zip = trim($_POST['shipping_zip'] ?? '');
    $shipping_country = trim($_POST['shipping_country'] ?? '');
    $shipping_phone = trim($_POST['shipping_phone'] ?? '');
    
    // Check if all required shipping fields are provided
    if (empty($shipping_name) || empty($shipping_address) || empty($shipping_city) || 
        empty($shipping_state) || empty($shipping_zip) || empty($shipping_country) || 
        empty($shipping_phone)) {
        header("Location: checkout.php?error=missing_address");
        exit();
    }
    
    // Calculate total price from database to ensure price integrity
    $product_ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $types = str_repeat('i', count($product_ids));
    
    $stmt = $conn->prepare("SELECT product_id, price FROM products WHERE product_id IN ($placeholders)");
    $stmt->bind_param($types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products_from_db = [];
    while ($row = $result->fetch_assoc()) {
        $products_from_db[$row['product_id']] = $row['price'];
    }
    
    $cart_items_for_db = [];
    foreach ($cart as $product_id => $quantity) {
        if (isset($products_from_db[$product_id])) {
            $price = $products_from_db[$product_id];
            $sub_total += $price * $quantity;
            $cart_items_for_db[] = [
                'id' => $product_id,
                'quantity' => $quantity,
                'price' => $price
            ];
        }
    }
    
    // --- Apply Coupon Discount to Final Order Total ---
    $discount_amount = 0;
    $temp_voucher_discount = 0;
    $grand_total = $sub_total;
    
    // Apply regular coupon
    if (isset($_SESSION['coupon'])) {
        $coupon = $_SESSION['coupon'];
        if ($coupon['discount_type'] == 'percentage') {
            $discount_amount = $sub_total * ($coupon['discount_value'] / 100);
        } elseif ($coupon['discount_type'] == 'fixed') {
            $discount_amount = $coupon['discount_value'];
        }
        $grand_total = $sub_total - $discount_amount;
        if ($grand_total < 0) $grand_total = 0;
    }
    
    // Apply temp voucher (can stack with regular coupon)
    // Only apply discount to items matching the voucher's product_id
    if (isset($_SESSION['temp_voucher'])) {
        $temp_voucher = $_SESSION['temp_voucher'];
        $voucher_product_id = isset($temp_voucher['product_id']) ? intval($temp_voucher['product_id']) : 0;
        
        // Calculate discount only for items matching the voucher's product
        foreach ($cart_items_for_db as $item) {
            if ($item['id'] == $voucher_product_id) {
                $line_total = $item['price'] * $item['quantity'];
                
                if ($temp_voucher['discount_type'] == 'percentage') {
                    $temp_voucher_discount += $line_total * ($temp_voucher['discount_value'] / 100);
                } elseif ($temp_voucher['discount_type'] == 'fixed') {
                    // For fixed discount, apply proportionally or as a flat amount
                    $temp_voucher_discount += min($temp_voucher['discount_value'], $line_total);
                }
                break; // Only apply to matching product
            }
        }
        
        $grand_total = $grand_total - $temp_voucher_discount;
        if ($grand_total < 0) $grand_total = 0;
    }
    
    // Start a transaction
    $conn->begin_transaction();
    try {
        // 1. Insert into 'orders' table with shipping address
        $order_status = 'Pending';
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, order_status, shipping_name, shipping_address, shipping_city, shipping_state, shipping_zip, shipping_country, shipping_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idssssssss", $user_id, $grand_total, $order_status, $shipping_name, $shipping_address, $shipping_city, $shipping_state, $shipping_zip, $shipping_country, $shipping_phone);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        
        // 2. Insert into 'order_items' table AND Deduct Stock
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
        
        // [START] STOCK DEDUCTION UPDATE
        $stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        
        foreach($cart_items_for_db as $item) {
            // A. Insert into order_items
            $stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
            $stmt->execute();
            
            // B. Run Stock Deduction
            $stock_stmt->bind_param("ii", $item['quantity'], $item['id']);
            $stock_stmt->execute();
        }
        $stock_stmt->close(); 
        // [END] STOCK DEDUCTION UPDATE
        
        // 3. Mark temp voucher as used if one was applied
        if (isset($_SESSION['temp_voucher']) && isset($_SESSION['temp_voucher']['voucher_id'])) {
            $voucher_id = intval($_SESSION['temp_voucher']['voucher_id']);
            $update_stmt = $conn->prepare("UPDATE temp_vouchers SET used = TRUE, used_at = NOW() WHERE voucher_id = ? AND user_id = ?");
            $update_stmt->bind_param("ii", $voucher_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Commit the transaction
        $conn->commit();

        // Clear the cart, coupon, and temp voucher
        unset($_SESSION['cart']);
        unset($_SESSION['coupon']);
        unset($_SESSION['temp_voucher']);
        
        header("Location: order_success.php?order_id=" . $order_id);
        exit();
        
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        // Log the error for debugging
        error_log("Checkout Error: " . $exception->getMessage());
        // Show detailed error (REMOVE THIS IN PRODUCTION!)
        header("Location: cart.php?error=checkout_failed&debug=" . urlencode($exception->getMessage()));
        exit();
    }
}

// Fallback redirect
header("Location: cart.php");
exit();
?>