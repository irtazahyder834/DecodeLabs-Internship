-- =========================================================
-- Savoria — Project 4: Authentication & Authorization
-- Schema: savoria_project4_schema.sql
-- Engine: MySQL 8.0+ / MariaDB 10.4+
-- =========================================================

CREATE DATABASE IF NOT EXISTS savoria_project4
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE savoria_project4;

-- ---------------------------------------------------------
-- users
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(120) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('customer', 'staff', 'admin') NOT NULL DEFAULT 'customer',
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- refresh_tokens — supports logout / token revocation
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    revoked     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_refresh_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_refresh_tokens_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed data — one account per role
-- Password for all seed accounts: "Password123!"
-- Hash generated with PHP's password_hash() using PASSWORD_BCRYPT
-- ---------------------------------------------------------
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
    ('Savoria Admin', 'admin@savoria.example', '021111728674', '$2y$10$U5zI32dvnZEHaR2naPBlU.0lJ8ttwrm.IjKbDoJL0zk5xqSqmpulG', 'admin'),
    ('Front of House Staff', 'staff@savoria.example', '03001112233', '$2y$10$U5zI32dvnZEHaR2naPBlU.0lJ8ttwrm.IjKbDoJL0zk5xqSqmpulG', 'staff'),
    ('Ayesha Khan', 'ayesha@savoria.example', '03211234567', '$2y$10$U5zI32dvnZEHaR2naPBlU.0lJ8ttwrm.IjKbDoJL0zk5xqSqmpulG', 'customer');
