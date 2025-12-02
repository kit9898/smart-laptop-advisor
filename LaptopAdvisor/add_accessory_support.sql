-- =====================================================
-- ACCESSORY SUPPORT MIGRATION
-- Adds support for accessories (mouse, keyboard, etc.)
-- =====================================================

-- Step 1: Add product_category column
ALTER TABLE `products` 
ADD COLUMN `product_category` ENUM('laptop', 'mouse', 'keyboard', 'headset', 'monitor', 'bag', 'mousepad', 'webcam', 'other') 
DEFAULT 'laptop' 
AFTER `product_name`;

-- Step 2: Add related_to_category column for accessories
ALTER TABLE `products` 
ADD COLUMN `related_to_category` VARCHAR(100) 
COMMENT 'For accessories: Gaming, Business, Creative, Student, General Use' 
AFTER `product_category`;

-- Step 3: Add stock_quantity column if not exists
ALTER TABLE `products` 
ADD COLUMN `stock_quantity` INT(11) DEFAULT 50 
AFTER `description`;

-- Step 4: Update existing products to be laptops
UPDATE `products` SET `product_category` = 'laptop' WHERE `product_category` IS NULL OR `product_category` = 'laptop';

-- Step 5: Insert Gaming Accessories
INSERT INTO `products` 
(`product_name`, `product_category`, `related_to_category`, `brand`, `price`, `image_url`, `description`, `primary_use_case`, `stock_quantity`, `cpu`, `gpu`, `ram_gb`, `storage_gb`, `display_size`) 
VALUES
('Razer DeathAdder V3 Pro', 'mouse', 'Gaming', 'Razer', 129.99, 'images/accessories/gaming_mouse.png', 'Wireless gaming mouse with 30,000 DPI sensor and ergonomic design.', 'Gaming', 45, NULL, NULL, NULL, NULL, NULL),
('Logitech G Pro X', 'keyboard', 'Gaming', 'Logitech', 149.99, 'images/accessories/gaming_keyboard.png', 'Mechanical gaming keyboard with hot-swappable switches and RGB lighting.', 'Gaming', 30, NULL, NULL, NULL, NULL, NULL),
('SteelSeries Arctis 7', 'headset', 'Gaming', 'SteelSeries', 149.99, 'images/accessories/gaming_headset.png', 'Wireless gaming headset with lossless 2.4GHz connection and 24-hour battery.', 'Gaming', 25, NULL, NULL, NULL, NULL, NULL),
('Razer Gigantus V2', 'mousepad', 'Gaming', 'Razer', 29.99, 'images/accessories/gaming_mousepad.png', 'Large gaming mousepad with textured micro-weave cloth surface.', 'Gaming', 60, NULL, NULL, NULL, NULL, NULL),
('ASUS ROG Strix 27"', 'monitor', 'Gaming', 'ASUS', 399.99, 'images/accessories/gaming_monitor.png', '27-inch 1440p gaming monitor with 165Hz refresh rate and 1ms response time.', 'Gaming', 15, NULL, NULL, NULL, NULL, 27.00);

-- Step 6: Insert Business/Professional Accessories
INSERT INTO `products` 
(`product_name`, `product_category`, `related_to_category`, `brand`, `price`, `image_url`, `description`, `primary_use_case`, `stock_quantity`, `cpu`, `gpu`, `ram_gb`, `storage_gb`, `display_size`) 
VALUES
('Logitech MX Master 3S', 'mouse', 'Business', 'Logitech', 99.99, 'images/accessories/business_mouse.png', 'Wireless mouse with MagSpeed scroll wheel and multi-device connectivity.', 'Business', 50, NULL, NULL, NULL, NULL, NULL),
('Keychron K8 Pro', 'keyboard', 'Business', 'Keychron', 109.99, 'images/accessories/business_keyboard.png', 'Wireless mechanical keyboard perfect for productivity and coding.', 'Business', 35, NULL, NULL, NULL, NULL, NULL),
('Jabra Evolve2 65', 'headset', 'Business', 'Jabra', 179.99, 'images/accessories/business_headset.png', 'Professional wireless headset with active noise cancellation for calls.', 'Business', 40, NULL, NULL, NULL, NULL, NULL),
('Dell P2723DE', 'monitor', 'Business', 'Dell', 449.99, 'images/accessories/business_monitor.png', '27-inch QHD monitor with USB-C hub and ergonomic stand.', 'Business', 20, NULL, NULL, NULL, NULL, 27.00),
('Targus Corporate Traveler', 'bag', 'Business', 'Targus', 59.99, 'images/accessories/business_bag.png', 'Professional laptop backpack with padded compartment up to 15.6 inches.', 'Business', 55, NULL, NULL, NULL, NULL, NULL);

