<?php
require_once 'includes/db_connect.php';
$result = $conn->query("SELECT * FROM personas");
while ($row = $result->fetch_assoc()) {
    echo $row['persona_id'] . ": " . $row['name'] . "\n";
}
?>
