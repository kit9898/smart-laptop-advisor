<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Check if product_media table exists
$table_check = $conn->query("SHOW TABLES LIKE 'product_media'");
if (!$table_check || $table_check->num_rows === 0) {
    // Create the table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS product_media (
        media_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        media_type ENUM('image', 'video') NOT NULL,
        media_url VARCHAR(255) NOT NULL,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    )";
    $conn->query($create_table);
}

if (!isset($_POST['product_id']) || !isset($_POST['media_type'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$product_id = intval($_POST['product_id']);
$media_type = $_POST['media_type'];
$media_url = null;

if ($media_type === 'image') {
    // Handle image upload
    if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'No image file uploaded']);
        exit;
    }
    
    $file = $_FILES['media_file'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid image type']);
        exit;
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Image size exceeds 5MB limit']);
        exit;
    }
    
    // Upload image
    $upload_dir = '../../LaptopAdvisor/images/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $extension_map = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    $safe_extension = $extension_map[$mime_type];
    $new_filename = uniqid('product_' . $product_id . '_') . '.' . $safe_extension;
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $media_url = 'LaptopAdvisor/images/' . $new_filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload image']);
        exit;
    }
    
} else if ($media_type === 'video') {
    // Handle video URL
    if (!isset($_POST['video_url']) || empty($_POST['video_url'])) {
        echo json_encode(['success' => false, 'error' => 'Video URL is required']);
        exit;
    }
    
    $video_url = trim($_POST['video_url']);
    if (!filter_var($video_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid video URL']);
        exit;
    }
    
    $media_url = $video_url;
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid media type']);
    exit;
}

// Get next display order
$order_stmt = $conn->prepare("SELECT MAX(display_order) as max_order FROM product_media WHERE product_id = ?");
$order_stmt->bind_param("i", $product_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order_row = $order_result->fetch_assoc();
$display_order = ($order_row['max_order'] ?? 0) + 1;
$order_stmt->close();

// Insert media record
$stmt = $conn->prepare("INSERT INTO product_media (product_id, media_type, media_url, display_order) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $product_id, $media_type, $media_url, $display_order);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'media_id' => $conn->insert_id,
        'message' => 'Media uploaded successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save media to database']);
}

$stmt->close();
?>