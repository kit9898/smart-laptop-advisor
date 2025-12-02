<?php
require_once 'admin/includes/db_connect.php';
$result = $conn->query('SHOW TABLES');
while($row=$result->fetch_row()) {
    echo $row[0]."\n";
}
?>
