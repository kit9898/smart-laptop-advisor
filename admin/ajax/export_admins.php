<?php
/**
 * Export Admins to CSV
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db_connect.php';

// Get filter parameters (same as in admin_admins.php)
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$admin_query = "SELECT 
    a.admin_id,
    a.admin_code,
    a.first_name,
    a.last_name,
    a.email,
    a.phone,
    r.role_name,
    a.status,
    a.last_login,
    a.login_count,
    a.created_at
FROM admin_users a
LEFT JOIN roles r ON a.role_id = r.role_id
WHERE 1=1";

$params = [];
$types = '';

if (!empty($search_term)) {
    $admin_query .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.email LIKE ? OR a.admin_code LIKE ?)";
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if (!empty($role_filter)) {
    $admin_query .= " AND a.role_id = ?";
    $params[] = $role_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $admin_query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$admin_query .= " ORDER BY a.created_at DESC";

$stmt = mysqli_prepare($conn, $admin_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="admin_users_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel compatibility
fputs($output, "\xEF\xBB\xBF");

// Add headers
fputcsv($output, ['ID', 'Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status', 'Last Login', 'Login Count', 'Created At']);

// Add data rows
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['admin_id'],
        $row['admin_code'],
        $row['first_name'],
        $row['last_name'],
        $row['email'],
        $row['phone'],
        $row['role_name'],
        ucfirst($row['status']),
        $row['last_login'],
        $row['login_count'],
        $row['created_at']
    ]);
}

fclose($output);
mysqli_close($conn);
exit();
