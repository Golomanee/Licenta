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
  `profileimage` LONGBLOB NULL,
  `specialty` ENUM('cardiolog', 'radiolog', 'gastroenterolog', 'pneumolog', 'medicina_laborator') NULL,
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

-- Comments table (with support for nested replies)
CREATE TABLE IF NOT EXISTS `Comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `parent_comment_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `EduPosts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `User`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_comment_id`) REFERENCES `Comments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `Likes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `type` ENUM('like', 'dislike') DEFAULT 'like',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_post_user_like` (`post_id`, `user_id`),
  FOREIGN KEY (`post_id`) REFERENCES `EduPosts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `User`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create indexes for better performance
CREATE INDEX `idx_user_email` ON `User`(`email`);
CREATE INDEX `idx_user_role` ON `User`(`role`);
CREATE INDEX `idx_userdetails_userid` ON `UserDetails`(`userid`);
CREATE INDEX `idx_eduposts_creator` ON `EduPosts`(`creator_id`);
CREATE INDEX `idx_eduposts_created` ON `EduPosts`(`created_at`);
-- Posts table removed; using `EduPosts` instead.
CREATE INDEX `idx_comments_post` ON `Comments`(`post_id`);
CREATE INDEX `idx_comments_user` ON `Comments`(`user_id`);
CREATE INDEX `idx_comments_parent` ON `Comments`(`parent_comment_id`);
CREATE INDEX `idx_comments_created` ON `Comments`(`created_at`);
CREATE INDEX `idx_likes_post` ON `Likes`(`post_id`);
CREATE INDEX `idx_likes_user` ON `Likes`(`user_id`);

-- Programul medicilor (zilele și orele în care sunt disponibili)
CREATE TABLE IF NOT EXISTS `DoctorSchedule` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doctor_id` INT NOT NULL,
    `day_of_week` TINYINT NOT NULL COMMENT '1=Luni, 2=Marti, 3=Miercuri, 4=Joi, 5=Vineri, 6=Sambata, 7=Duminica',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `slot_duration` INT DEFAULT 20 COMMENT 'Durata în minute a fiecărui slot',
    FOREIGN KEY (`doctor_id`) REFERENCES `User`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Programările pacienților
CREATE TABLE IF NOT EXISTS `Appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `specialty_key` VARCHAR(50) NOT NULL,
    `service_key` VARCHAR(50) NOT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `status` ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `User`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `User`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_doctor_slot` (`doctor_id`, `appointment_date`, `appointment_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX `idx_schedule_doctor` ON `DoctorSchedule`(`doctor_id`);
CREATE INDEX `idx_schedule_day` ON `DoctorSchedule`(`day_of_week`);
CREATE INDEX `idx_appointments_date` ON `Appointments`(`appointment_date`);
CREATE INDEX `idx_appointments_doctor` ON `Appointments`(`doctor_id`);
CREATE INDEX `idx_appointments_patient` ON `Appointments`(`patient_id`);
