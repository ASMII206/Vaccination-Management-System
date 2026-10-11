-- =========================================================
-- VaxCare - complete database for a fresh install
-- Import with phpMyAdmin (or: mysql -u root < vaccination_db.sql)
-- Admin login (set in includes/config.php):
--   admin@vaxcare.com / admin12345
-- =========================================================

CREATE DATABASE IF NOT EXISTS `vaccination_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vaccination_db`;

CREATE TABLE IF NOT EXISTS `parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `address` varchar(255) NOT NULL,
  `location` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hospital_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `children` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_child_parent` (`parent_id`),
  CONSTRAINT `fk_child_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vaccines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `age_in_days` int(11) NOT NULL DEFAULT 0,
  `availability` enum('Available','Unavailable') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vaccine_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `child_vaccinations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_child_vaccine` (`child_id`,`vaccine_id`),
  KEY `idx_cv_vaccine` (`vaccine_id`),
  CONSTRAINT `fk_cv_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cv_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_vaccination_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `time_slot` varchar(40) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `vaccination_status` enum('Pending','Vaccinated','Not Vaccinated') NOT NULL DEFAULT 'Pending',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_appt_cv` (`child_vaccination_id`),
  KEY `idx_appt_parent` (`parent_id`),
  KEY `idx_appt_hospital` (`hospital_id`),
  CONSTRAINT `fk_appt_cv` FOREIGN KEY (`child_vaccination_id`) REFERENCES `child_vaccinations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per child + vaccine, with the state used by the parent, hospital and admin portals
CREATE OR REPLACE VIEW `v_child_schedule` AS
SELECT cv.id AS id,
       cv.child_id AS child_id,
       c.name AS child_name,
       c.parent_id AS parent_id,
       cv.vaccine_id AS vaccine_id,
       v.name AS vaccine_name,
       cv.due_date AS due_date,
       v.availability AS availability,
       EXISTS (SELECT 1 FROM appointments a
               WHERE a.child_vaccination_id = cv.id AND a.vaccination_status = 'Vaccinated') AS is_vaccinated,
       (SELECT a.status FROM appointments a
        WHERE a.child_vaccination_id = cv.id AND a.status IN ('Pending','Approved')
        ORDER BY a.id DESC LIMIT 1) AS booking_status
FROM child_vaccinations cv
JOIN children c ON c.id = cv.child_id
JOIN vaccines v ON v.id = cv.vaccine_id;

-- Vaccines (the due date of each dose = date of birth + age_in_days)
INSERT IGNORE INTO `vaccines` (`id`, `name`, `description`, `age_in_days`, `availability`) VALUES
(1, 'BCG', 'Tuberculosis - at birth', 0, 'Available'),
(2, 'Hepatitis B (Birth dose)', 'Hepatitis B - at birth', 0, 'Available'),
(3, 'OPV 0', 'Oral Polio - at birth', 0, 'Available'),
(4, 'Pentavalent 1 + OPV 1', 'DPT, Hep B, Hib + Polio - 6 weeks', 42, 'Available'),
(5, 'Pentavalent 2 + OPV 2', 'DPT, Hep B, Hib + Polio - 10 weeks', 70, 'Available'),
(6, 'Pentavalent 3 + OPV 3', 'DPT, Hep B, Hib + Polio - 14 weeks', 98, 'Available'),
(7, 'Measles 1', 'Measles - 9 months', 270, 'Available'),
(8, 'Measles 2 / MMR', 'Measles, Mumps, Rubella - 15 months', 450, 'Available');

INSERT IGNORE INTO `hospitals` (`id`, `name`, `address`, `location`, `phone`) VALUES
(1, 'City General Hospital', 'Main Boulevard, Block 5', 'Karachi', '021-1111111'),
(2, 'Mother & Child Care Centre', 'Shahrah-e-Faisal', 'Karachi', '021-2222222'),
(3, 'Green Valley Clinic', 'Satellite Town', 'Lahore', '042-3333333');
