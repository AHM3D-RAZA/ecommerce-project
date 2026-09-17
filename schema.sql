-- ecommerce_project database
-- Import this whole file in phpMyAdmin, or run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS ecommerce_project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecommerce_project;

-- ---------------------------------------------------------
-- 1. users
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 2. categories
-- ---------------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    image VARCHAR(255) NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 3. products
-- ---------------------------------------------------------
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT UNSIGNED DEFAULT 0,
    image VARCHAR(255) NOT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 4. orders
-- ---------------------------------------------------------
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cod', 'paypal') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    order_status ENUM('processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'processing',
    transaction_id VARCHAR(100) NULL,
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- 5. order_items
-- ---------------------------------------------------------
CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------

-- Admin login: admin@shop.com / admin123
-- Demo customer login: customer@shop.com / customer123
INSERT INTO users (name, email, password, role) VALUES
('Site Admin', 'admin@shop.com', '$2y$10$wOHqqbEahf6zysegkudz9.yTYXy7H0UMc65mIxQnvSXVnPgPvjhx.', 'admin'),
('Demo Customer', 'customer@shop.com', '$2y$10$uwoQmWRt8eKCtqf9gda0rOXO6fZ45K5irlehSLkV4oYk05USjn8F6', 'customer');

INSERT INTO categories (name, slug, image, status) VALUES
('Computer & Laptop', 'computer-laptop', 'cats/1.png', 1),
('Digital Cameras', 'digital-cameras', 'cats/2.png', 1),
('Smart Phones', 'smart-phones', 'cats/3.png', 1),
('Televisions', 'televisions', 'cats/4.png', 1),
('Audio', 'audio', 'cats/5.png', 1),
('Smart Watches', 'smart-watches', 'cats/6.png', 1);

INSERT INTO products (category_id, name, slug, description, price, stock, image, status) VALUES
(1, 'MacBook Pro 13" Display, i5', 'macbook-pro-13-i5', 'Powerful and portable, the 13" MacBook Pro with an Intel Core i5 processor handles everyday work and creative tasks with ease.', 1199.99, 18, 'products/product-1.jpg', 1),
(1, 'Lenovo 330-15IKBR 15.6"', 'lenovo-330-15ikbr', 'A reliable everyday laptop with a 15.6" display, ideal for study, browsing, and office work.', 339.99, 25, 'products/product-13.jpg', 1),
(2, 'GoPro HERO7 Black HD Waterproof Action Camera', 'gopro-hero7-black', 'Capture smooth, stabilized 4K video and 12MP photos, waterproof up to 10m without a housing.', 349.99, 14, 'products/product-11.jpg', 1),
(2, 'Sony Alpha a5100 Mirrorless Camera', 'sony-alpha-a5100', 'Compact mirrorless camera with a 24.3MP sensor and fast hybrid autofocus for sharp, detailed shots.', 499.99, 10, 'products/product-14.jpg', 1),
(3, 'Apple 11 Inch iPad Pro Wi-Fi 256GB', 'ipad-pro-11-256gb', 'The 11" iPad Pro with 256GB storage delivers desktop-class performance in a slim, all-screen design.', 899.99, 20, 'products/product-3.jpg', 1),
(3, 'Google Pixel 3 XL 128GB', 'google-pixel-3-xl-128gb', 'Google Pixel 3 XL with 128GB storage, a stunning camera, and the smoothest version of Android.', 799.99, 16, 'products/product-4.jpg', 1),
(4, 'Samsung 55" Class LED 2160p Smart TV', 'samsung-55-led-2160p', 'A 55" 4K Smart TV with vivid colour and built-in streaming apps for the whole family.', 899.99, 9, 'products/product-5.jpg', 1),
(4, 'Sony Class LED 2160p Smart 4K Ultra HD TV', 'sony-led-2160p-4k', 'Crisp 4K Ultra HD picture with Sony image processing and smart TV features built in.', 799.99, 7, 'products/product-9.jpg', 1),
(5, 'Bose SoundLink Bluetooth Speaker', 'bose-soundlink-speaker', 'Compact, portable Bluetooth speaker with clear, room-filling Bose sound.', 79.99, 30, 'products/product-2.jpg', 1),
(5, 'Bose SoundSport Wireless Headphones', 'bose-soundsport-wireless', 'Sweat and weather-resistant wireless headphones built for an active lifestyle.', 149.99, 22, 'products/product-6.jpg', 1),
(5, 'Beats by Dr. Dre Wireless Headphones', 'beats-dre-wireless', 'Award-winning sound with Class 1 Bluetooth wireless technology and up to 40 hours of battery life.', 279.99, 12, 'products/product-10.jpg', 1),
(5, 'WONDERBOOM Portable Bluetooth Speaker', 'wonderboom-bluetooth-speaker', 'Rugged, waterproof, and buoyant speaker with 360-degree sound you can take anywhere.', 99.99, 26, 'products/product-16.jpg', 1),
(5, 'Google Home Mini Smart Speaker', 'google-home-mini', 'Compact smart speaker with the Google Assistant built in, for hands-free help around the house.', 49.00, 35, 'products/product-15.jpg', 1),
(5, 'Google Home Hub with Google Assistant', 'google-home-hub', 'A display for your Google Assistant to help manage your day, control your smart home, and enjoy entertainment.', 149.00, 15, 'products/product-17.jpg', 1),
(6, 'Apple Watch Series 4 Gold Aluminum Case', 'apple-watch-series-4-gold', 'Apple Watch Series 4 with a bigger, more vivid display and advanced fitness tracking.', 429.99, 13, 'products/product-8.jpg', 1),
(6, 'Apple Watch Series 3 White Sport Band', 'apple-watch-series-3-white', 'Apple Watch Series 3 with built-in GPS to track your run, swim, and everyday activity.', 214.49, 19, 'products/product-12-2.jpg', 1);
