-- =============================================
-- COMPLETE OZYDE DATABASE MERGE SCRIPT
-- Includes ALL tables from all 3 databases
-- =============================================

CREATE DATABASE IF NOT EXISTS ozyde;
USE ozyde;

-- =============================================
-- ALL TABLES FROM ALL DATABASES
-- =============================================

-- 1. activity_log (DB1 only)
CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `context` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
);

-- 2. addresses (All DBs)
CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'South Africa',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`address_id`),
  KEY `user_id` (`user_id`)
);

-- 3. admins (DB1 & DB3)
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('admin','superadmin') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
);

-- 4. audit_log (All DBs)
CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
);

-- 5. blog_posts (All DBs)
CREATE TABLE `blog_posts` (
  `post_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`post_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `author_id` (`author_id`)
);

-- 6. bookings (All DBs - using DB2 enhanced structure)
CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('booked','returned','cancelled') DEFAULT 'booked',
  `late_fee` decimal(10,2) DEFAULT 0.00,
  `damage_fee` decimal(10,2) DEFAULT 0.00,
  `penalty_status` enum('none','pending','paid') DEFAULT 'none',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `booking_ref` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`booking_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`)
);

-- 7. cart (All DBs - using DB2 enhanced structure)
CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` varchar(5) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `expires_at` datetime NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_id`),
  UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`)
);

-- 8. categories (All DBs)
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  PRIMARY KEY (`category_id`)
);

-- 9. custom_orders (All DBs)
CREATE TABLE `custom_orders` (
  `custom_order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `fabric_preference` varchar(255) DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','in_consultation','in_progress','completed','cancelled') DEFAULT 'pending',
  `bust` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `hips` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `sleeve_length` decimal(5,2) DEFAULT NULL,
  `shoulder_width` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`custom_order_id`),
  KEY `user_id` (`user_id`)
);

-- 10. delivery (All DBs)
CREATE TABLE `delivery` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `courier` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `delivery_status` enum('pending','shipped','delivered','failed') DEFAULT 'pending',
  `delivered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`delivery_id`),
  KEY `order_id` (`order_id`)
);

-- 11. dress_styles (DB1 only)
CREATE TABLE `dress_styles` (
  `style_id` int(11) NOT NULL AUTO_INCREMENT,
  `style_name` varchar(100) NOT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`style_id`)
);

-- 12. email_verifications (DB1 & DB3)
CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
);

-- 13. gallery (All DBs)
CREATE TABLE `gallery` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `custom_order_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `media_type` enum('image','video') DEFAULT 'image',
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `product_id` (`product_id`),
  KEY `custom_order_id` (`custom_order_id`)
);

-- 14. inventory (All DBs)
CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `location` varchar(100) DEFAULT 'Main Store',
  `available` tinyint(1) DEFAULT 1,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`inventory_id`),
  KEY `product_id` (`product_id`)
);

-- 15. messages (All DBs)
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `channel` enum('whatsapp','contact_form') DEFAULT 'whatsapp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `user_id` (`user_id`)
);

-- 16. notifications (All DBs)
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','custom_order','booking','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`)
);

-- 17. orders (All DBs)
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `delivery_method` enum('collection','delivery') DEFAULT 'collection',
  `order_status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `user_id` (`user_id`)
);

-- 18. order_items (All DBs)
CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
);

-- 19. payments (All DBs)
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `method` enum('pay_in_store','payfast','bank_transfer') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','successful','failed') DEFAULT 'pending',
  PRIMARY KEY (`payment_id`),
  KEY `order_id` (`order_id`)
);

-- 20. penalties (All DBs)
CREATE TABLE `penalties` (
  `penalty_id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `type` enum('late_return','damage') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`penalty_id`),
  KEY `booking_id` (`booking_id`)
);

-- 21. products (Enhanced from DB1 & DB2)
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `rental_price` decimal(10,2) DEFAULT NULL,
  `rental_duration` int(11) DEFAULT 3,
  `security_deposit` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `is_rental` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `category_id` (`category_id`)
);

-- 22. product_categories (DB1 only)
CREATE TABLE `product_categories` (
  `product_category_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`product_category_id`),
  KEY `product_id` (`product_id`),
  KEY `category_id` (`category_id`)
);

