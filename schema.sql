-- Reset database
DROP DATABASE IF EXISTS swachhv2;
CREATE DATABASE swachhv2;
USE swachhv2;

-- Drop in the correct order to avoid FK issues
DROP TABLE IF EXISTS segregation_reports;
DROP TABLE IF EXISTS apartments;
DROP TABLE IF EXISTS blocks;
DROP TABLE IF EXISTS societies;
DROP TABLE IF EXISTS users;

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('worker','resident') NOT NULL,
    credits INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Societies
CREATE TABLE societies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Blocks
CREATE TABLE blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    society_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (society_id) REFERENCES societies(id) ON DELETE CASCADE
);

-- Apartments
CREATE TABLE apartments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_id INT NOT NULL,
    apt_number VARCHAR(50) NOT NULL,
    resident_id INT DEFAULT NULL,
    FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Segregation Reports
CREATE TABLE segregation_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apartment_id INT NOT NULL,
    worker_id INT NOT NULL,
    status ENUM('segregated','partial','not','no_waste') NOT NULL,
    report_date DATE NOT NULL,
    FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert societies
INSERT INTO societies (name) VALUES ('Green Residency');

-- Insert blocks
INSERT INTO blocks (society_id, name) VALUES
(1, 'Block A'),
(1, 'Block B');

-- Insert users (password = "12345")
INSERT INTO users (name, email, password, role) VALUES
('Ram', 'worker@example.com', '$2y$10$0kDoNOB53kQl4Urm/k1BW.OYBvAwhGrhpjQvBdpjJND.Jrc8eCu6u', 'worker'),
('Ravi Kumar', 'resident@example.com', '$2y$10$0kDoNOB53kQl4Urm/k1BW.OYBvAwhGrhpjQvBdpjJND.Jrc8eCu6u', 'resident');

-- Insert apartments
INSERT INTO apartments (block_id, apt_number, resident_id) VALUES
(1, 'A-101', 2),
(1, 'A-102', NULL),
(2, 'B-201', NULL);
