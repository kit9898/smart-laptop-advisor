<?php
require_once 'includes/db_connect.php';

// Check authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized Access");
}

echo "<h1>Starting Product Data Migration</h1>";
echo "<pre>";

// Map Old -> New
$maps = [
    'Business' => 'Professional',
    'Gaming' => 'Gamer',
    'General Use' => 'Home User',
    'General' => 'Home User', // Handle both variations
    'Creative' => 'Creative', // Ensure consistency
    'Student' => 'Student'
];

foreach ($maps as $old => $new) {
    if ($old == $new) continue;

    // Update primary_use_case
    $sql1 = "UPDATE products SET primary_use_case = '$new' WHERE primary_use_case = '$old'";
    if ($conn->query($sql1)) {
        echo "Updated primary_use_case: '$old' -> '$new' (Rows: " . $conn->affected_rows . ")\n";
    } else {
        echo "Error updating primary_use_case '$old': " . $conn->error . "\n";
    }

    // Update related_to_category
    $sql2 = "UPDATE products SET related_to_category = '$new' WHERE related_to_category = '$old'";
    if ($conn->query($sql2)) {
        echo "Updated related_to_category: '$old' -> '$new' (Rows: " . $conn->affected_rows . ")\n";
    } else {
        echo "Error updating related_to_category '$old': " . $conn->error . "\n";
    }
}

echo "\nMigration Completed.\n";
echo "<a href='admin_products.php'>Return to Products</a>";
echo "</pre>";
?>
