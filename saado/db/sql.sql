-- EzyLivraison Database Schema
-- Complete SQL schema for the delivery platform

-- Create database
CREATE DATABASE IF NOT EXISTS EzyLivraison;
USE EzyLivraison;

-- Users table (both clients and transporters)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    user_type ENUM('client', 'transporter', 'admin') NOT NULL,
    status ENUM('active', 'pending', 'suspended', 'banned') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT FALSE,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_user_type (user_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Transporter profiles (additional info for transporters)
CREATE TABLE transporter_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_type ENUM('car', 'van', 'truck', 'motorcycle') NOT NULL,
    vehicle_capacity INT NOT NULL, -- in kg
    license_number VARCHAR(50) NOT NULL,
    license_expiry DATE,
    vehicle_registration VARCHAR(20),
    insurance_number VARCHAR(50),
    experience_years INT DEFAULT 0,
    description TEXT,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_date TIMESTAMP NULL,
    verified_by INT NULL,
    rating_average DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    completed_deliveries INT DEFAULT 0,
    total_earnings DECIMAL(10,2) DEFAULT 0.00,
    availability_status ENUM('available', 'busy', 'offline') DEFAULT 'offline',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_verification_status (verification_status),
    INDEX idx_availability_status (availability_status),
    INDEX idx_rating (rating_average)
);

-- Client profiles (additional info for clients)
CREATE TABLE client_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    country VARCHAR(100) DEFAULT 'Maroc',
    total_deliveries INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    preferred_payment_method ENUM('card', 'paypal', 'bank_transfer') DEFAULT 'card',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_city (city)
);

-- Delivery requests
CREATE TABLE deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    transporter_id INT NULL,
    
    -- Pickup information
    pickup_address TEXT NOT NULL,
    pickup_city VARCHAR(100) NOT NULL,
    pickup_postal_code VARCHAR(10) NOT NULL,
    pickup_latitude DECIMAL(10, 8),
    pickup_longitude DECIMAL(11, 8),
    pickup_date DATE,
    pickup_time_start TIME,
    pickup_time_end TIME,
    pickup_contact_name VARCHAR(255),
    pickup_contact_phone VARCHAR(20),
    
    -- Delivery information
    delivery_address TEXT NOT NULL,
    delivery_city VARCHAR(100) NOT NULL,
    delivery_postal_code VARCHAR(10) NOT NULL,
    delivery_latitude DECIMAL(10, 8),
    delivery_longitude DECIMAL(11, 8),
    delivery_date DATE,
    delivery_time_start TIME,
    delivery_time_end TIME,
    delivery_contact_name VARCHAR(255) NOT NULL,
    delivery_contact_phone VARCHAR(20) NOT NULL,
    delivery_contact_email VARCHAR(255),
    
    -- Package information
    package_description TEXT NOT NULL,
    package_weight DECIMAL(8,2), -- in kg
    package_dimensions VARCHAR(50), -- e.g., "30x20x15"
    package_value DECIMAL(10,2), -- declared value
    is_fragile BOOLEAN DEFAULT FALSE,
    special_instructions TEXT,
    
    -- Pricing
    suggested_price DECIMAL(8,2) NOT NULL,
    max_price DECIMAL(8,2),
    final_price DECIMAL(8,2),
    platform_commission DECIMAL(8,2),
    
    -- Status and tracking
    status ENUM('pending', 'accepted', 'picked_up', 'in_transit', 'delivered', 'cancelled', 'disputed') DEFAULT 'pending',
    urgency ENUM('normal', 'urgent', 'express') DEFAULT 'normal',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    picked_up_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    
    -- Distance and duration (calculated)
    estimated_distance INT, -- in km
    estimated_duration INT, -- in minutes
    actual_distance INT,
    actual_duration INT,
    
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transporter_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_client_id (client_id),
    INDEX idx_transporter_id (transporter_id),
    INDEX idx_status (status),
    INDEX idx_pickup_city (pickup_city),
    INDEX idx_delivery_city (delivery_city),
    INDEX idx_created_at (created_at),
    INDEX idx_pickup_date (pickup_date),
    INDEX idx_urgency (urgency)
);

-- Delivery bids (transporters can bid on deliveries)
CREATE TABLE delivery_bids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    transporter_id INT NOT NULL,
    bid_amount DECIMAL(8,2) NOT NULL,
    message TEXT,
    estimated_pickup_time TIMESTAMP,
    estimated_delivery_time TIMESTAMP,
    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (transporter_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bid (delivery_id, transporter_id),
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_transporter_id (transporter_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Messages between users
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'location', 'system') DEFAULT 'text',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_receiver_id (receiver_id),
    INDEX idx_created_at (created_at),
    INDEX idx_is_read (is_read)
);

-- Ratings and reviews
CREATE TABLE ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    rater_id INT NOT NULL, -- who is giving the rating
    rated_id INT NOT NULL, -- who is being rated
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    rating_type ENUM('client_to_transporter', 'transporter_to_client') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rating (delivery_id, rater_id, rated_id),
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_rated_id (rated_id),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at)
);

-- Payments
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    client_id INT NOT NULL,
    transporter_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    platform_commission DECIMAL(10,2) NOT NULL,
    transporter_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('card', 'paypal', 'bank_transfer', 'wallet') NOT NULL,
    payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    payment_gateway VARCHAR(50),
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transporter_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_client_id (client_id),
    INDEX idx_transporter_id (transporter_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('delivery', 'payment', 'rating', 'system', 'promotion') NOT NULL,
    related_id INT, -- ID of related entity (delivery, payment, etc.)
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Tracking information
CREATE TABLE delivery_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    status VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_created_at (created_at)
);

-- System settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
);

-- Disputes
CREATE TABLE disputes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    complainant_id INT NOT NULL,
    respondent_id INT NOT NULL,
    dispute_type ENUM('payment', 'service', 'damage', 'other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'investigating', 'resolved', 'closed') DEFAULT 'open',
    resolution TEXT,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (complainant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (respondent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Promo codes
CREATE TABLE promo_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(8,2) NOT NULL,
    min_order_amount DECIMAL(8,2) DEFAULT 0,
    max_discount_amount DECIMAL(8,2),
    usage_limit INT,
    used_count INT DEFAULT 0,
    valid_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_valid_from (valid_from),
    INDEX idx_valid_until (valid_until),
    INDEX idx_is_active (is_active)
);

-- Promo code usage
CREATE TABLE promo_code_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    promo_code_id INT NOT NULL,
    user_id INT NOT NULL,
    delivery_id INT NOT NULL,
    discount_amount DECIMAL(8,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usage (promo_code_id, user_id, delivery_id),
    INDEX idx_promo_code_id (promo_code_id),
    INDEX idx_user_id (user_id),
    INDEX idx_used_at (used_at)
);

-- Admin activity log
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50), -- user, delivery, payment, etc.
    target_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_target_type (target_type),
    INDEX idx_created_at (created_at)
);
