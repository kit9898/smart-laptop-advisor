-- Migration to add default shipping address fields to users table
-- This allows users to save their address in profile and auto-fill at checkout

ALTER TABLE `users` 
ADD COLUMN `default_shipping_name` VARCHAR(255) NULL AFTER `primary_use_case`,
ADD COLUMN `default_shipping_address` VARCHAR(500) NULL AFTER `default_shipping_name`,
ADD COLUMN `default_shipping_city` VARCHAR(100) NULL AFTER `default_shipping_address`,
ADD COLUMN `default_shipping_state` VARCHAR(100) NULL AFTER `default_shipping_city`,
ADD COLUMN `default_shipping_zip` VARCHAR(20) NULL AFTER `default_shipping_state`,
ADD COLUMN `default_shipping_country` VARCHAR(100) NULL AFTER `default_shipping_zip`,
ADD COLUMN `default_shipping_phone` VARCHAR(20) NULL AFTER `default_shipping_country`;
