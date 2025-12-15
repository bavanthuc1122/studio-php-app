-- schema.sql
-- MySQL schema creation and default data seed for studio-php-app

-- Create database (optional if already exists)
CREATE DATABASE IF NOT EXISTS studio_db
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE studio_db;

-- Labels table
CREATE TABLE IF NOT EXISTS labels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default public labels
INSERT IGNORE INTO labels (name, is_public) VALUES
  ('Mới', 1),
  ('Đã xử lý', 1),
  ('Chờ phản hồi', 1),
  ('Hoàn thành', 1);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(255) DEFAULT NULL,
  shoot_date DATE DEFAULT NULL,
  image_link VARCHAR(1024) NOT NULL UNIQUE,
  note TEXT DEFAULT NULL,
  status VARCHAR(20) DEFAULT 'new',
  label VARCHAR(100) DEFAULT 'Mới',
  avatar VARCHAR(255) DEFAULT NULL,
  result_link TEXT DEFAULT NULL,
  result_content TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_label (label),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Config table
CREATE TABLE IF NOT EXISTS config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bg_image TEXT DEFAULT NULL,
  text_color VARCHAR(20) DEFAULT '#000000',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;