-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 16, 2025 at 11:07 PM
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
-- Database: `ozyde`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `context` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`log_id`, `admin_id`, `action`, `context`, `created_at`) VALUES
(1, 1, 'created_category', '{\"category_id\":2,\"category_name\":\"Evening Wear\"}', '2025-10-16 10:46:48'),
(2, 1, 'product_updated', '{\"product_id\":2,\"name\":\"Black dress\"}', '2025-10-16 11:03:33'),
(3, 1, 'product_updated', '{\"product_id\":3,\"name\":\"Dress\"}', '2025-10-16 11:07:18');

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'South Africa',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('admin','superadmin') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `name`, `email`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin@ozyde.com', 'admin', '2025-10-09 19:58:07'),
(2, 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'superadmin@ozyde.com', 'superadmin', '2025-10-09 19:58:07');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `post_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
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
  `total_amount` decimal(10,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `product_id`, `user_id`, `start_date`, `end_date`, `status`, `late_fee`, `damage_fee`, `penalty_status`, `created_at`, `booking_ref`, `total_amount`) VALUES
(1, 3, 1, '2025-10-16', '2025-10-18', 'booked', 0.00, 0.00, 'none', '2025-10-16 13:43:18', NULL, NULL),
(2, 3, 28, '2025-10-22', '2025-10-24', 'booked', 0.00, 0.00, 'none', '2025-10-16 14:06:06', 'OZ684111', 1670),
(3, 3, 29, '2025-11-07', '2025-11-09', 'booked', 0.00, 0.00, 'none', '2025-10-16 14:26:55', 'OZ780979', 1670);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_id`, `size`, `quantity`, `added_at`, `start_date`, `end_date`) VALUES
(2, 1, 3, 'S', 1, '2025-10-16 13:41:02', '2025-10-24', '2025-10-26');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Evening Wear');

-- --------------------------------------------------------

--
-- Table structure for table `custom_orders`
--

