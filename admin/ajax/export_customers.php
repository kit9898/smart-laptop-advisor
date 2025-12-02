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
header('Content-Disposition: attachment; filename="customers_export_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['User ID', 'Full Name', 'Email', 'Phone', 'Location', 'Registration Date', 'Status', 'Total Orders', 'Total Spent']);

// Get filter parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT 
    u.user_id,
    u.full_name,
    u.email,
    u.default_shipping_phone as phone,
    CONCAT(COALESCE(u.default_shipping_city, ''), ', ', COALESCE(u.default_shipping_state, '')) as location,
    u.created_at as registration_date,
    u.status,
    COUNT(o.order_id) as total_orders,
    COALESCE(SUM(CASE WHEN o.order_status != 'Cancelled' AND o.order_status != 'Failed' THEN o.total_amount ELSE 0 END), 0) as total_spent
FROM users u
LEFT JOIN orders o ON u.user_id = o.user_id
WHERE 1=1";

$params = [];
$types = '';

if (!empty($search_term)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $query .= " AND DATE(u.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(u.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$query .= " GROUP BY u.user_id ORDER BY u.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Add data rows
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        '#USR-' . str_pad($row['user_id'], 4, '0', STR_PAD_LEFT),
        $row['full_name'],
        $row['email'],
        $row['phone'] ?? 'N/A',
        ($row['location'] == ', ') ? 'N/A' : $row['location'],
        date('Y-m-d H:i', strtotime($row['registration_date'])),
        ucfirst($row['status']),
        $row['total_orders'],
        number_format($row['total_spent'], 2)
    ]);
}

fclose($output);
mysqli_close($conn);
exit();
?>