-- 23. product_images (DB1 only)
CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `thumb_filename` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `product_id` (`product_id`)
);

-- 24. product_measurements (All DBs)
CREATE TABLE `product_measurements` (
  `measurement_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `size_label` varchar(20) NOT NULL,
  `bust` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `hips` decimal(5,2) DEFAULT NULL,
  `length` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`measurement_id`),
  KEY `product_id` (`product_id`)
);

-- 25. product_sizes (All DBs)
CREATE TABLE `product_sizes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `size` varchar(10) NOT NULL,
  `stock` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
);

-- 26. product_styles (DB1 only)
CREATE TABLE `product_styles` (
  `product_id` int(11) NOT NULL,
  `style_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`product_id`,`style_id`),
  KEY `style_id` (`style_id`)
);

-- 27. profiles (DB1 & DB2)
CREATE TABLE `profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bust` int(11) DEFAULT NULL,
  `waist` int(11) DEFAULT NULL,
  `hip` int(11) DEFAULT NULL,
  `styles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`styles`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);

-- 28. reviews (All DBs)
CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`)
);

-- 29. settings (All DBs)
CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
);

-- 30. size_preferences (DB1 only)
CREATE TABLE `size_preferences` (
  `size_pref_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `size_label` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`size_pref_id`),
  KEY `user_id` (`user_id`)
);

-- 31. users (Enhanced from all DBs)
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `country_code` varchar(5) DEFAULT '+27',
  `role` enum('customer','admin','super_admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'South Africa',
  `google_id` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `twofa_enabled` tinyint(1) DEFAULT 0,
  `twofa_secret` varchar(255) DEFAULT NULL,
  `apple_id` varchar(255) DEFAULT NULL,
  `twofa_code` varchar(10) DEFAULT NULL,
  `twofa_expires` datetime DEFAULT NULL,
  `twofa_temp_token` varchar(64) DEFAULT NULL,
  `twofa_attempts` tinyint(4) DEFAULT 0,
  `verification_expires` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
);

-- 32. user_activities (DB1 only)
CREATE TABLE `user_activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `user_id` (`user_id`)
);

-- 33. user_custom_styles (DB1 only)
CREATE TABLE `user_custom_styles` (
  `custom_style_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `style_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`custom_style_id`),
  KEY `user_id` (`user_id`)
);

-- 34. user_measurements (DB1 only)
CREATE TABLE `user_measurements` (
  `measurement_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bust` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `hips` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`measurement_id`),
  KEY `user_id` (`user_id`)
);

-- 35. user_preferences (DB1 only)
CREATE TABLE `user_preferences` (
  `preference_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `preferred_sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_sizes`)),
  `preferred_colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_colors`)),
  `preferred_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_categories`)),
  `preferred_styles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_styles`)),
  `price_min` decimal(10,2) DEFAULT NULL,
  `price_max` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`preference_id`),
  KEY `user_id` (`user_id`)
);

