CREATE DATABASE IF NOT EXISTS dr_kiran_appointments;
USE dr_kiran_appointments;

-- Create appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_date DATE NOT NULL,
    appointment_time VARCHAR(20) NOT NULL,
    patient_first_name VARCHAR(100) NOT NULL,
    patient_last_name VARCHAR(100) NOT NULL,
    patient_preferred_name VARCHAR(100),
    dob DATE NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    preferred_specialty VARCHAR(100),
    reason_for_appointment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('confirmed', 'cancelled', 'completed') DEFAULT 'confirmed',
    UNIQUE KEY unique_appointment (appointment_date, appointment_time)
);

-- Create admin_users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password is 'admin123')
INSERT INTO admin_users (username, password) 
VALUES ('admin', '$2y$10$XMM2PGIlrv0adwJ3fx0v0.xFT9T6FTSrA7Kx.xr4kBYAD2X1m0hRa')
ON DUPLICATE KEY UPDATE username = username;

-- Create a table for time slots configuration
CREATE TABLE IF NOT EXISTS time_slots_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_type ENUM('morning', 'evening') NOT NULL,
    start_time INT NOT NULL COMMENT 'Minutes from midnight',
    end_time INT NOT NULL COMMENT 'Minutes from midnight',
    interval_minutes INT NOT NULL DEFAULT 15,
    max_appointments INT NOT NULL DEFAULT 1 COMMENT 'Number of appointments allowed per slot'
);

-- Clear existing time slots and insert default configurations
DELETE FROM time_slots_config;
INSERT INTO time_slots_config (slot_type, start_time, end_time, interval_minutes, max_appointments) VALUES
('morning', 540, 780, 15, 1), -- 9:00 AM to 1:00 PM
('evening', 1080, 1200, 15, 1); -- 6:00 PM to 8:00 PM 