-- =========================================================
-- Savoria — add two new dishes to an EXISTING database
-- Run this only if you already imported savoria_full_schema.sql
-- earlier and just want the two new dishes added on top.
-- (If you're importing the schema fresh, you don't need this
-- file — the two dishes are already included there.)
-- =========================================================
USE savoria_app;

INSERT INTO menu_items (category_id, name, description, price, image_url, spice_level, prep_time_minutes, is_available) VALUES
    (1, 'Truffle Mushroom Arancini', 'Crisp risotto balls, wild mushroom, truffle aioli, shaved parmesan.', 890.00, 'https://images.unsplash.com/photo-1541529086526-db283c563270?w=500&q=80', 0, 14, TRUE),
    (2, 'Peri-Peri Grilled Chicken', 'Char-grilled chicken breast, peri-peri glaze, herbed rice, charred vegetables.', 2050.00, 'https://images.unsplash.com/photo-1532550907401-a500c9a57435?w=500&q=80', 2, 30, TRUE);
