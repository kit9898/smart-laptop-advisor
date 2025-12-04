<?php
require_once 'includes/db_connect.php';

echo "Setting up System Settings...\n";

// 1. Create Table
$create_table_sql = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'boolean', 'integer') DEFAULT 'text',
    category VARCHAR(50) NOT NULL,
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $create_table_sql)) {
    echo "Table 'system_settings' checked/created successfully.\n";
} else {
    die("Error creating table: " . mysqli_error($conn) . "\n");
}

// 2. Default Settings
$defaults = [
    // General
    [
        'key' => 'site_name',
        'value' => 'Smart Laptop Advisor',
        'type' => 'text',
        'category' => 'general',
        'desc' => 'The name of the website displayed in titles and headers.'
    ],
    [
        'key' => 'site_description',
        'value' => 'Your trusted guide for finding the perfect laptop.',
        'type' => 'text',
        'category' => 'general',
        'desc' => 'Meta description for SEO and footer text.'
    ],
    [
        'key' => 'support_email',
        'value' => 'support@laptopadvisor.com',
        'type' => 'text',
        'category' => 'general',
        'desc' => 'Email address displayed for customer support.'
    ],
    [
        'key' => 'contact_phone',
        'value' => '+1 (555) 123-4567',
        'type' => 'text',
        'category' => 'general',
        'desc' => 'Contact phone number.'
    ],
    [
        'key' => 'company_address',
        'value' => '123 Tech Blvd, Silicon Valley, CA',
        'type' => 'text',
        'category' => 'general',
        'desc' => 'Physical address of the company.'
    ],
    
    // Security
    [
        'key' => 'max_login_attempts',
        'value' => '5',
        'type' => 'integer',
        'category' => 'security',
        'desc' => 'Maximum number of failed login attempts before lockout.'
    ],
    [
        'key' => 'password_expiry_days',
        'value' => '90',
        'type' => 'integer',
        'category' => 'security',
        'desc' => 'Days before a password expires (0 for no expiry).'
    ],
    
    // Maintenance
    [
        'key' => 'maintenance_mode',
        'value' => '0',
        'type' => 'boolean',
        'category' => 'maintenance',
        'desc' => 'Enable to show maintenance page to non-admin users.'
    ]
];

// 3. Insert Defaults
foreach ($defaults as $setting) {
    $check_sql = "SELECT setting_id FROM system_settings WHERE setting_key = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "s", $setting['key']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        $insert_sql = "INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "sssss", $setting['key'], $setting['value'], $setting['type'], $setting['category'], $setting['desc']);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            echo "Inserted setting: {$setting['key']}\n";
        } else {
            echo "Error inserting {$setting['key']}: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Setting {$setting['key']} already exists. Skipping.\n";
    }
}

echo "Setup completed successfully.\n";
?>