-- 36. user_style_preferences (DB1 only)
CREATE TABLE `user_style_preferences` (
  `user_id` int(11) NOT NULL,
  `style_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`style_id`),
  KEY `style_id` (`style_id`)
);

-- 37. wishlist (All DBs)
CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`wishlist_id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`)
);

-- =============================================
-- INSERT ALL DATA (Resolving conflicts)
-- =============================================

-- Insert base data first
INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Evening Gowns'),
(2, 'Cocktail Dresses'),
(3, 'Formal Wear'),
(4, 'Wedding Guest'),
(5, 'Prom Dresses'),
(6, 'Casual Dresses'),
(7, 'Party Dresses'),
(8, 'Bridal Wear'),
(9, 'Mother of the Bride'),
(10, 'Graduation Dresses'),
(11, 'Matric Dance'),
(12, 'Summer Dresses');

INSERT INTO `dress_styles` (`style_id`, `style_name`, `is_custom`) VALUES
(1, 'Cocktail', 0),
(2, 'Evening Gown', 0),
(3, 'A-Line', 0),
(4, 'Bodycon', 0),
(5, 'Ball Gown', 0),
(6, 'Mermaid', 0),
(7, 'Sheath', 0),
(8, 'Empire Waist', 0),
(9, 'Off-Shoulder', 0),
(10, 'Vintage', 0),
(11, 'Boho', 0),
(12, 'Modern', 0),
(13, 'Classic', 0),
(14, 'Sexy', 0),
(15, 'Elegant', 0);

INSERT INTO `admins` (`id`, `username`, `password`, `name`, `email`, `role`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin@ozyde.com', 'admin'),
(2, 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'superadmin@ozyde.com', 'superadmin');

-- Insert users (starting from ID 100)
INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `phone`, `role`) VALUES
(100, 'Gundo', 'Tshavhungwe', 'gtshavhungwe@gmail.com', '$2y$10$LBDO7kEycJsy6J1M4HPLG.3214ixZSjKsj/e7bC.RIkHpGYbakWNm', '0606338947', 'admin'),
(101, 'Gundo', 'Tshavhungwe', 'its.gundow@gmail.com', '$2y$10$PbIjeXU5qXdO47bDSKVjyutb70Gjq9xLMSN0WUHWfsJvJmcVlmGdK', '606338948', 'customer'),
(102, 'Gundo', 'Tshavhungwe', 'grtshavhungwe@gmail.cpm', '$2y$10$ths1AJ.oWiA.4qQpZfK9W.NzYEBTGz5HkGo4h0wrntIADmEBaJhEy', '606338947', 'customer'),
(103, 'Gundo', 'Tshavhungwe', 'grt@gmail.cpm', '$2y$10$8g/uUHqOUcKSqKpUI.d98.doKfXBGT0b9hLlDIH18qQLiSW5HSF7i', '678902345', 'customer'),
(104, 'Mashudu', 'Tshavhungwe', 'mashudu.tshavhungwe@gmail.com', '$2y$10$vnW2pef5XFDZgQu4zujdG.tk9.J40Quq8zRw0MKWZO8UNGsLNEngS', '823940303', 'customer'),
(105, 'Muneiwa', 'Tshavhungwe', 'newi.vannesa@gmail.com', '$2y$10$mZxACeEGIOna/iu3H4eeuuTcHc8xL/5OGJTcRlkqw8Yf8nBQIeAXO', '723568942', 'customer'),
(106, 'Shafeeqah', 'Mmadi', 'shafee.mmadi@gmail.com', '$2y$10$YvPwwlq08Anq2WKihdwV3.hkc3SmCX3cDj6uqVRmlGb9xOeHfZmcm', '0640918839', 'customer'),
(107, 'Neo', 'Khoza', 'neo@gmail.com', '$2y$10$4orZOQszGgMrbjWcrY9Vqeh7/WocsFfDhfJ/RvVQjG88r/syQ9JEG', '078945621096', 'customer'),
(108, 'Tali', 'Davhana', 'talidavhana12@gmail.com', '$2y$10$j2BiHXNWn3iJfLJVcOXzku0bNc.ur7cJwmF5pffS4ojfkRyaBq9ii', '0662224349', 'customer'),
(109, 'Shafeeqah', 'Mmadi', 'shafiemmadi@outlook.com', '$2y$10$hBuKUcFx1cb1EcmzKbK2auueZ6GKq6AOhZhwEH2rVUh3gauNGuAa6', '0640918839', 'customer'),
(110, 'Imannah', 'Tote', 'immanah@gmail.com', '$2y$10$Vjt7lXHDe3afnz6dhE9XFOTPV.ncxG.Qcw54L0yO7oke8SPDHBbP.', '0745249625', 'customer'),
(111, 'Tim', 'Choshi', 'davhanatalifhani54@gmail.com', '$2y$10$g6dazcn/sisfQF70ZbB9M.mVRReQ8KPLvS4i/LsddzKuBNCrAPQfm', '0724041157', 'customer');

-- Insert products (starting from ID 200)
INSERT INTO `products` (`product_id`, `category_id`, `name`, `brand`, `description`, `size`, `color`, `price`, `rental_price`, `rental_duration`, `security_deposit`, `image`, `stock`, `is_rental`, `is_active`) VALUES
(200, 2, 'Seqence Dress', NULL, 'Sparkly', 'S:5,M:2,XS:3', 'Silver', 1600.00, 400.00, 3, 200.00, 'gallery/DSC07964-Edit.jpg', 10, 1, 1),
(201, 8, 'White Dress', '', 'Short White Wedding Dress ', 'S:1', 'White', 0.00, 15000.00, 3, 800.00, '', 1, 1, 1),
(202, 2, 'White', '', 'Short White Dress', 'S:3', 'White', 0.00, 2500.00, 3, 800.00, '', 3, 1, 1),
(203, 8, 'Sparke Sparkle', '', 'White Shiny', 'M:1', 'White', 0.00, 6500.00, 3, 800.00, '', 1, 1, 1),
(204, 8, 'White', '', 'White shiny', 'S:1', 'White', 0.00, 12000.00, 3, 800.00, '', 1, 1, 1),
(205, 1, 'Red Evening Gown', NULL, 'Elegant red gown for special occasions', 'M', 'Red', 1200.00, 300.00, 3, 200.00, 'gallery/red-gown.jpg', 5, 1, 1),
(206, 1, 'Black Cocktail Dress', NULL, 'Chic black dress for parties', 'S', 'Black', 850.00, 250.00, 3, 150.00, 'gallery/black-dress.jpg', 3, 1, 1),
(207, 12, 'Blue Summer Dress', NULL, 'Casual blue summer dress', 'L', 'Blue', 500.00, 150.00, 3, 100.00, 'gallery/blue-dress.jpg', 10, 1, 1),
(208, 12, 'Barbie', NULL, 'A beautiful dress.', 'S:2,M:3,L:4,XL:2', 'Black', 2500.00, 600.00, 3, 300.00, 'gallery/Barbie.png', 11, 1, 1),
(209, 1, 'Starlight', NULL, 'A dazzling dress...', 'S:5,M:2,XL:1', 'Blue', 2000.00, 500.00, 3, 250.00, 'gallery/Starlight.png', 8, 1, 1),
(210, 3, 'Serious', NULL, 'A formal look', 'XS:3,S:3,M:4', 'Black', 1500.00, 400.00, 3, 200.00, 'gallery/formal.png', 10, 1, 1),
(211, 12, 'Daisy', NULL, 'A beautiful floral dress', 'XS:3,S:2,M:4', 'Red', 600.00, 180.00, 3, 100.00, 'gallery/daisy.png', 9, 1, 1),
(212, 3, 'Rose', NULL, 'Rosyyyy', 'S:0', 'Red', 2500.00, 600.00, 3, 300.00, 'gallery/stella.jpg.png', 0, 1, 1),
(213, 1, 'Red', NULL, 'redddddd', '', 'red', 50000.00, 12000.00, 3, 2500.00, 'gallery/formal.png', 0, 1, 1);

-- Insert product categories
INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES
(200, 2),
(201, 8),
(202, 8),
(203, 8),
(204, 8);

-- Insert product styles
INSERT INTO `product_styles` (`product_id`, `style_id`) VALUES
(201, 3),
(202, 3),
(203, 13),
(204, 13);

-- Insert bookings
INSERT INTO `bookings` (`booking_id`, `product_id`, `user_id`, `start_date`, `end_date`, `status`, `late_fee`, `damage_fee`, `penalty_status`, `created_at`, `booking_ref`, `total_amount`) VALUES
(1, 210, 109, '2025-10-09', '2025-10-11', 'booked', 0.00, 0.00, 'none', '2025-10-09 21:15:36', NULL, NULL),
(2, 209, 109, '2025-10-12', '2025-10-14', 'booked', 0.00, 0.00, 'none', '2025-10-12 21:58:32', 'OZ547046', 3170.00),
(3, 209, 109, '2025-12-01', '2025-12-03', 'booked', 0.00, 0.00, 'none', '2025-10-12 22:10:30', 'OZ790893', 3170.00),
(4, 210, 109, '2025-11-18', '2025-11-20', 'booked', 0.00, 0.00, 'none', '2025-10-13 13:48:00', 'OZ967962', 2670.00),
(5, 210, 109, '2025-10-21', '2025-10-23', 'booked', 0.00, 0.00, 'none', '2025-10-14 23:36:16', 'OZ486665', 5170.00),
(6, 212, 109, '2025-12-29', '2025-12-31', 'booked', 0.00, 0.00, 'none', '2025-10-14 23:36:16', 'OZ486665', 5170.00),
(7, 211, 109, '2025-10-17', '2025-10-19', 'booked', 0.00, 0.00, 'none', '2025-10-14 23:45:14', 'OZ175719', 1770.00),
(8, 208, 109, '2025-10-16', '2025-10-18', 'booked', 0.00, 0.00, 'none', '2025-10-14 23:47:56', 'OZ689130', 6270.00),
(9, 209, 109, '2025-10-17', '2025-10-19', 'booked', 0.00, 0.00, 'none', '2025-10-14 23:47:56', 'OZ689130', 6270.00),
(10, 211, 109, '2025-10-28', '2025-10-30', 'booked', 0.00, 0.00, 'none', '2025-10-14 23:47:56', 'OZ689130', 6270.00),
(11, 206, 109, '2025-10-15', '2025-10-17', 'booked', 0.00, 0.00, 'none', '2025-10-16 09:50:40', 'OZ619355', 1670.00);

-- Insert cart items
INSERT INTO `cart` (`user_id`, `product_id`, `size`, `start_date`, `end_date`, `expires_at`, `quantity`) VALUES
(107, 212, 'S:0', '2025-10-21', '2025-10-23', '2025-10-21 23:59:59', 1),
(107, 208, 'M:3', '2025-12-23', '2025-12-25', '2025-12-23 23:59:59', 1),
(107, 211, 'M:4', '2025-12-13', '2025-12-15', '2025-12-13 23:59:59', 1),
(108, 210, 'M:4', '2025-12-25', '2025-12-27', '2025-12-25 23:59:59', 1),
(108, 208, 'XL:2', '2025-12-01', '2025-12-03', '2025-12-01 23:59:59', 1),
(108, 209, 'M:2', '2025-10-31', '2025-11-02', '2025-10-31 23:59:59', 1),
(109, 208, 'L:4', '2025-11-18', '2025-11-20', '2025-11-18 23:59:59', 1),
(109, 211, 'M:4', '2025-10-21', '2025-10-23', '2025-10-21 23:59:59', 1),
(109, 206, 'S', '2025-10-16', '2025-10-18', '2025-10-16 23:59:59', 1),
(109, 209, 'M:2', '2025-11-26', '2025-11-28', '2025-11-26 23:59:59', 1);

-- Insert user preferences and measurements
INSERT INTO `user_measurements` (`user_id`, `bust`, `waist`, `hips`) VALUES
(101, 60.00, 31.00, 70.00),
(103, 68.00, 32.00, 40.00);

INSERT INTO `user_style_preferences` (`user_id`, `style_id`) VALUES
(101, 3),
(101, 5),
(103, 1),
(103, 4),
(103, 5),
(103, 14),
(104, 2),
(104, 5);

INSERT INTO `wishlist` (`user_id`, `product_id`) VALUES
(101, 200),
(107, 208),
(107, 209),
(107, 210),
(107, 211),
(109, 211),
(109, 210);

-- Insert activity log
INSERT INTO `activity_log` (`log_id`, `admin_id`, `action`, `context`, `created_at`) VALUES
(1, 1, 'created_category', '{\"category_id\":2,\"category_name\":\"Evening Wear\"}', '2025-10-16 10:46:48'),
(2, 1, 'product_updated', '{\"product_id\":2,\"name\":\"Black dress\"}', '2025-10-16 11:03:33'),
(3, 1, 'product_updated', '{\"product_id\":3,\"name\":\"Dress\"}', '2025-10-16 11:07:18');

-- Insert custom orders
INSERT INTO `custom_orders` (`custom_order_id`, `user_id`, `description`, `fabric_preference`, `budget`, `status`, `image_url`) VALUES
(1, 106, 'beautifullllllllllllllllll', 'Dress', NULL, 'pending', 'uploads/custom_orders/1759889268_Screenshot 2025-10-06 152848.png');

-- Insert orders
INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `payment_status`, `delivery_method`, `order_status`, `created_at`) VALUES
(100, 100, 1500.00, 'paid', 'collection', 'completed', '2025-10-02 11:08:55');

-- Insert profiles
INSERT INTO `profiles` (`id`, `first_name`, `last_name`, `email`, `phone`, `address`, `bust`, `waist`, `hip`, `styles`) VALUES
(1, 'Lerato', 'Mokoena', 'lerato.m@example.com', '0823456789', '123 Fashion Street, Johannesburg', 90, 70, 95, '[\"Casual\",\"Elegant\",\"Modern\"]');

-- Insert size preferences
INSERT INTO `size_preferences` (`size_pref_id`, `user_id`, `size_label`) VALUES
(1, 101, 'XS'),
(2, 101, 'S'),
(3, 101, 'M'),
(4, 103, 'M'),
(5, 103, 'L'),
(6, 104, 'S'),
(7, 104, 'XXL');

-- =============================================
-- ADD ALL FOREIGN KEY CONSTRAINTS
-- =============================================

-- Addresses
ALTER TABLE `addresses` ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Audit Log
ALTER TABLE `audit_log` ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

-- Blog Posts
ALTER TABLE `blog_posts` ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`);

