-- Safe migration to add shipping address fields to orders table
-- This script checks if columns exist before adding them

-- Add shipping_name if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'shipping_name';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `orders` ADD COLUMN `shipping_name` VARCHAR(255) NULL AFTER `order_date`', 
    'SELECT "Column shipping_name already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add shipping_address if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'shipping_address';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `orders` ADD COLUMN `shipping_address` VARCHAR(500) NULL AFTER `shipping_name`', 
    'SELECT "Column shipping_address already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add shipping_city if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'shipping_city';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `orders` ADD COLUMN `shipping_city` VARCHAR(100) NULL AFTER `shipping_address`', 
    'SELECT "Column shipping_city already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add shipping_state if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'shipping_state';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `orders` ADD COLUMN `shipping_state` VARCHAR(100) NULL AFTER `shipping_city`', 
    'SELECT "Column shipping_state already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add shipping_zip if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'shipping_zip';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `orders` ADD COLUMN `shipping_zip` VARCHAR(20) NULL AFTER `shipping_state`', 
    'SELECT "Column shipping_zip already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add shipping_country if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'shipping_country';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `orders` ADD COLUMN `shipping_country` VARCHAR(100) NULL AFTER `shipping_zip`', 
    'SELECT "Column shipping_country already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add shipping_phone if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'shipping_phone';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `orders` ADD COLUMN `shipping_phone` VARCHAR(20) NULL AFTER `shipping_country`', 
    'SELECT "Column shipping_phone already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the changes
SELECT 'Migration completed! Here are the current columns in the orders table:' AS status;
SHOW COLUMNS FROM `orders`;
