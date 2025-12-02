# Product Image/Video Gallery Implementation Guide

## Overview
Add a gallery with multiple product photos and videos to `product_details.php`.

---

## Option 1: Quick Solution (Using Comma-Separated URLs)

### Step 1: Update Database (Optional - For Existing Products)

Add multiple image URLs to your products table. You can store them as comma-separated values in the existing `image_url` field:

```sql
-- Example: Update a product with multiple images
UPDATE products 
SET image_url = 'img1.jpg,img2.jpg,img3.jpg,video.mp4' 
WHERE product_id = 1;
```

### Step 2: Update `product_details.php`

Find the **product image gallery section** (around line 538) and **replace it** with:

```php
<!-- Left Column: Image/Video Gallery -->
<div class="product-image-gallery">
    <?php
    // Parse multiple images/videos from image_url field (comma-separated)
    $media_items = [];
    if (!empty($product['image_url'])) {
        $media_urls = explode(',', $product['image_url']);
        foreach ($media_urls as $url) {
            $url = trim($url);
            if (!empty($url)) {
                $media_items[] = $url;
            }
        }
    }
    
    // If no media, use placeholder
    if (empty($media_items)) {
        $media_items = ['https://via.placeholder.com/600'];
    }
    
    $main_media = $media_items[0];
    $is_video = (strpos($main_media, '.mp4') !== false || strpos($main_media, '.webm') !== false || strpos($main_media, 'youtube.com') !== false);
    ?>
    
    <!-- Main Display Area -->
    <div class="main-media-container" id="mainMediaContainer">
        <?php if ($is_video): ?>
            <video class="main-media" id="mainMedia" controls style="width: 100%; border-radius: 12px;">
                <source src="<?php echo htmlspecialchars($main_media); ?>" type="video/mp4">
                Your browser does not support video playback.
            </video>
        <?php else: ?>
            <img class="main-media" id="mainMedia" 
                 src="<?php echo htmlspecialchars($main_media); ?>" 
                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                 style="width: 100%; border-radius: 12px;">
        <?php endif; ?>
    </div>
    
    <!-- Thumbnail Gallery (only show if multiple items) -->
    <?php if (count($media_items) > 1): ?>
    <div class="thumbnail-gallery" style="display: flex; gap: 10px; margin-top: 15px; overflow-x: auto; padding: 10px 0;">
        <?php foreach ($media_items as $index => $media_url): 
            $is_thumb_video = (strpos($media_url, '.mp4') !== false || strpos($media_url, '.webm') !== false);
        ?>
        <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>" 
             onclick="switchMedia('<?php echo htmlspecialchars($media_url); ?>', <?php echo $is_thumb_video ? 'true' : 'false'; ?>, this)"
             style="min-width: 80px; height: 80px; border: 3px solid <?php echo $index === 0 ? '#667eea' : '#e9ecef'; ?>; border-radius: 8px; cursor: pointer; overflow: hidden; position: relative; transition: all 0.3s;">
            
            <?php if ($is_thumb_video): ?>
                <!-- Video thumbnail with play icon -->
                <video style="width: 100%; height: 100%; object-fit: cover;" muted>
                    <source src="<?php echo htmlspecialchars($media_url); ?>" type="video/mp4">
                </video>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 2rem; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">▶</div>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($media_url); ?>" 
                     alt="Thumbnail <?php echo $index + 1; ?>" 
                     style="width: 100%; height: 100%; object-fit: cover;">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="trust-badges">
        <div class="trust-badge">
            <span>✓</span> Free Shipping
        </div>
        <div class="trust-badge">
            <span>↺</span> 30-Day Returns
        </div>
        <div class="trust-badge">
            <span>★</span> Warranty Included
        </div>
    </div>
</div>

<!-- Add JavaScript for Gallery -->
<script>
function switchMedia(mediaUrl, isVideo, thumbnailElement) {
    const container = document.getElementById('mainMediaContainer');
    
    // Update active thumbnail border
    document.querySelectorAll('.thumbnail-item').forEach(item => {
        item.style.borderColor = '#e9ecef';
        item.classList.remove('active');
    });
    thumbnailElement.style.borderColor = '#667eea';
    thumbnailElement.classList.add('active');
    
    // Replace main media
    if (isVideo) {
        container.innerHTML = `
            <video class="main-media" id="mainMedia" controls style="width: 100%; border-radius: 12px;" autoplay>
                <source src="${mediaUrl}" type="video/mp4">
                Your browser does not support video playback.
            </video>
        `;
    } else {
        container.innerHTML = `
            <img class="main-media" id="mainMedia" 
                 src="${mediaUrl}" 
                 alt="Product Image"
                 style="width: 100%; border-radius: 12px;">
        `;
    }
}

// Optional: Add hover effect to thumbnails
document.querySelectorAll('.thumbnail-item').forEach(item => {
    item.addEventListener('mouseenter', function() {
        if (!this.classList.contains('active')) {
            this.style.transform = 'scale(1.05)';
        }
    });
    item.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
});
</script>
```

---

## Option 2: Full Database Solution (Better for Long Term)

### Step 1: Create Product Media Table

```sql
CREATE TABLE product_media (
    media_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    media_url VARCHAR(500) NOT NULL,
    media_type ENUM('image', 'video') DEFAULT 'image',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- Example: Add images for product ID 1
INSERT INTO product_media (product_id, media_url, media_type, display_order) VALUES
(1, 'images/laptop1-front.jpg', 'image', 1),
(1, 'images/laptop1-side.jpg', 'image', 2),
(1, 'images/laptop1-keyboard.jpg', 'image', 3),
(1, 'https://youtube.com/embed/video_id', 'video', 4);
```

### Step 2: Modify PHP to Load from Database

In `product_details.php`, after fetching the product:

```php
// Fetch product media
$media_sql = "SELECT * FROM product_media WHERE product_id = ? ORDER BY display_order ASC";
$media_stmt = $conn->prepare($media_sql);
$media_stmt->bind_param("i", $product_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();

$media_items = [];
while ($media_row = $media_result->fetch_assoc()) {
    $media_items[] = $media_row;
}
$media_stmt->close();

// Fallback to main image_url if no media found
if (empty($media_items) && !empty($product['image_url'])) {
    $media_items[] = [
        'media_url' => $product['image_url'],
        'media_type' => 'image'
    ];
}
```

Then use the same gallery HTML from Option 1, but loop through `$media_items` from the database.

---

## Quick Test

To test without modifying the database, update one product manually:

```sql
UPDATE products 
SET image_url = 'https://via.placeholder.com/600/667eea/ffffff?text=Image+1,https://via.placeholder.com/600/764ba2/ffffff?text=Image+2,https://via.placeholder.com/600/10b981/ffffff?text=Image+3' 
WHERE product_id = 1;
```

Then view that product - you should see 3 clickable thumbnails!

---

## Features

✅ **Multiple images/videos per product**  
✅ **Clickable thumbnail gallery**  
✅ **Active border indicator**  
✅ **Supports images AND videos**  
✅ **YouTube embed support**  
✅ **Mobile-friendly horizontal scroll**  
✅ **Smooth transitions**  

---

## Summary

- **Option 1** (comma-separated): Quick, works with current schema
- **Option 2** (new table): Better for scalability, professional approach

I recommend Option 1 to get started, then migrate to Option 2 later if you need more features (like captions, alt text, etc.).
