<?php
require_once 'includes/db_connect.php';

// Check if column exists
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'battery_life'");
if ($result->num_rows == 0) {
    echo "Column 'battery_life' does not exist. Attempting to add it...\n";
    $sql = "ALTER TABLE products ADD COLUMN battery_life VARCHAR(50) DEFAULT NULL AFTER display_size";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'battery_life' added successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'battery_life' already exists.\n";
}

// Check for battery_capacity just in case
$result_cap = $conn->query("SHOW COLUMNS FROM products LIKE 'battery_capacity'");
if ($result_cap->num_rows > 0) {
    echo "Note: Column 'battery_capacity' also exists.\n";
}

$conn->close();
?>