CREATE TABLE `custom_orders` (
  `custom_order_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery`
--

CREATE TABLE `delivery` (
  `delivery_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `courier` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `delivery_status` enum('pending','shipped','delivered','failed') DEFAULT 'pending',
  `delivered_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dress_styles`
--

CREATE TABLE `dress_styles` (
  `style_id` int(11) NOT NULL,
  `style_name` varchar(100) NOT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dress_styles`
--

INSERT INTO `dress_styles` (`style_id`, `style_name`, `is_custom`, `created_at`) VALUES
(1, 'Cocktail', 0, '2025-10-11 09:50:27'),
(2, 'Evening Gown', 0, '2025-10-11 09:50:27'),
(3, 'A-Line', 0, '2025-10-11 09:50:27'),
(4, 'Bodycon', 0, '2025-10-11 09:50:27'),
(5, 'Ball Gown', 0, '2025-10-11 09:50:27'),
(6, 'Mermaid', 0, '2025-10-11 09:50:27'),
(7, 'Sheath', 0, '2025-10-11 09:50:27'),
(8, 'Empire Waist', 0, '2025-10-11 09:50:27'),
(9, 'Off-Shoulder', 0, '2025-10-11 09:50:27'),
(10, 'Vintage', 0, '2025-10-11 09:50:27'),
(11, 'Boho', 0, '2025-10-11 09:50:27'),
(12, 'Modern', 0, '2025-10-11 09:50:27'),
(13, 'Classic', 0, '2025-10-11 09:50:27'),
(14, 'Sexy', 0, '2025-10-11 09:50:27'),
(15, 'Elegant', 0, '2025-10-11 09:50:27'),
(1, 'Cocktail', 0, '2025-10-11 07:50:27'),
(2, 'Evening Gown', 0, '2025-10-11 07:50:27'),
(3, 'A-Line', 0, '2025-10-11 07:50:27'),
(4, 'Bodycon', 0, '2025-10-11 07:50:27'),
(5, 'Ball Gown', 0, '2025-10-11 07:50:27'),
(6, 'Mermaid', 0, '2025-10-11 07:50:27'),
(7, 'Sheath', 0, '2025-10-11 07:50:27'),
(8, 'Empire Waist', 0, '2025-10-11 07:50:27'),
(9, 'Off-Shoulder', 0, '2025-10-11 07:50:27'),
(10, 'Vintage', 0, '2025-10-11 07:50:27'),
(11, 'Boho', 0, '2025-10-11 07:50:27'),
(12, 'Modern', 0, '2025-10-11 07:50:27'),
(13, 'Classic', 0, '2025-10-11 07:50:27'),
(14, 'Sexy', 0, '2025-10-11 07:50:27'),
(15, 'Elegant', 0, '2025-10-11 07:50:27'),
(1, 'Cocktail', 0, '2025-10-11 07:50:27'),
(2, 'Evening Gown', 0, '2025-10-11 07:50:27'),
(3, 'A-Line', 0, '2025-10-11 07:50:27'),
(4, 'Bodycon', 0, '2025-10-11 07:50:27'),
(5, 'Ball Gown', 0, '2025-10-11 07:50:27'),
(6, 'Mermaid', 0, '2025-10-11 07:50:27'),
(7, 'Sheath', 0, '2025-10-11 07:50:27'),
(8, 'Empire Waist', 0, '2025-10-11 07:50:27'),
(9, 'Off-Shoulder', 0, '2025-10-11 07:50:27'),
(10, 'Vintage', 0, '2025-10-11 07:50:27'),
(11, 'Boho', 0, '2025-10-11 07:50:27'),
(12, 'Modern', 0, '2025-10-11 07:50:27'),
(13, 'Classic', 0, '2025-10-11 07:50:27'),
(14, 'Sexy', 0, '2025-10-11 07:50:27'),
(15, 'Elegant', 0, '2025-10-11 07:50:27');

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `custom_order_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `location` varchar(100) DEFAULT 'Main Store',
  `available` tinyint(1) DEFAULT 1,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `channel` enum('whatsapp','contact_form') DEFAULT 'whatsapp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','custom_order','booking','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `delivery_method` enum('collection','delivery') DEFAULT 'collection',
  `order_status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `payment_status`, `delivery_method`, `order_status`, `created_at`) VALUES
(101, 1, 1500.00, 'paid', 'collection', 'completed', '2025-10-02 11:08:55');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `method` enum('pay_in_store','payfast','bank_transfer') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','successful','failed') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penalties`
--

CREATE TABLE `penalties` (
  `penalty_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `type` enum('late_return','damage') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `sku` varchar(120) DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `sku`, `category_id`, `name`, `brand`, `description`, `size`, `color`, `price`, `rental_price`, `rental_duration`, `security_deposit`, `image`, `video_url`, `stock`, `is_rental`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '', 1, 'Black dress', NULL, 'Elegent evening dress for any occation', NULL, 'Black', 300.50, NULL, 3, NULL, NULL, NULL, 1, 1, 1, '2025-10-16 10:51:29', '2025-10-16 11:58:08'),
(3, '', 1, 'Dress', NULL, 'Cute dress', NULL, 'Nude', 500.00, NULL, 3, NULL, NULL, NULL, 4, 1, 1, '2025-10-16 11:07:18', '2025-10-16 11:58:08');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `product_category_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`product_category_id`, `product_id`, `category_id`, `created_at`) VALUES
(1, 1, 2, '2025-10-14 11:49:54'),
(2, 6, 8, '2025-10-14 11:49:54'),
(3, 8, 8, '2025-10-14 18:07:21'),
(4, 9, 8, '2025-10-14 18:10:21'),
(9, 14, 8, '2025-10-15 11:28:01'),
(12, 17, 8, '2025-10-15 16:09:37'),
(1, 1, 2, '2025-10-14 09:49:54'),
(2, 6, 8, '2025-10-14 09:49:54'),
(3, 8, 8, '2025-10-14 16:07:21'),
(4, 9, 8, '2025-10-14 16:10:21'),
(9, 14, 8, '2025-10-15 09:28:01'),
(12, 17, 8, '2025-10-15 14:09:37'),
(1, 1, 2, '2025-10-14 09:49:54'),
(2, 6, 8, '2025-10-14 09:49:54'),
(3, 8, 8, '2025-10-14 16:07:21'),
(4, 9, 8, '2025-10-14 16:10:21'),
(9, 14, 8, '2025-10-15 09:28:01'),
(12, 17, 8, '2025-10-15 14:09:37');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `thumb_filename` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `filename`, `thumb_filename`, `is_primary`, `created_at`) VALUES
(2, 3, '526905721_18070453187474223_6576334884311368812_n-dba3e44f0026.jpg', 'thumb-526905721_18070453187474223_6576334884311368812_n-dba3e44f0026.jpg', 1, '2025-10-16 11:07:18');