-- Step 7: Insert Creative Accessories
INSERT INTO `products` 
(`product_name`, `product_category`, `related_to_category`, `brand`, `price`, `image_url`, `description`, `primary_use_case`, `stock_quantity`, `cpu`, `gpu`, `ram_gb`, `storage_gb`, `display_size`) 
VALUES
('Logitech MX Vertical', 'mouse', 'Creative', 'Logitech', 89.99, 'images/accessories/creative_mouse.png', 'Ergonomic vertical mouse ideal for long editing sessions.', 'Creative', 40, NULL, NULL, NULL, NULL, NULL),
('Adobe Premiere Pro Keyboard', 'keyboard', 'Creative', 'Editors Keys', 149.99, 'images/accessories/creative_keyboard.png', 'Backlit keyboard with Adobe shortcuts for video editing.', 'Creative', 25, NULL, NULL, NULL, NULL, NULL),
('Audio-Technica ATH-M50x', 'headset', 'Creative', 'Audio-Technica', 169.99, 'images/accessories/creative_headset.png', 'Professional studio headphones for accurate audio monitoring.', 'Creative', 30, NULL, NULL, NULL, NULL, NULL),
('BenQ PD3220U', 'monitor', 'Creative', 'BenQ', 999.99, 'images/accessories/creative_monitor.png', '32-inch 4K monitor with 99% Adobe RGB and calibration support.', 'Creative', 10, NULL, NULL, NULL, NULL, 32.00),
('Logitech StreamCam', 'webcam', 'Creative', 'Logitech', 149.99, 'images/accessories/creative_webcam.png', '1080p webcam with smart auto-framing for content creation.', 'Creative', 35, NULL, NULL, NULL, NULL, NULL);

-- Step 8: Insert Student Accessories
INSERT INTO `products` 
(`product_name`, `product_category`, `related_to_category`, `brand`, `price`, `image_url`, `description`, `primary_use_case`, `stock_quantity`, `cpu`, `gpu`, `ram_gb`, `storage_gb`, `display_size`) 
VALUES
('Logitech M330 Silent', 'mouse', 'Student', 'Logitech', 24.99, 'images/accessories/student_mouse.png', 'Affordable wireless mouse with silent clicks for quiet study sessions.', 'Student', 70, NULL, NULL, NULL, NULL, NULL),
('Logitech K380', 'keyboard', 'Student', 'Logitech', 39.99, 'images/accessories/student_keyboard.png', 'Compact wireless keyboard compatible with multiple devices.', 'Student', 60, NULL, NULL, NULL, NULL, NULL),
('JBL Tune 510BT', 'headset', 'Student', 'JBL', 49.99, 'images/accessories/student_headset.png', 'Budget-friendly wireless headphones with 40-hour battery life.', 'Student', 80, NULL, NULL, NULL, NULL, NULL),
('Amazon Basics Backpack', 'bag', 'Student', 'Amazon Basics', 29.99, 'images/accessories/student_bag.png', 'Affordable laptop backpack with multiple compartments.', 'Student', 100, NULL, NULL, NULL, NULL, NULL);

-- Step 9: Insert General/Universal Accessories
INSERT INTO `products` 
(`product_name`, `product_category`, `related_to_category`, `brand`, `price`, `image_url`, `description`, `primary_use_case`, `stock_quantity`, `cpu`, `gpu`, `ram_gb`, `storage_gb`, `display_size`) 
VALUES
('Logitech M185', 'mouse', 'General Use', 'Logitech', 14.99, 'images/accessories/general_mouse.png', 'Simple and reliable wireless mouse for everyday use.', 'General Use', 90, NULL, NULL, NULL, NULL, NULL),
('Logitech K270', 'keyboard', 'General Use', 'Logitech', 24.99, 'images/accessories/general_keyboard.png', 'Wireless keyboard with long battery life for home or office.', 'General Use', 85, NULL, NULL, NULL, NULL, NULL),
('Anker Soundcore Q20', 'headset', 'General Use', 'Anker', 59.99, 'images/accessories/general_headset.png', 'Over-ear headphones with active noise cancellation.', 'General Use', 65, NULL, NULL, NULL, NULL, NULL);

-- Verification Query
SELECT 
    product_category,
    related_to_category,
    COUNT(*) as count
FROM products
GROUP BY product_category, related_to_category
ORDER BY product_category, related_to_category;
