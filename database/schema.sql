-- MariaDB Database Schema for Spital Application
-- Database: spital

CREATE DATABASE IF NOT EXISTS spital CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE spital;

-- User table
CREATE TABLE IF NOT EXISTS `User` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL,
  `email_verified` TINYINT(1) DEFAULT 0,
  `verification_token` VARCHAR(100) NULL,
  `token_expires` DATETIME NULL,
  CONSTRAINT `chk_role` CHECK (`role` IN ('patient', 'doctor', 'admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- UserDetails table
CREATE TABLE IF NOT EXISTS `UserDetails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `userid` INT NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `birthday` DATE NULL,
  `phone` VARCHAR(20) NULL,
  `country` VARCHAR(100) NULL,
  `city` VARCHAR(100) NULL,
  `height` INT NULL,
  `weight` INT NULL,
  `profileimage` VARCHAR(255) NULL,
  FOREIGN KEY (`userid`) REFERENCES `User`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- EduPosts table
CREATE TABLE IF NOT EXISTS `EduPosts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `creator_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `text` TEXT NOT NULL,
  `image` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`creator_id`) REFERENCES `User`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create indexes for better performance
CREATE INDEX `idx_user_email` ON `User`(`email`);
CREATE INDEX `idx_user_role` ON `User`(`role`);
CREATE INDEX `idx_userdetails_userid` ON `UserDetails`(`userid`);
CREATE INDEX `idx_eduposts_creator` ON `EduPosts`(`creator_id`);
CREATE INDEX `idx_eduposts_created` ON `EduPosts`(`created_at`);
