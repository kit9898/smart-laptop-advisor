<?php
require_once 'includes/db_connect.php';

echo "=== ADDING STOCK COLUMN ===\n\n";

// Add stock_quantity column
$sql1 = "ALTER TABLE products ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0 AFTER price";
if ($conn->query($sql1)) {
    echo "✓ Added stock_quantity column\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

// Set initial stock for all products (50 units each)
$sql2 = "UPDATE products SET stock_quantity = 50 WHERE stock_quantity = 0";
if ($conn->query($sql2)) {
    $affected = $conn->affected_rows;
    echo "✓ Set initial stock for $affected products\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

echo "\n=== VERIFICATION ===\n";
$result = $conn->query("SELECT product_id, product_name, stock_quantity FROM products LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo sprintf("Product #%d: %s - Stock: %d\n", $row['product_id'], $row['product_name'], $row['stock_quantity']);
}

$conn->close();
echo "\n✓ Database setup complete!\n";
?>
