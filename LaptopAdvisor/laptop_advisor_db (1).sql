-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2025 at 07:30 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `laptop_advisor_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `chat_history`
--

CREATE TABLE `chat_history` (
  `chat_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sender` enum('user','bot','system') NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_history`
--

INSERT INTO `chat_history` (`chat_id`, `session_id`, `user_id`, `sender`, `message`, `timestamp`) VALUES
(1, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'user', 'gaming', '2025-09-27 20:10:48'),
(2, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'bot', 'For gaming, I recommend looking at models with dedicated GPUs like the Nvidia RTX series. Check our \"Gaming\" category!', '2025-09-27 20:10:48'),
(3, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'user', 'i want you to recoemded', '2025-09-27 20:11:01'),
(4, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'bot', 'I\'m not sure how to answer that. You can ask me to \'recommend a laptop\' or ask about \'gaming\' or \'price\'. You can also ask to speak to a \'human agent\'.', '2025-09-27 20:11:01'),
(5, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'user', 'price', '2025-09-27 20:11:09'),
(6, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'bot', 'Our laptops range widely in price. You can use the filters on the Products page to find one in your budget!', '2025-09-27 20:11:09'),
(7, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'user', 'human agent\'', '2025-09-27 20:11:20'),
(8, 'lkiq5gjh8hfhiee5q9jvb95mma', 1, 'bot', 'Please wait while I connect you to a live agent... (This is a simulation. In a real system, this would trigger a notification.)', '2025-09-27 20:11:20'),
(9, 'uji8s4vd2rk1nj67bb4i9gb2dh', 4, 'user', 'll', '2025-11-23 13:59:54'),
(10, 'uji8s4vd2rk1nj67bb4i9gb2dh', 4, 'bot', 'I\'m not sure how to answer that. You can ask me to \'recommend a laptop\' or ask about \'gaming\' or \'price\'. You can also ask to speak to a \'human agent\'.', '2025-11-23 13:59:54'),
(11, 'uji8s4vd2rk1nj67bb4i9gb2dh', 4, 'user', 'recommend a laptop', '2025-11-23 14:00:03'),
(12, 'uji8s4vd2rk1nj67bb4i9gb2dh', 4, 'bot', 'Based on your request, you might be interested in: **MacBook Pro 16**, **Creator Z16**, **Spectre x360**. You can find more on our Products page!', '2025-11-23 14:00:03');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `coupon_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`coupon_id`, `code`, `discount_type`, `discount_value`, `expiry_date`, `is_active`) VALUES
(1, 'SAVE10', 'percentage', 10.00, '2025-12-31', 1),
(2, '50OFF', 'fixed', 50.00, NULL, 1),
(3, 'EXPIRED', 'percentage', 20.00, '2020-01-01', 0);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` varchar(50) DEFAULT 'Processing',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `order_status`, `order_date`) VALUES
(1, 1, 649.00, 'Pending', '2025-09-27 19:17:53'),
(2, 1, 584.10, 'Pending', '2025-09-27 19:36:06'),
(3, 1, 649.00, 'Pending', '2025-09-27 19:36:33'),
(4, 1, 649.00, 'Pending', '2025-09-27 19:37:59'),
(5, 1, 584.10, 'Pending', '2025-09-27 19:38:17'),
(6, 1, 799.00, 'Pending', '2025-09-27 19:38:26'),
(7, 1, 719.10, 'Pending', '2025-09-27 19:41:51'),
(8, 1, 2898.00, 'Pending', '2025-09-27 19:42:34'),
(9, 2, 649.00, 'Pending', '2025-09-28 06:09:52'),
(10, 1, 649.00, 'Pending', '2025-09-28 07:23:33'),
(11, 4, 649.00, 'Pending', '2025-11-23 14:03:26'),
(12, 4, 1450.00, 'Pending', '2025-11-24 05:46:43');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price_at_purchase`) VALUES
(1, 1, 4, 1, 649.00),
(2, 2, 4, 1, 649.00),
(3, 3, 4, 1, 649.00),
(4, 4, 4, 1, 649.00),
(5, 5, 4, 1, 649.00),
(6, 6, 8, 1, 799.00),
(7, 7, 8, 1, 799.00),
(8, 8, 4, 1, 649.00),
(9, 8, 8, 1, 799.00),
(10, 8, 6, 1, 1450.00),
(11, 9, 4, 1, 649.00),
(12, 10, 4, 1, 649.00),
(13, 11, 4, 1, 649.00),
(14, 12, 6, 1, 1450.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cpu` varchar(100) DEFAULT NULL,
  `gpu` varchar(100) DEFAULT NULL,
  `ram_gb` int(11) DEFAULT NULL,
  `storage_gb` int(11) DEFAULT NULL,
  `storage_type` varchar(50) DEFAULT 'SSD',
  `display_size` decimal(4,2) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `primary_use_case` varchar(100) DEFAULT NULL COMMENT 'e.g., Gaming, Business, General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `brand`, `price`, `cpu`, `gpu`, `ram_gb`, `storage_gb`, `storage_type`, `display_size`, `image_url`, `description`, `primary_use_case`) VALUES
