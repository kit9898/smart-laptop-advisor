<?php
include 'includes/header.php';
require_once 'includes/auth_check.php'; // Make sure user is logged in

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id == 0) {
    echo "<h1>Invalid Order</h1>";
    include 'includes/footer.php';
    exit();
}

// Fetch order to confirm it belongs to the logged-in user
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $order = $result->fetch_assoc();
} else {
    echo "<h1>Order not found or access denied.</h1>";
    include 'includes/footer.php';
    exit();
}
$stmt->close();
?>

<div style="text-align:center; padding: 40px;">
    <h1>Thank You For Your Order!</h1>
    <p>Your order has been placed successfully.</p>
    <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_id']); ?></p>
    <p><strong>Order Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
    <p>You can view your order history in your <a href="profile.php">profile</a>.</p>
</div>

<?php include 'includes/footer.php'; ?>