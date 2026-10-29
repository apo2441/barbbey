-- ============================================
-- BARBERSHOP BOOKING SYSTEM - DATABASE SCHEMA
-- ============================================

CREATE DATABASE IF NOT EXISTS barbershop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barbershop_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    reset_token VARCHAR(64),
    reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(150),
    description TEXT,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default_shop.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE barbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barber_id CHAR(8) UNIQUE NOT NULL,
    shop_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    bio TEXT,
    avatar VARCHAR(255) DEFAULT 'default_barber.png',
    specialties VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    subscription_status ENUM('paid','unpaid','trial') DEFAULT 'trial',
    subscription_due DATE,
    reset_token VARCHAR(64),
    reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
);

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barber_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration_minutes INT DEFAULT 30,
    price DECIMAL(8,2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE
);

CREATE TABLE availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barber_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday,1=Monday,...,6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE
);

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    barber_id INT NOT NULL,
    service_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    end_time TIME NOT NULL,
    client_name VARCHAR(100),
    client_email VARCHAR(150),
    client_phone VARCHAR(20),
    notes TEXT,
    status ENUM('pending','confirmed','cancelled','completed','reschedule_pending') DEFAULT 'confirmed',
    reschedule_date DATE,
    reschedule_time TIME,
    reschedule_token VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

CREATE TABLE ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    barber_id INT NOT NULL,
    appointment_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE subscription_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barber_id INT NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    period_month VARCHAR(7) NOT NULL,
    paid_at DATE NOT NULL,
    notes TEXT,
    recorded_by INT,
    FOREIGN KEY (barber_id) REFERENCES barbers(id) ON DELETE CASCADE
);

-- Default admin (password: 'admin123' - CHANGE THIS!)
INSERT INTO admins (name, email, password) VALUES 
('Admin', 'admin@barbershop.com', '$2y$10$Nuu28s9LARU/vGoxQDUcZuJlwZZgyBw8A6sjPuGzocf9hCgzk1H82');

INSERT INTO shops (name, address, city, phone, description, latitude, longitude) VALUES
('Classic Cuts', '123 Main Street', 'Istanbul', '+90 212 555 0001', 'Traditional barbershop with modern flair.', 41.0082, 28.9784),
('The Fade Factory', '456 Barber Lane', 'Istanbul', '+90 212 555 0002', 'Specialists in fades and modern cuts.', 41.0150, 28.9800);
