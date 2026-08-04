-- =========================================================
-- Savoria — Project 3: Database Integration
-- Schema: savoria_project3_schema.sql
-- Engine: MySQL 8.0+ / MariaDB 10.4+
-- =========================================================

CREATE DATABASE IF NOT EXISTS savoria_project3
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE savoria_project3;

-- ---------------------------------------------------------
-- categories
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- menu_items
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS menu_items (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id       INT UNSIGNED NOT NULL,
    name              VARCHAR(120) NOT NULL,
    description       TEXT,
    price             DECIMAL(10,2) NOT NULL CHECK (price >= 0),
    image_url         VARCHAR(500) DEFAULT NULL,
    spice_level       TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK (spice_level BETWEEN 0 AND 3),
    prep_time_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    is_available      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_menu_items_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_menu_items_category (category_id),
    INDEX idx_menu_items_available (is_available)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- orders
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name     VARCHAR(120) NOT NULL,
    phone             VARCHAR(20) NOT NULL,
    order_type        ENUM('dine_in', 'pickup', 'delivery') NOT NULL DEFAULT 'delivery',
    delivery_address  VARCHAR(255) DEFAULT NULL,
    table_number      TINYINT UNSIGNED DEFAULT NULL,
    status            ENUM('pending','confirmed','preparing','out_for_delivery','delivered','cancelled')
                        NOT NULL DEFAULT 'pending',
    total_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- order_items (line items — join table with pricing snapshot)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id             INT UNSIGNED NOT NULL,
    menu_item_id         INT UNSIGNED NOT NULL,
    item_name_snapshot   VARCHAR(120) NOT NULL,
    unit_price           DECIMAL(10,2) NOT NULL,
    quantity             SMALLINT UNSIGNED NOT NULL DEFAULT 1 CHECK (quantity > 0),
    subtotal             DECIMAL(10,2) NOT NULL,
    special_instructions VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_items_menu_item
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed data — categories
-- ---------------------------------------------------------
INSERT INTO categories (name, description, sort_order) VALUES
    ('Starters', 'Small plates to begin the meal', 1),
    ('Mains', 'Chef-crafted main courses', 2),
    ('Desserts', 'Sweet finishes', 3),
    ('Beverages', 'House-made drinks and coolers', 4);

-- ---------------------------------------------------------
-- Seed data — menu_items
-- ---------------------------------------------------------
INSERT INTO menu_items (category_id, name, description, price, image_url, spice_level, prep_time_minutes, is_available) VALUES
    (1, 'Charred Corn & Burrata', 'Grilled sweet corn, torn burrata, chili oil, micro basil.', 950.00, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&q=80', 1, 12, TRUE),
    (1, 'Smoked Prawn Toast', 'Brioche, smoked prawn butter, pickled shallot, lime zest.', 1150.00, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=500&q=80', 2, 15, TRUE),
    (1, 'Roasted Beet Salad', 'Heirloom beets, whipped goat cheese, candied walnut, sherry vinaigrette.', 850.00, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500&q=80', 0, 10, TRUE),
    (2, 'Slow-Braised Lamb Shank', 'Red wine jus, saffron mash, roasted root vegetables.', 2450.00, 'https://images.unsplash.com/photo-1544025162-d76694265947?w=500&q=80', 1, 35, TRUE),
    (2, 'Charcoal Grilled Salmon', 'Miso glaze, sesame greens, ginger-scallion oil.', 2150.00, 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=500&q=80', 1, 25, TRUE),
    (2, 'Wild Mushroom Risotto', 'Arborio rice, truffle oil, aged parmesan, crisped sage.', 1850.00, 'https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=500&q=80', 0, 28, FALSE),
    (3, 'Dark Chocolate Fondant', 'Molten center, pistachio crumble, vanilla bean ice cream.', 850.00, 'https://images.unsplash.com/photo-1624353365286-3f8d62daad51?w=500&q=80', 0, 18, TRUE),
    (3, 'Saffron Kunafa', 'Crisp shredded pastry, cream cheese filling, rose-saffron syrup.', 780.00, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=500&q=80', 0, 20, TRUE),
    (4, 'Rose & Cardamom Cooler', 'House-made rose syrup, cardamom, soda, fresh mint.', 550.00, 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=500&q=80', 0, 5, TRUE),
    (4, 'Cold Brew Espresso Tonic', 'Slow-steeped cold brew, tonic water, orange peel.', 620.00, 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=500&q=80', 0, 5, TRUE);
