<?php
/**
 * Get Role Details
 */

header('Content-Type: application/json');
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../includes/db_connect.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Role ID is required']);
    exit();
}

$role_id = intval($_GET['id']);

$query = "SELECT role_id, role_name, role_code, description, status, is_system_role FROM roles WHERE role_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $role_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'data' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Role not found']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
