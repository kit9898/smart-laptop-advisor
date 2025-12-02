<?php
require_once 'includes/db_connect.php';

echo "=== PRODUCTS TABLE SCHEMA ===\n\n";

$result = $conn->query("DESCRIBE products");
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-20s %-15s %s\n", $row['Field'], $row['Type'], $row['Null']);
}

echo "\n=== CHECKING FOR STOCK COLUMN ===\n";
$check = $conn->query("SHOW COLUMNS FROM products LIKE 'stock%'");
if ($check->num_rows > 0) {
    echo "✓ Stock column exists!\n";
    while ($row = $check->fetch_assoc()) {
        echo "  - " . $row['Field'] . "\n";
    }
} else {
    echo "✗ No stock column found. Need to add it.\n";
}

$conn->close();
?>