-- Bookings
ALTER TABLE `bookings` ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
ALTER TABLE `bookings` ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Cart
ALTER TABLE `cart` ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `cart` ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- Custom Orders
ALTER TABLE `custom_orders` ADD CONSTRAINT `custom_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Delivery
ALTER TABLE `delivery` ADD CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

-- Email Verifications
ALTER TABLE `email_verifications` ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Gallery
ALTER TABLE `gallery` ADD CONSTRAINT `gallery_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
ALTER TABLE `gallery` ADD CONSTRAINT `gallery_ibfk_2` FOREIGN KEY (`custom_order_id`) REFERENCES `custom_orders` (`custom_order_id`) ON DELETE CASCADE;

-- Inventory
ALTER TABLE `inventory` ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- Messages
ALTER TABLE `messages` ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

-- Notifications
ALTER TABLE `notifications` ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Orders
ALTER TABLE `orders` ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Order Items
ALTER TABLE `order_items` ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;
ALTER TABLE `order_items` ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- Payments
ALTER TABLE `payments` ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

-- Penalties
ALTER TABLE `penalties` ADD CONSTRAINT `penalties_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

-- Products
ALTER TABLE `products` ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

-- Product Categories
ALTER TABLE `product_categories` ADD CONSTRAINT `product_categories_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
ALTER TABLE `product_categories` ADD CONSTRAINT `product_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

-- Product Images
ALTER TABLE `product_images` ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- Product Measurements
ALTER TABLE `product_measurements` ADD CONSTRAINT `product_measurements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- Product Sizes
ALTER TABLE `product_sizes` ADD CONSTRAINT `product_sizes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- Product Styles
ALTER TABLE `product_styles` ADD CONSTRAINT `product_styles_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
ALTER TABLE `product_styles` ADD CONSTRAINT `product_styles_ibfk_2` FOREIGN KEY (`style_id`) REFERENCES `dress_styles` (`style_id`) ON DELETE CASCADE;

