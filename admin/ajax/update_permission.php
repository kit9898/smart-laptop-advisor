<?php
/**
 * Update Role Permission
 */

header('Content-Type: application/json');
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../includes/db_connect.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$role_id = isset($input['role_id']) ? intval($input['role_id']) : 0;
$permission_id = isset($input['permission_id']) ? intval($input['permission_id']) : 0;
$access_level = $input['access_level'] ?? 'none';

if ($role_id <= 0 || $permission_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Valid access levels
$valid_levels = ['none', 'read', 'write', 'full'];
if (!in_array($access_level, $valid_levels)) {
    echo json_encode(['success' => false, 'message' => 'Invalid access level']);
    exit();
}

// Check if record exists
$check_query = "SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "ii", $role_id, $permission_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    if ($access_level === 'none') {
        // Remove permission
        $update_query = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $role_id, $permission_id);
    } else {
        // Update permission
        $update_query = "UPDATE role_permissions SET access_level = ? WHERE role_id = ? AND permission_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sii", $access_level, $role_id, $permission_id);
    }
} else {
    if ($access_level !== 'none') {
        // Insert permission
        $update_query = "INSERT INTO role_permissions (role_id, permission_id, access_level) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "iis", $role_id, $permission_id, $access_level);
    } else {
        // Nothing to do
        echo json_encode(['success' => true, 'message' => 'No changes made']);
        exit();
    }
}

if (mysqli_stmt_execute($stmt)) {
    // We avoid logging every single click to avoid clutter, or maybe log batches?
    // Let's log if needed but keeping it silent for UI responsiveness is common for matrix updates.
    // However, for audit, we should log.
    
    /*
    $log_query = "INSERT INTO admin_activity_log (admin_id, action, module, description, affected_record_type, affected_record_id, ip_address) VALUES (?, 'update_permission', 'roles', ?, 'role', ?, ?)";
    $role_desc = "Updated permission ID $permission_id to $access_level for role $role_id";
    // ... execution code ...
    */
    
    echo json_encode(['success' => true, 'message' => 'Permission updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
