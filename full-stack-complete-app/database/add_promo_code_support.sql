-- =========================================================
-- Savoria — add promo code support to an EXISTING database
-- Run this only if you already imported savoria_full_schema.sql
-- earlier. (Fresh imports already include these columns.)
-- =========================================================
USE savoria_app;

ALTER TABLE orders
    ADD COLUMN promo_code VARCHAR(30) DEFAULT NULL AFTER total_amount,
    ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER promo_code;