(1, 'Creator Z16', 'MSI', 2499.99, 'Intel Core i7', 'NVIDIA RTX 3060', 16, 1024, 'SSD', 16.00, 'images/laptop1.png', 'A powerful laptop for creative professionals.', 'Creative'),
(2, 'ZenBook Duo', 'Asus', 1999.00, 'Intel Core i9', 'NVIDIA RTX 3070', 32, 1024, 'SSD', 14.00, 'images/laptop2.png', 'Dual-screen laptop for ultimate productivity.', 'Business'),
(3, 'Blade 15', 'Razer', 2999.50, 'Intel Core i7', 'NVIDIA RTX 3080', 16, 1024, 'SSD', 15.60, 'images/laptop3.png', 'The ultimate gaming laptop with a sleek design.', 'Gaming'),
(4, 'IdeaPad Slim 3', 'Lenovo', 649.00, 'AMD Ryzen 5', 'Integrated Radeon', 8, 512, 'SSD', 15.60, 'images/laptop4.jpg', 'Great for students and everyday tasks.', 'Student'),
(5, 'MacBook Pro 16', 'Apple', 3499.00, 'Apple M2 Pro', 'Integrated', 16, 1024, 'SSD', 16.20, 'images/laptop5.png', 'Powerful performance for professionals and creatives.', 'Creative'),
(6, 'Spectre x360', 'HP', 1450.00, 'Intel Core i7', 'Intel Iris Xe', 16, 512, 'SSD', 13.50, 'images/laptop6.png', 'A versatile 2-in-1 laptop with a premium feel.', 'Business'),
(7, 'ROG Strix G15', 'Asus', 1899.99, 'AMD Ryzen 9', 'NVIDIA RTX 3070', 16, 1024, 'SSD', 15.60, 'images/laptop7.png', 'High-refresh-rate gaming powerhouse.', 'Gaming'),
(8, 'Pavilion 15', 'HP', 799.00, 'Intel Core i5', 'Intel Iris Xe', 12, 512, 'SSD', 15.60, 'images/laptop8.png', 'A reliable all-rounder for work and entertainment.', 'General');

-- --------------------------------------------------------

--
-- Table structure for table `recommendation_ratings`
--

CREATE TABLE `recommendation_ratings` (
  `rating_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL COMMENT '1 for like, -1 for dislike',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recommendation_ratings`
--

INSERT INTO `recommendation_ratings` (`rating_id`, `user_id`, `product_id`, `rating`, `created_at`) VALUES
(1, 1, 4, -1, '2025-09-27 19:56:58'),
(4, 1, 2, 1, '2025-09-27 19:57:13'),
(5, 1, 6, 1, '2025-09-27 20:02:40'),
(6, 2, 4, 1, '2025-09-28 06:10:39'),
(7, 4, 7, 1, '2025-11-24 05:57:47'),
(8, 4, 3, -1, '2025-11-24 06:13:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `profile_image_url` varchar(255) DEFAULT 'uploads/default.png',
  `primary_use_case` varchar(100) DEFAULT 'General Use',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `status`, `profile_image_url`, `primary_use_case`, `created_at`) VALUES
(1, 'Heng', 'chansh-am22@student.tarc.edu.my', '$2y$10$3Sm1lxjx6YodMxqCXW0riOY6Plen2z.pV.GXvYL.KH3a.3dmFPRMy', 'pending', 'uploads/68d83a959bb49-Screenshot 2024-05-03 151943.png', 'Business', '2025-09-27 17:21:42'),
(2, 'xuan', 'chan123@GMAIL.COM', '$2y$10$UqWAFCaxniqS5NKJDYgndOWtGvfVlv3rwWfdJVo.f0mbUMkYPmY16', 'pending', 'uploads/default.png', 'Student', '2025-09-28 06:08:01'),
(3, 'xuan', 'abc@gmail.com', '$2y$10$WbU7EDXaKg0EcfW.B.spWOqvSqrLro1z9NCpw9DW.Z8xMhe04PRMm', 'pending', 'uploads/default.png', 'Student', '2025-11-11 14:17:17'),
(4, 'xuan', '3@gmail.com', '$2y$10$JVtPUBxorDJMV9vEKWgHy.Rtkie2FZmZKGltH0b4GIv3539e2Qy7.', 'pending', 'uploads/6923fae4f024b-Gohwenkai.png', 'Gaming', '2025-11-23 13:56:49'),
(5, 'Test User', 'test@example.com', '$2y$10$PmTK7PUCzfLcHBczmvYO4eCJH0fHPpebVEkwny8CuPfB/zb.dHqm2', 'pending', 'uploads/default.png', 'General Use', '2025-11-24 06:05:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chat_history`
--
ALTER TABLE `chat_history`
  ADD PRIMARY KEY (`chat_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`coupon_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `recommendation_ratings`
--
ALTER TABLE `recommendation_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD UNIQUE KEY `user_product_rating` (`user_id`,`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_history`
--
ALTER TABLE `chat_history`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `coupon_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `recommendation_ratings`
--
ALTER TABLE `recommendation_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