-- --------------------------------------------------------

--
-- Table structure for table `product_measurements`
--

CREATE TABLE `product_measurements` (
  `measurement_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size_label` varchar(20) NOT NULL,
  `bust` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `hips` decimal(5,2) DEFAULT NULL,
  `length` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_sizes`
--

CREATE TABLE `product_sizes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` varchar(10) NOT NULL,
  `stock` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_sizes`
--

INSERT INTO `product_sizes` (`id`, `product_id`, `size`, `stock`) VALUES
(1, 1, 'm', 1),
(3, 3, 'XL', 8);

-- --------------------------------------------------------

--
-- Table structure for table `product_styles`
--

CREATE TABLE `product_styles` (
  `product_id` int(11) NOT NULL,
  `style_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_styles`
--

INSERT INTO `product_styles` (`product_id`, `style_id`, `created_at`) VALUES
(6, 3, '2025-10-13 11:32:44'),
(7, 3, '2025-10-13 13:06:56'),
(8, 3, '2025-10-14 18:07:21'),
(14, 13, '2025-10-15 11:28:01'),
(17, 13, '2025-10-15 16:09:37'),
(6, 3, '2025-10-13 09:32:44'),
(7, 3, '2025-10-13 11:06:56'),
(8, 3, '2025-10-14 16:07:21'),
(14, 13, '2025-10-15 09:28:01'),
(17, 13, '2025-10-15 14:09:37');

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bust` int(11) DEFAULT NULL,
  `waist` int(11) DEFAULT NULL,
  `hip` int(11) DEFAULT NULL,
  `styles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`styles`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`id`, `first_name`, `last_name`, `email`, `phone`, `address`, `bust`, `waist`, `hip`, `styles`, `created_at`) VALUES
(1, 'Lerato', 'Mokoena', 'lerato.m@example.com', '0823456789', '123 Fashion Street, Johannesburg', 90, 70, 95, '[\"Casual\",\"Elegant\",\"Modern\"]', '2025-10-01 17:01:34');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `size_preferences`
--

CREATE TABLE `size_preferences` (
  `size_pref_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `size_label` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `size_preferences`
--

INSERT INTO `size_preferences` (`size_pref_id`, `user_id`, `size_label`, `created_at`) VALUES
(1, 1, 'XS', '2025-10-14 13:47:34'),
(2, 1, 'S', '2025-10-14 13:47:34'),
(3, 1, 'M', '2025-10-14 13:47:34'),
(6, 3, 'M', '2025-10-14 13:47:34'),
(7, 3, 'L', '2025-10-14 13:47:34'),
(10, 2, 'XS', '2025-10-15 01:47:00'),
(11, 2, 'S', '2025-10-15 01:47:00'),
(12, 2, 'M', '2025-10-15 01:47:00'),
(13, 4, 'XS', '2025-10-16 04:00:56'),
(14, 4, 'S', '2025-10-16 04:00:56'),
(15, 4, 'M', '2025-10-16 04:00:56'),
(1, 1, 'XS', '2025-10-14 11:47:34'),
(2, 1, 'S', '2025-10-14 11:47:34'),
(3, 1, 'M', '2025-10-14 11:47:34'),
(6, 3, 'M', '2025-10-14 11:47:34'),
(7, 3, 'L', '2025-10-14 11:47:34'),
(10, 2, 'XS', '2025-10-14 23:47:00'),
(11, 2, 'S', '2025-10-14 23:47:00'),
(12, 2, 'M', '2025-10-14 23:47:00'),
(13, 4, 'XS', '2025-10-16 02:00:56'),
(14, 4, 'S', '2025-10-16 02:00:56'),
(15, 4, 'M', '2025-10-16 02:00:56'),
(1, 1, 'XS', '2025-10-14 11:47:34'),
(2, 1, 'S', '2025-10-14 11:47:34'),
(3, 1, 'M', '2025-10-14 11:47:34'),
(6, 3, 'M', '2025-10-14 11:47:34'),
(7, 3, 'L', '2025-10-14 11:47:34'),
(10, 2, 'XS', '2025-10-14 23:47:00'),
(11, 2, 'S', '2025-10-14 23:47:00'),
(12, 2, 'M', '2025-10-14 23:47:00'),
(13, 4, 'XS', '2025-10-16 02:00:56'),
(14, 4, 'S', '2025-10-16 02:00:56'),
(15, 4, 'M', '2025-10-16 02:00:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('customer','admin','super_admin') DEFAULT 'customer',
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `verification_token` varchar(100) DEFAULT NULL,
  `twofa_enabled` tinyint(1) DEFAULT 0,
  `twofa_secret` varchar(255) DEFAULT NULL,
  `apple_id` varchar(255) DEFAULT NULL,
  `twofa_code` varchar(10) DEFAULT NULL,
  `twofa_expires` datetime DEFAULT NULL,
  `twofa_temp_token` varchar(64) DEFAULT NULL,
  `twofa_attempts` tinyint(4) DEFAULT 0,
  `verification_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `google_id`, `is_verified`, `phone`, `role`, `email_verified`, `created_at`, `updated_at`, `last_login`, `verification_token`, `twofa_enabled`, `twofa_secret`, `apple_id`, `twofa_code`, `twofa_expires`, `twofa_temp_token`, `twofa_attempts`, `verification_expires`) VALUES
(1, 'Talifhani', 'Davhana', 'talidavhana12@gmail.com', '$2y$10$mfMvAmoEB71PjtLwCxRz.OBRBkKCrdeW19WwcP630g0rHpGalgipa', '105660417731107523809', 0, '0662224349', 'customer', 1, '2025-10-04 15:13:39', '2025-10-16 09:59:47', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(15, 'Super', 'Admin', 'superadmin@ozyde.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 0, NULL, 'super_admin', 1, '2025-10-08 02:01:28', '2025-10-16 09:59:47', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(16, 'Regular', 'Admin', 'admin@ozyde.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 0, NULL, 'admin', 1, '2025-10-08 02:01:28', '2025-10-16 09:59:47', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(28, 'Annabelle', 'Smiths', 'annnebellle731@gmail.com', '$2y$10$cXEVQ5GUnUhr9sTeQCHoo.xxLCFU6hChcmtaP3i9s/7a/REMpXjJe', NULL, 0, '662224349', 'customer', 0, '2025-10-16 14:02:23', '2025-10-16 14:03:56', NULL, '0aaeb58322f38069f70b0232efcf05ea', 0, NULL, NULL, NULL, NULL, NULL, 0, '2025-10-17 16:02:23'),
(29, 'Phillip', 'Choshi', 'spchoshi@gmail.com', '$2y$10$RAKtQr6YdOAb7EmFCKJFUOmVvEO3b5F4lXVWozG/Euh72j3wxOvNW', NULL, 0, '724041157', 'customer', 0, '2025-10-16 14:19:07', '2025-10-16 14:20:47', NULL, '0d0540142fcb6199b364cbebafaccc65', 0, NULL, NULL, NULL, NULL, NULL, 0, '2025-10-17 16:19:07');

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_custom_styles`
--

CREATE TABLE `user_custom_styles` (
  `custom_style_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `style_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_measurements`
--

CREATE TABLE `user_measurements` (
  `measurement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bust` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `hips` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_measurements`
--

INSERT INTO `user_measurements` (`measurement_id`, `user_id`, `bust`, `waist`, `hips`, `created_at`, `updated_at`) VALUES
(1, 2, 60.00, 31.00, 70.00, '2025-10-15 00:51:19', '2025-10-15 01:46:10'),
(2, 4, 68.00, 32.00, 40.00, '2025-10-16 04:00:48', '2025-10-16 04:00:48'),
(1, 2, 60.00, 31.00, 70.00, '2025-10-14 22:51:19', '2025-10-14 23:46:10'),
(2, 4, 68.00, 32.00, 40.00, '2025-10-16 02:00:48', '2025-10-16 02:00:48'),
(1, 2, 60.00, 31.00, 70.00, '2025-10-14 22:51:19', '2025-10-14 23:46:10'),
(2, 4, 68.00, 32.00, 40.00, '2025-10-16 02:00:48', '2025-10-16 02:00:48');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preferred_sizes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_sizes`)),
  `preferred_colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_colors`)),
  `preferred_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_categories`)),
  `preferred_styles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_styles`)),
  `price_min` decimal(10,2) DEFAULT NULL,
  `price_max` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_style_preferences`
--

CREATE TABLE `user_style_preferences` (
  `user_id` int(11) NOT NULL,
  `style_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_style_preferences`
--

INSERT INTO `user_style_preferences` (`user_id`, `style_id`, `created_at`) VALUES
(2, 3, '2025-10-15 01:49:25'),
(2, 5, '2025-10-15 01:49:28'),
(4, 1, '2025-10-16 04:01:10'),
(4, 4, '2025-10-16 04:01:01'),
(4, 5, '2025-10-16 04:01:06'),
(4, 14, '2025-10-16 04:01:25'),
(2, 3, '2025-10-14 23:49:25'),
(2, 5, '2025-10-14 23:49:28'),
(4, 1, '2025-10-16 02:01:10'),
(4, 4, '2025-10-16 02:01:01'),
(4, 5, '2025-10-16 02:01:06'),
(4, 14, '2025-10-16 02:01:25');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`post_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `custom_orders`
--
ALTER TABLE `custom_orders`
  ADD PRIMARY KEY (`custom_order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `delivery`
--
ALTER TABLE `delivery`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `custom_order_id` (`custom_order_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_notifications_created` (`created_at`);

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
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `penalties`
--
ALTER TABLE `penalties`
  ADD PRIMARY KEY (`penalty_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_measurements`
--
ALTER TABLE `product_measurements`
  ADD PRIMARY KEY (`measurement_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `custom_orders`
--
ALTER TABLE `custom_orders`
  MODIFY `custom_order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery`
--
ALTER TABLE `delivery`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penalties`
--
ALTER TABLE `penalties`
  MODIFY `penalty_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_measurements`
--
ALTER TABLE `product_measurements`
  MODIFY `measurement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_sizes`
--
ALTER TABLE `product_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_orders`
--
ALTER TABLE `custom_orders`
  ADD CONSTRAINT `custom_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery`
--
ALTER TABLE `delivery`
  ADD CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `gallery`
--
ALTER TABLE `gallery`
  ADD CONSTRAINT `gallery_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gallery_ibfk_2` FOREIGN KEY (`custom_order_id`) REFERENCES `custom_orders` (`custom_order_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `penalties`
--
ALTER TABLE `penalties`
  ADD CONSTRAINT `penalties_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_measurements`
--
ALTER TABLE `product_measurements`
  ADD CONSTRAINT `product_measurements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD CONSTRAINT `product_sizes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
