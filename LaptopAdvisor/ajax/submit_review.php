<?php
include '../includes/db_connect.php';
// If header_no_ui.php doesn't exist, we'll just connect manually or include header and clean buffer
// But typically we need session and db. Assuming include 'includes/header.php' works but might output HTML.
// Let's rely on standard established pattern or just use what we know.
// Getting DB connection from existing patterns.

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a review.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($_POST['product_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

if ($product_id <= 0 || $rating < 1 || $rating > 5 || empty($review_text)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid rating and review text.']);
    exit;
}

// Check for existing review
$check_stmt = $conn->prepare("SELECT review_id FROM product_reviews WHERE product_id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $product_id, $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product.']);
    exit;
}
$check_stmt->close();

// Insert Review
$stmt = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $product_id, $user_id, $rating, $review_text);

if ($stmt->execute()) {
    $review_id = $stmt->insert_id;
    $stmt->close();
    
    // Handle File Uploads
    $upload_dir = '../uploads/reviews/' . $product_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $uploaded_media = [];
    $errors = [];

    if (isset($_FILES['review_media']) && !empty($_FILES['review_media']['name'][0])) {
        $files = $_FILES['review_media'];
        $count = count($files['name']);
        
        // Limit to 3 files
        if ($count > 3) {
            $count = 3; 
        }

        for ($i = 0; $i < $count; $i++) {
            $name = $files['name'][$i];
            $tmp_name = $files['tmp_name'][$i];
            $size = $files['size'][$i];
            $error = $files['error'][$i];
            
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "$name failed to upload (Error Code: $error)";
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed_images = ['jpg', 'jpeg', 'png', 'gif'];
            $allowed_videos = ['mp4', 'webm'];
            
            $media_type = '';
            if (in_array($ext, $allowed_images)) {
                $media_type = 'image';
                if ($size > 5 * 1024 * 1024) {
                    $errors[] = "$name exceeds 5MB limit";
                    continue; 
                }
            } elseif (in_array($ext, $allowed_videos)) {
                $media_type = 'video';
                if ($size > 20 * 1024 * 1024) {
                    $errors[] = "$name exceeds 20MB limit";
                    continue; 
                }
            } else {
                $errors[] = "$name has invalid file type ($ext)";
                continue; 
            }

            $new_filename = $review_id . '_' . uniqid() . '.' . $ext;
            $destination = $upload_dir . $new_filename;
            
            // Store relative path for DB
            $db_path = 'uploads/reviews/' . $product_id . '/' . $new_filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $media_stmt = $conn->prepare("INSERT INTO review_media (review_id, file_path, media_type) VALUES (?, ?, ?)");
                $media_stmt->bind_param("iss", $review_id, $db_path, $media_type);
                $media_stmt->execute();
                $media_stmt->close();
                $uploaded_media[] = $db_path;
            } else {
                $errors[] = "Failed to move uploaded file: $name";
            }
        }
    }

    $msg = 'Review submitted successfully!';
    if (!empty($errors)) {
        $msg .= ' However, some files were not uploaded: ' . implode(', ', $errors);
    }

    echo json_encode(['success' => true, 'message' => $msg, 'review_id' => $review_id, 'uploaded_count' => count($uploaded_media), 'errors' => $errors]);

} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>
