<?php
require_once 'admin/includes/db_connect.php';
$result = $conn->query("DESCRIBE personas");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
?>
