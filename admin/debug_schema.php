<?php
require_once 'includes/db_connect.php';

function get_columns($conn, $table) {
    $result = $conn->query("DESCRIBE $table");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

echo "Products: " . implode(", ", get_columns($conn, 'products')) . "\n";
echo "Orders: " . implode(", ", get_columns($conn, 'orders')) . "\n";
?>
