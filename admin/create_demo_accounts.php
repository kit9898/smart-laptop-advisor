<?php
/**
 * Create Demo Admin Accounts
 * This script creates demo admin accounts with proper password hashes
 * Run this once to set up demo credentials
 */

require_once 'includes/db_connect.php';

// Demo password for all accounts
$demo_password = 'password123';
$password_hash = password_hash($demo_password, PASSWORD_DEFAULT);

echo "<h2>Creating Demo Admin Accounts</h2>";
echo "<p>Password for all accounts: <strong>password123</strong></p>";
echo "<hr>";

// First, check if roles exist
$roles_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM roles");
$roles_count = mysqli_fetch_assoc($roles_check)['count'];

if ($roles_count == 0) {
    echo "<p style='color: red;'>⚠️ Error: No roles found. Please execute module_e_schema.sql first!</p>";
    exit();
}

// Demo accounts to create
$demo_accounts = [
    [
        'admin_code' => 'ADMIN-001',
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'email' => 'sarah.johnson@smartlaptop.com',
        'role_id' => 1, // Super Admin
        'phone' => '+1 (555) 100-0001'
    ],
    [
        'admin_code' => 'ADMIN-002',
        'first_name' => 'Michael',
        'last_name' => 'Chen',
        'email' => 'michael.chen@smartlaptop.com',
        'role_id' => 2, // Product Manager
        'phone' => '+1 (555) 100-0002'
    ],
    [
        'admin_code' => 'ADMIN-003',
        'first_name' => 'Emily',
        'last_name' => 'Rodriguez',
        'email' => 'emily.rodriguez@smartlaptop.com',
        'role_id' => 4, // AI Administrator
        'phone' => '+1 (555) 100-0003'
    ],
    [
        'admin_code' => 'ADMIN-004',
        'first_name' => 'David',
        'last_name' => 'Kim',
        'email' => 'david.kim@smartlaptop.com',
        'role_id' => 3, // Order Manager
        'phone' => '+1 (555) 100-0004'
    ]
];

echo "<h3>Creating Accounts:</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";

foreach ($demo_accounts as $account) {
    // Check if account already exists
    $check_query = "SELECT admin_id FROM admin_users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 's', $account['email']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Update existing account
        $admin = mysqli_fetch_assoc($result);
        $update_query = "UPDATE admin_users SET 
            password_hash = ?,
            status = 'active',
            failed_login_attempts = 0,
            last_failed_login = NULL
            WHERE admin_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, 'si', $password_hash, $admin['admin_id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // Get role name
        $role_query = "SELECT role_name FROM roles WHERE role_id = ?";
        $role_stmt = mysqli_prepare($conn, $role_query);
        mysqli_stmt_bind_param($role_stmt, 'i', $account['role_id']);
        mysqli_stmt_execute($role_stmt);
        $role_result = mysqli_stmt_get_result($role_stmt);
        $role = mysqli_fetch_assoc($role_result);
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($account['email']) . "</td>";
        echo "<td>" . htmlspecialchars($role['role_name']) . "</td>";
        echo "<td style='color: orange;'>✓ Updated</td>";
        echo "</tr>";
        
        mysqli_stmt_close($role_stmt);
    } else {
        // Create new account
        $insert_query = "INSERT INTO admin_users 
            (admin_code, first_name, last_name, email, password_hash, role_id, phone, status, login_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, 'ssssis', 
            $account['admin_code'],
            $account['first_name'],
            $account['last_name'],
            $account['email'],
            $password_hash,
            $account['role_id'],
            $account['phone']
        );
        
        if (mysqli_stmt_execute($insert_stmt)) {
            // Get role name
            $role_query = "SELECT role_name FROM roles WHERE role_id = ?";
            $role_stmt = mysqli_prepare($conn, $role_query);
            mysqli_stmt_bind_param($role_stmt, 'i', $account['role_id']);
            mysqli_stmt_execute($role_stmt);
            $role_result = mysqli_stmt_get_result($role_stmt);
            $role = mysqli_fetch_assoc($role_result);
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($account['email']) . "</td>";
            echo "<td>" . htmlspecialchars($role['role_name']) . "</td>";
            echo "<td style='color: green;'>✓ Created</td>";
            echo "</tr>";
            
            mysqli_stmt_close($role_stmt);
        } else {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($account['email']) . "</td>";
            echo "<td>-</td>";
            echo "<td style='color: red;'>✗ Failed</td>";
            echo "</tr>";
        }
        
        mysqli_stmt_close($insert_stmt);
    }
    
    mysqli_stmt_close($stmt);
}

echo "</table>";

echo "<hr>";
echo "<h3 style='color: green;'>✓ Demo Accounts Ready!</h3>";
echo "<p><strong>Login Credentials:</strong></p>";
echo "<ul>";
echo "<li><strong>Super Admin:</strong> sarah.johnson@smartlaptop.com / password123</li>";
echo "<li><strong>Product Manager:</strong> michael.chen@smartlaptop.com / password123</li>";
echo "<li><strong>AI Administrator:</strong> emily.rodriguez@smartlaptop.com / password123</li>";
echo "<li><strong>Order Manager:</strong> david.kim@smartlaptop.com / password123</li>";
echo "</ul>";

echo "<p><a href='login.php' style='display:inline-block; padding:10px 20px; background:#435ebe; color:white; text-decoration:none; border-radius:5px; margin-top:20px;'>Go to Login Page</a></p>";

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'><em>You can run this script again anytime to reset passwords to 'password123'</em></p>";

mysqli_close($conn);
?>
