-- ============================================================================
-- SMART PARKING SLOT MANAGEMENT SYSTEM - COMPLETE DATABASE SETUP
-- ============================================================================
-- This file contains everything needed to set up the database from scratch
-- Run this entire file in phpMyAdmin to create a fresh database
-- ============================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS parking_system;
USE parking_system;

-- ============================================================================
-- TABLE: users
-- Description: Stores user account information
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: admin
-- Description: Stores admin account information
-- ============================================================================
CREATE TABLE IF NOT EXISTS admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: vehicles
-- Description: Stores vehicle information for each user
-- Features: 
--   - vehicle_type: Bike, Car, Truck, SUV, Other
--   - vehicle_number: Unique globally (no duplicates allowed)
-- ============================================================================
CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type ENUM('Bike', 'Car', 'Truck', 'SUV', 'Other') DEFAULT 'Car',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_vehicle_number (vehicle_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: parking_areas
-- Description: Stores parking area/lot information
-- ============================================================================
CREATE TABLE IF NOT EXISTS parking_areas (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL,
    location VARCHAR(200) NOT NULL,
    hourly_rate DECIMAL(10, 2) DEFAULT 50.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_area_name (area_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: parking_slots
-- Description: Stores individual parking slots within areas
-- Status: Available, Occupied, Maintenance
-- ============================================================================
CREATE TABLE IF NOT EXISTS parking_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    slot_number VARCHAR(20) NOT NULL,
    status ENUM('Available', 'Occupied', 'Maintenance') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES parking_areas(area_id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (area_id, slot_number),
    INDEX idx_area_id (area_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: bookings
-- Description: Stores parking slot bookings
-- Status: Active, Completed, Cancelled
-- ============================================================================
CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    slot_id INT NOT NULL,
    entry_time DATETIME NOT NULL,
    exit_time DATETIME NULL,
    status ENUM('Active', 'Completed', 'Cancelled') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES parking_slots(slot_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_slot_id (slot_id),
    INDEX idx_status (status),
    INDEX idx_entry_time (entry_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: wallet
-- Description: Stores user wallet balance
-- Each user gets one wallet with initial balance
-- ============================================================================
CREATE TABLE IF NOT EXISTS wallet (
    wallet_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- TABLE: payments
-- Description: Stores payment transaction records
-- Status: SUCCESS, FAILED, PENDING
-- ============================================================================
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('SUCCESS', 'FAILED', 'PENDING') DEFAULT 'PENDING',
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- INSERT DEFAULT DATA
-- ============================================================================

-- Insert default admin account
-- Username: admin
-- Password: admin123
-- NOTE: If login fails, run setup_admin.php to generate a fresh password hash
INSERT IGNORE INTO admin (username, password_hash) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample parking areas
INSERT IGNORE INTO parking_areas (area_id, area_name, location, hourly_rate) VALUES
(1, 'Main Parking Lot', 'Building A - Ground Floor', 50.00),
(2, 'VIP Parking', 'Building B - Basement', 75.00),
(3, 'Visitor Parking', 'Building C - Ground Floor', 40.00);

-- Insert sample parking slots
INSERT IGNORE INTO parking_slots (area_id, slot_number, status) VALUES
-- Main Parking Lot (Area 1) - 10 slots
(1, 'A1', 'Available'),
(1, 'A2', 'Available'),
(1, 'A3', 'Available'),
(1, 'A4', 'Available'),
(1, 'A5', 'Available'),
(1, 'A6', 'Available'),
(1, 'A7', 'Available'),
(1, 'A8', 'Available'),
(1, 'A9', 'Available'),
(1, 'A10', 'Available'),
-- VIP Parking (Area 2) - 5 slots
(2, 'V1', 'Available'),
(2, 'V2', 'Available'),
(2, 'V3', 'Available'),
(2, 'V4', 'Available'),
(2, 'V5', 'Available'),
-- Visitor Parking (Area 3) - 8 slots
(3, 'V1', 'Available'),
(3, 'V2', 'Available'),
(3, 'V3', 'Available'),
(3, 'V4', 'Available'),
(3, 'V5', 'Available'),
(3, 'V6', 'Available'),
(3, 'V7', 'Available'),
(3, 'V8', 'Available');

-- ============================================================================
-- DATABASE SETUP COMPLETE
-- ============================================================================
-- Next Steps:
-- 1. Run setup_admin.php to ensure admin password is correct
-- 2. Access the system at: http://localhost/Parking/
-- 3. Register a new user account
-- 4. Add vehicles with vehicle type selection
-- 5. Book parking slots
-- ============================================================================
