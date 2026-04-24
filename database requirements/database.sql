-- Apexcare Health Record Database
-- Database healthrecord_db
-- Created for Apexcare Hospital Management System

-- Drop database if exists (use with caution in production!)
DROP DATABASE IF EXISTS healthrecord_db;
CREATE DATABASE healthrecord_db;
USE healthrecord_db;

-- =====================================================
-- Table 1 users (Base user table for all roles)
-- =====================================================
CREATE TABLE users (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    national_id VARCHAR(20) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(10) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('receptionist', 'doctor', 'patient') NOT NULL,
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_national_id (national_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 2 admins (Separate admin table for security)
-- =====================================================
CREATE TABLE admins (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    failed_attempts INT(11) DEFAULT 0,
    lock_until DATETIME DEFAULT NULL,
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 3 admin_activity_logs
-- =====================================================
CREATE TABLE admin_activity_logs (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    admin_id INT(11) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NOT NULL,
    ip_address VARCHAR(45),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 4 doctor_profiles
-- =====================================================
CREATE TABLE doctor_profiles (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT(11) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    qualifications TEXT,
    experience_years INT(3),
    consultation_fee DECIMAL(10,2),
    status ENUM('working', 'onleave', 'absent') DEFAULT 'working',
    bio TEXT,
    
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_id (doctor_id),
    INDEX idx_specialization (specialization),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 5 doctor_schedules
-- =====================================================
CREATE TABLE doctor_schedules (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT(11) NOT NULL,
    available_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    is_booked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_date (doctor_id, available_date),
    INDEX idx_available_slots (available_date, is_booked),
    UNIQUE KEY unique_slot (doctor_id, available_date, slot_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 6 appointments
-- =====================================================
CREATE TABLE appointments (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) NOT NULL,
    schedule_id INT(11) NOT NULL,
    status ENUM('assigned', 'completed', 'cancelled') DEFAULT 'assigned',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES doctor_schedules(id) ON DELETE CASCADE,
    
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 7 medical_records (Main table)
-- =====================================================
CREATE TABLE medical_records (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) NOT NULL,
    symptoms TEXT,
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    appointment_id INT(11),
    follow_up_date DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_created (created_at),
    FULLTEXT idx_search (symptoms, diagnosis, treatment, notes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 8 articles
-- =====================================================
CREATE TABLE articles (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    target_gender ENUM('male', 'female', 'all') DEFAULT 'all',
    min_age INT(11) DEFAULT 0,
    max_age INT(11) DEFAULT 120,
    status ENUM('published', 'draft') DEFAULT 'published',
    created_by INT(11),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_target (target_gender, min_age, max_age),
    FULLTEXT idx_content (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 9 messages (Contact form messages)
-- =====================================================
CREATE TABLE messages (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11) NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 10 uploads (Patient file uploads)
-- =====================================================
CREATE TABLE uploads (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    patient_id INT(11) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT(11),
    file_type VARCHAR(50),
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    medical_record_id INT(11),
    
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    
    INDEX idx_patient (patient_id),
    INDEX idx_uploaded (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Create triggers for data integrity
-- =====================================================

DELIMITER $$

-- Trigger to update doctor_schedule when appointment is created
CREATE TRIGGER after_appointment_insert
AFTER INSERT ON appointments
FOR EACH ROW
BEGIN
    UPDATE doctor_schedules 
    SET is_booked = 1 
    WHERE id = NEW.schedule_id;
END$$

-- Trigger to update doctor_schedule when appointment is cancelled
CREATE TRIGGER after_appointment_cancel
AFTER UPDATE ON appointments
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        UPDATE doctor_schedules 
        SET is_booked = 0 
        WHERE id = NEW.schedule_id;
    END IF;
END$$

DELIMITER ;