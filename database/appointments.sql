-- Tabele pentru sistemul de programări
-- Rulează acest fișier pentru a adăuga tabelele necesare

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

-- Indexuri pentru performanță
CREATE INDEX `idx_schedule_doctor` ON `DoctorSchedule`(`doctor_id`);
CREATE INDEX `idx_schedule_day` ON `DoctorSchedule`(`day_of_week`);
CREATE INDEX `idx_appointments_date` ON `Appointments`(`appointment_date`);
CREATE INDEX `idx_appointments_doctor` ON `Appointments`(`doctor_id`);
CREATE INDEX `idx_appointments_patient` ON `Appointments`(`patient_id`);
