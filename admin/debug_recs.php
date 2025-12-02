<?php
require_once 'includes/db_connect.php';
$result = $conn->query("DESCRIBE recommendation_logs");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