-- Reviews
ALTER TABLE `reviews` ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `reviews` ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- Size Preferences
ALTER TABLE `size_preferences` ADD CONSTRAINT `size_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- User Activities
ALTER TABLE `user_activities` ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- User Custom Styles
ALTER TABLE `user_custom_styles` ADD CONSTRAINT `user_custom_styles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- User Measurements
ALTER TABLE `user_measurements` ADD CONSTRAINT `user_measurements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- User Preferences
ALTER TABLE `user_preferences` ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- User Style Preferences
ALTER TABLE `user_style_preferences` ADD CONSTRAINT `user_style_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `user_style_preferences` ADD CONSTRAINT `user_style_preferences_ibfk_2` FOREIGN KEY (`style_id`) REFERENCES `dress_styles` (`style_id`) ON DELETE CASCADE;

-- Wishlist
ALTER TABLE `wishlist` ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `wishlist` ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

-- =============================================
-- FINAL VERIFICATION
-- =============================================

SELECT 'COMPLETE DATABASE MERGE FINISHED SUCCESSFULLY!' as message;

SELECT 
  (SELECT COUNT(*) FROM users) as total_users,
  (SELECT COUNT(*) FROM products) as total_products, 
  (SELECT COUNT(*) FROM categories) as total_categories,
  (SELECT COUNT(*) FROM bookings) as total_bookings,
  (SELECT COUNT(*) FROM cart) as total_cart_items,
  (SELECT COUNT(*) FROM custom_orders) as total_custom_orders,
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'ozyde_merged') as total_tables;