-- JKC Cafe sanitized database schema
-- Compatible with MySQL 8+ and MariaDB 10.4+.
-- Create/select your database before importing this file.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    recovery_email VARCHAR(150) DEFAULT NULL,
    recovery_phone VARCHAR(30) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role TINYINT NOT NULL DEFAULT 0,
    account_status VARCHAR(30) NOT NULL DEFAULT 'active',
    deleted_at DATETIME DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_account_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'available',
    PRIMARY KEY (id),
    KEY idx_products_status (status),
    KEY idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    total_amount DECIMAL(10,2) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    customer_name VARCHAR(100) DEFAULT NULL,
    phone_number VARCHAR(30) DEFAULT NULL,
    delivery_address TEXT DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 60.00,
    delivery_partner VARCHAR(50) NOT NULL DEFAULT 'JKC Cafe Delivery',
    cancel_reason TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_orders_user_created (user_id, created_at),
    KEY idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED DEFAULT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    topic VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_messages_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_reminders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_product_reminder (user_id, product_id),
    KEY idx_product_reminders_product_status (product_id, status),
    KEY idx_product_reminders_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'product_available',
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'unread',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user_notifications_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe catalogue seed data only. No users, orders, messages, or uploads are included.
INSERT INTO products (name, category, price, image, status) VALUES
    ('Signature Latte', 'Featured Drinks', 145.00, 'images/signature-latte.png', 'available'),
    ('Caramel Cloud Coffee', 'Featured Drinks', 165.00, 'images/caramel-cloud-coffee.png', 'available'),
    ('Mocha Cream Latte', 'Featured Drinks', 170.00, 'images/mocha-cream-latte.png', 'available'),
    ('Butter Croissant', 'Featured Food', 95.00, 'images/croissant-card.png', 'available'),
    ('Blueberry Muffin', 'Featured Food', 105.00, 'images/blueberry-muffin.png', 'available'),
    ('Ham & Cheese Toast', 'Featured Food', 155.00, 'images/ham-cheese-toast.png', 'available'),
    ('Americano', 'Hot Coffee', 120.00, 'images/americano.png', 'available'),
    ('Cappuccino', 'Hot Coffee', 140.00, 'images/cappuccino.png', 'available'),
    ('Vanilla Latte', 'Hot Coffee', 155.00, 'images/vanilla-latte.png', 'available'),
    ('Iced Americano', 'Iced Coffee', 130.00, 'images/iced-americano.png', 'available'),
    ('Iced Caramel Coffee', 'Iced Coffee', 165.00, 'images/iced-coffee-card.png', 'available'),
    ('Cold Brew', 'Iced Coffee', 150.00, 'images/cold-brew.png', 'available');
