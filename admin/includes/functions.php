<?php
/**
 * Admin Helper Functions
 */

/**
 * Log an activity to the database
 *
 * @param mysqli $conn Database connection
 * @param int $admin_id ID of the admin performing the action
 * @param string $action Action type (create, update, delete, login, logout, etc.)
 * @param string $module Module name (products, orders, users, auth, system)
 * @param string $description Human-readable description of the action
 * @param string $record_type (Optional) Type of record affected (e.g., 'product', 'order')
 * @param int $record_id (Optional) ID of the record affected
 * @return bool True on success, False on failure
 */
function logActivity($conn, $admin_id, $action, $module, $description, $record_type = null, $record_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    $sql = "INSERT INTO admin_activity_log 
            (admin_id, action, module, description, affected_record_type, affected_record_id, ip_address, user_agent, request_uri) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("issssisss", $admin_id, $action, $module, $description, $record_type, $record_id, $ip_address, $user_agent, $request_uri);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}

/**
 * Triggers the recommendation engine retraining process asynchronously
 * 
 * @return bool True if request sent successfully, False otherwise
 */
function triggerRecommendationTraining() {
    $url = 'http://127.0.0.1:5000/api/train';
    $data = json_encode(['async' => true]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Fast timeout, fire and forget logic
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    // We expect a timeout (succesful fire-and-forget) or a quick success response
    if ($error && strpos($error, 'timed out') === false) {
        // Real error occurred
        return false;
    }
    
    return true;
}

/**
 * ==================== RBAC FUNCTIONS ====================
 */

/**
 * Load admin permissions into session (cached for performance)
 * Call this once per page load after login check
 * 
 * @param mysqli $conn Database connection
 * @return array Array of permissions with access levels
 */
function loadAdminPermissions($conn) {
    // Return cached permissions if already loaded
    if (isset($_SESSION['admin_permissions']) && !empty($_SESSION['admin_permissions'])) {
        return $_SESSION['admin_permissions'];
    }
    
    $role_id = $_SESSION['admin_role_id'] ?? null;
    if (!$role_id) {
        return [];
    }
    
    // Fetch role info
    $role_query = "SELECT role_name, role_code, is_system_role FROM roles WHERE role_id = ?";
    $role_stmt = mysqli_prepare($conn, $role_query);
    mysqli_stmt_bind_param($role_stmt, 'i', $role_id);
    mysqli_stmt_execute($role_stmt);
    $role_result = mysqli_stmt_get_result($role_stmt);
    $role = mysqli_fetch_assoc($role_result);
    mysqli_stmt_close($role_stmt);
    
    $_SESSION['admin_role_name'] = $role['role_name'] ?? 'Unknown';
    $_SESSION['admin_role_code'] = $role['role_code'] ?? 'unknown';
    $_SESSION['is_super_admin'] = ($role['is_system_role'] == 1 && $role['role_code'] === 'super_admin');
    
    // Fetch all permissions for this role
    $query = "SELECT p.permission_code, rp.access_level 
              FROM role_permissions rp
              JOIN permissions p ON rp.permission_id = p.permission_id
              WHERE rp.role_id = ? AND rp.access_level != 'none'";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $permissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[$row['permission_code']] = $row['access_level'];
    }
    mysqli_stmt_close($stmt);
    
    $_SESSION['admin_permissions'] = $permissions;
    return $permissions;
}

/**
 * Check if current admin has a specific permission
 * 
 * @param string $permission_code Permission code to check (e.g., 'product.view')
 * @param string $min_level Minimum access level required ('read', 'write', 'full')
 * @return bool True if permission granted, False otherwise
 */
function hasPermission($permission_code, $min_level = 'read') {
    // Super admin bypass all permission checks
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        return true;
    }
    
    $permissions = $_SESSION['admin_permissions'] ?? [];
    
    if (!isset($permissions[$permission_code])) {
        return false;
    }
    
    $current_level = $permissions[$permission_code];
    
    // Access level hierarchy: read < write < full
    $level_hierarchy = ['read' => 1, 'write' => 2, 'full' => 3];
    
    $current_value = $level_hierarchy[$current_level] ?? 0;
    $required_value = $level_hierarchy[$min_level] ?? 1;
    
    return $current_value >= $required_value;
}

/**
 * Require a permission to access the current page
 * Redirects to dashboard with error message if permission denied
 * 
 * @param string $permission_code Permission code required
 * @param string $min_level Minimum access level required
 */
function requirePermission($permission_code, $min_level = 'read') {
    if (!hasPermission($permission_code, $min_level)) {
        $_SESSION['access_denied_message'] = "You don't have permission to access this page. Required permission: " . $permission_code;
        header('Location: admin_dashboard.php');
        exit();
    }
}

/**
 * Get the required permission for a specific admin page
 * 
 * @param string $page_name The basename of the page (e.g., 'admin_products.php')
 * @return string|null Permission code required, or null if no permission needed
 */
function getPagePermission($page_name) {
    $page_permissions = [
        // Dashboard
        'admin_dashboard.php' => 'dashboard.view',
        
        // Product Module
        'admin_products.php' => 'product.view',
        'admin_inventory.php' => 'inventory.manage',
        'admin_reviews.php' => 'review.manage',
        'admin_coupons.php' => 'coupon.manage',
        
        // Order Module
        'admin_orders.php' => 'order.view',
        'admin_transactions.php' => 'transaction.manage',
        
        // AI Module
        'admin_personas.php' => 'persona.manage',
        'admin_recommendation_logs.php' => 'ai.logs',
        'admin_ai_performance.php' => 'ai.performance',
        
        // Chatbot Module
        'admin_conversation_logs.php' => 'chatbot.conversations',
        'admin_chatbot_analytics.php' => 'chatbot.analytics',
        
        // User Module
        'admin_customers.php' => 'customer.view',
        'admin_admins.php' => 'admin.view',
        'admin_roles.php' => 'role.manage',
        
        // System Module
        'admin_logs.php' => 'logs.view',
        'admin_settings.php' => 'settings.view',
        'admin_reports.php' => 'reports.view',
        
        // Profile - Everyone can access their own profile
        'admin_profile.php' => null,
    ];
    
    return $page_permissions[$page_name] ?? null;
}

/**
 * Check page access and load permissions
 * Call this at the top of every admin page after session start and DB connection
 * 
 * @param mysqli $conn Database connection  
 * @param bool $check_permission Whether to check page permission
 */
function initAdminPage($conn, $check_permission = true) {
    // Check if logged in
    if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Load permissions into session
    loadAdminPermissions($conn);
    
    // Check page-specific permission
    if ($check_permission) {
        $current_page = basename($_SERVER['PHP_SELF']);
        $required_permission = getPagePermission($current_page);
        
        if ($required_permission !== null) {
            requirePermission($required_permission);
        }
    }
}
?>
