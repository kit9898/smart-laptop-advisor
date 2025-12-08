-- Create review_media table to store images and videos for reviews
CREATE TABLE IF NOT EXISTS `review_media` (
  `media_id` INT(11) NOT NULL AUTO_INCREMENT,
  `review_id` INT(11) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `media_type` ENUM('image', 'video') NOT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`media_id`),
  KEY `idx_review_id` (`review_id`),
  CONSTRAINT `fk_media_review` FOREIGN KEY (`review_id`) REFERENCES `product_reviews` (`review_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add admin response columns to product_reviews table if they don't exist
-- We use a stored procedure to check if the column exists before adding it to avoid errors
DROP PROCEDURE IF EXISTS upgrade_product_reviews;

DELIMITER //
CREATE PROCEDURE upgrade_product_reviews()
BEGIN
    -- Add admin_response column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'product_reviews' 
        AND COLUMN_NAME = 'admin_response'
    ) THEN
        ALTER TABLE `product_reviews` ADD COLUMN `admin_response` TEXT NULL DEFAULT NULL;
    END IF;

    -- Add admin_response_date column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'product_reviews' 
        AND COLUMN_NAME = 'admin_response_date'
    ) THEN
        ALTER TABLE `product_reviews` ADD COLUMN `admin_response_date` DATETIME NULL DEFAULT NULL;
    END IF;

    -- Add admin_id column to track which admin responded
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'product_reviews' 
        AND COLUMN_NAME = 'admin_id'
    ) THEN
        ALTER TABLE `product_reviews` ADD COLUMN `admin_id` INT(11) NULL DEFAULT NULL;
    END IF;
END//
DELIMITER ;

-- Execute the stored procedure
CALL upgrade_product_reviews();

-- Drop the stored procedure after use
DROP PROCEDURE IF EXISTS upgrade_product_reviews;
