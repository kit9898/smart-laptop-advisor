<?php
require_once 'includes/db_connect.php';

$table = 'product_reviews';
$result = $conn->query("SHOW COLUMNS FROM $table");

echo "<h2>Columns in $table:</h2><ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['Field'] . " (" . $row['Type'] . ")</li>";
}
echo "</ul>";

echo "<h2>Test Update Review 1</h2>";
// Try to manually fetch a review
$test_id = 1; // Assume ID 1 exists
$check = $conn->query("SELECT * FROM product_reviews LIMIT 1");
if ($r = $check->fetch_assoc()) {
    $test_id = $r['review_id'];
    echo "Found review ID: $test_id<br>";
    
    // Attempt dummy update
    $sql = "UPDATE product_reviews SET admin_response = 'TEST RESPONSE " . time() . "' WHERE review_id = $test_id";
    if ($conn->query($sql)) {
        echo "Update SUCCESS. Rows affected: " . $conn->affected_rows;
    } else {
        echo "Update FAILED: " . $conn->error;
    }
} else {
    echo "No reviews found to test.";
}
?>
