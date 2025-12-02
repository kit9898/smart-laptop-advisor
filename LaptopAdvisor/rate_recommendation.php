<?php
require_once 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id']) && isset($_POST['rating'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = intval($_POST['product_id']);
    $rating = intval($_POST['rating']); // Should be 1 (like) or -1 (dislike)

    if ($rating == 1 || $rating == -1) {
        // "INSERT ... ON DUPLICATE KEY UPDATE" is perfect for this.
        // It will insert a new rating, or update the existing one if the user changes their mind.
        $sql = "INSERT INTO recommendation_ratings (user_id, product_id, rating) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE rating = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $user_id, $product_id, $rating, $rating);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect back to the recommendations page
header("Location: products.php?view=recommendations");
exit();
?>