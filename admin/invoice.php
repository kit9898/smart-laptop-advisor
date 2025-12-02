<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    die("Invalid Order ID");
}

// Fetch Order Details
$stmt = $conn->prepare("
    SELECT o.*, u.full_name, u.email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.user_id 
    WHERE o.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found");
}

// Fetch Order Items
$stmt_items = $conn->prepare("
    SELECT oi.*, p.product_name AS model 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE oi.order_id = ?
");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
$items = [];
while ($row = $result_items->fetch_assoc()) {
    $items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #555; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .invoice-box table td { padding: 5px; vertical-align: top; }
        .invoice-box table tr td:nth-child(2) { text-align: right; }
        .invoice-box table tr.top table td { padding-bottom: 20px; }
        .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; }
        .invoice-box table tr.information table td { padding-bottom: 40px; }
        .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
        .invoice-box table tr.details td { padding-bottom: 20px; }
        .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
        .invoice-box table tr.item.last td { border-bottom: none; }
        .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td { width: 100%; display: block; text-align: center; }
            .invoice-box table tr.information table td { width: 100%; display: block; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                Smart Laptop Advisor
                            </td>
                            <td>
                                Invoice #: <?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?><br>
                                Created: <?= date('F d, Y', strtotime($order['order_date'])) ?><br>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                Smart Laptop Advisor, Inc.<br>
                                123 Tech Street<br>
                                Silicon Valley, CA 94000
                            </td>
                            <td>
                                <?= htmlspecialchars($order['shipping_name'] ?? $order['full_name']) ?><br>
                                <?= htmlspecialchars($order['email']) ?><br>
                                <?= htmlspecialchars($order['shipping_address'] ?? '') ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="heading">
                <td>Payment Method</td>
                <td>Check #</td>
            </tr>
            <tr class="details">
                <td><?= htmlspecialchars($order['payment_method'] ?? 'Credit Card') ?></td>
                <td><?= $order['order_status'] ?></td>
            </tr>
            <tr class="heading">
                <td>Item</td>
                <td>Price</td>
            </tr>
            <?php foreach ($items as $item): ?>
            <tr class="item">
                <td><?= htmlspecialchars($item['model']) ?> (x<?= $item['quantity'] ?>)</td>
                <td>$<?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td></td>
                <td>Total: $<?= number_format($order['total_amount'], 2) ?></td>
            </tr>
        </table>
    </div>
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
