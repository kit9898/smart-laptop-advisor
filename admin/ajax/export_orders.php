<?php
session_start();
require_once '../includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['Order ID', 'Customer Name', 'Email', 'Order Date', 'Status', 'Total Amount', 'Payment Method']);

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT o.*, u.full_name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.user_id WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (o.order_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status) && $status !== 'all') {
    $query .= " AND o.order_status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Add data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        'ORD-' . str_pad($row['order_id'], 4, '0', STR_PAD_LEFT),
        $row['full_name'] ?? 'Guest',
        $row['email'] ?? 'N/A',
        date('Y-m-d H:i', strtotime($row['order_date'])),
        ucfirst($row['order_status']),
        number_format($row['total_amount'], 2),
        'Credit Card' // Hardcoded as per requirement
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
exit();
?>
