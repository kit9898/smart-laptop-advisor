<?php
require_once 'includes/db_connect.php';

// Check users table structure
echo "=== USERS TABLE ===\n";
$result = mysqli_query($conn, "DESCRIBE users");
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== ORDERS TABLE ===\n";
$result = mysqli_query($conn, "DESCRIBE orders");
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== RECOMMENDATION_LOGS TABLE ===\n";
$result = mysqli_query($conn, "DESCRIBE recommendation_logs");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Table doesn't exist yet\n";
}
?>
