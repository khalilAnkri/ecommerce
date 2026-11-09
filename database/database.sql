-- ============================================================
-- database/database.sql — Poilu & Co
-- Creates the database, the 6 tables and the demo data.
--
-- How to import (XAMPP / WAMP):
--   1. Open http://localhost/phpmyadmin
--   2. Click the "Import" tab, choose this file, click "Import"
--   (Importing again deletes and recreates all the tables.)
--
-- Content of this version:
--   - the 6 tables: users, categories, products, orders,
--     order_items, personalizations
--   - 6 categories and 24 products
--   - 2 demo accounts (admin + customer)
--   - the 150–200 demo orders for the Python analysis are
--     added later at the bottom of this file
-- ============================================================

-- This file is written in UTF-8: keeps the accents (é, è, «) correct
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS poilu_and_co CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE poilu_and_co;

-- Delete old tables so the file can be imported again.
-- (phpMyAdmin forbids DROP DATABASE, so we drop each table instead.)
-- Order matters: tables that point to others are deleted first.
DROP TABLE IF EXISTS personalizations;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;


-- ------------------------------------------------------------
-- users: customers and administrators
-- ------------------------------------------------------------
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    first_name    VARCHAR(50)  NOT NULL,
    last_name     VARCHAR(50)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,              -- made with password_hash() in PHP
    role          ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    phone         VARCHAR(20)  NULL,
    address       VARCHAR(150) NULL,
    postal_code   CHAR(4)      NULL,                  -- Belgian postal codes have 4 digits
    city          VARCHAR(80)  NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- categories: médailles, colliers, gamelles...
-- slug = short name used in URLs (products.php?category=mugs)
-- ------------------------------------------------------------
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    sort_order  INT          NOT NULL DEFAULT 0       -- display order in menus
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- products: one category has many products
-- ------------------------------------------------------------
CREATE TABLE products (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    category_id          INT          NOT NULL,
    name                 VARCHAR(120) NOT NULL,
    description          TEXT         NOT NULL,
    price                DECIMAL(6,2) NOT NULL,       -- EUR, VAT included
    old_price            DECIMAL(6,2) NULL,           -- filled only when the product is on sale
    image                VARCHAR(255) NULL,           -- file name in assets/images/products/
    animal               ENUM('dog', 'cat', 'both') NOT NULL DEFAULT 'both',
    personalization_type ENUM('tag', 'collar', 'bowl', 'blanket', 'mug', 'gift') NOT NULL,
    badge                VARCHAR(30)  NULL,           -- 'Nouveau', 'Best-seller'... (optional)
    is_featured          TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = shown on the homepage
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,  -- 0 = hidden from the shop
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- orders: one user has many orders
-- user_id is NULL for a guest order (customer not logged in).
-- The address is copied into the order, so old orders stay
-- correct even if the customer changes their profile later.
-- ------------------------------------------------------------
CREATE TABLE orders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NULL,
    first_name    VARCHAR(50)  NOT NULL,
    last_name     VARCHAR(50)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    phone         VARCHAR(20)  NULL,
    address       VARCHAR(150) NOT NULL,
    postal_code   CHAR(4)      NOT NULL,
    city          VARCHAR(80)  NOT NULL,
    subtotal      DECIMAL(8,2) NOT NULL,
    shipping_cost DECIMAL(6,2) NOT NULL,
    total         DECIMAL(8,2) NOT NULL,
    payment_method ENUM('bancontact', 'card', 'paypal') NOT NULL DEFAULT 'bancontact',   -- payment is simulated
    -- simulated life of an order: production and delivery are not real
    status        ENUM('pending', 'paid', 'in_production', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- order_items: one order has many items,
-- one product appears in many order items.
-- Name and price are copied: a later price change must not
-- change an old order.
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT          NOT NULL,
    product_id   INT          NOT NULL,
    product_name VARCHAR(120) NOT NULL,
    unit_price   DECIMAL(6,2) NOT NULL,
    quantity     INT          NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- personalizations: one order item has (at most) one personalisation
-- UNIQUE on order_item_id guarantees the "one" in "one-to-one".
-- ------------------------------------------------------------
CREATE TABLE personalizations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT          NOT NULL UNIQUE,
    pet_name      VARCHAR(30)  NULL,
    phone         VARCHAR(20)  NULL,
    color         VARCHAR(30)  NULL,
    size          VARCHAR(10)  NULL,
    message       VARCHAR(120) NULL,
    photo         VARCHAR(255) NULL,                  -- random file name of the uploaded pet photo
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- DEMO DATA
-- ============================================================

-- Categories (the slugs are also used in includes/header.php)
INSERT INTO categories (id, name, slug, description, sort_order) VALUES
(1, 'Médailles',          'medailles', 'Médailles d''identité gravées au prénom et au numéro de téléphone.', 1),
(2, 'Colliers',           'colliers',  'Colliers et harnais brodés au prénom de votre compagnon.',          2),
(3, 'Gamelles',           'gamelles',  'Gamelles en céramique ou en inox, personnalisées.',                 3),
(4, 'Plaids & coussins',  'plaids',    'Plaids, coussins et paniers avec le portrait ou le prénom.',        4),
(5, 'Mugs',               'mugs',      'Mugs et accessoires de balade pour les humains fans de leur animal.', 5),
(6, 'Coffrets cadeaux',   'coffrets',  'Coffrets prêts à offrir pour une adoption ou un anniversaire.',     6);


-- Products (ids 1 to 4 are the best-sellers of the homepage)
INSERT INTO products (id, category_id, name, description, price, old_price, image, animal, personalization_type, badge, is_featured, created_at) VALUES
-- Médailles
(1,  1, 'Médaille os gravée',             'Médaille en forme d''os en laiton, gravée au laser avec le prénom et votre numéro de téléphone.', 19.90, NULL,  'medaille-os.jpg',        'dog',  'tag',     'Nouveau',     1, '2026-10-20 10:00:00'),
(5,  1, 'Médaille ronde en laiton',       'Médaille ronde classique, gravure recto-verso offerte.',                                          19.90, NULL,  'medaille-ronde.jpg',     'both', 'tag',     NULL,          0, '2026-03-02 10:00:00'),
(6,  1, 'Médaille cœur émaillée',         'Médaille cœur émaillée en 5 couleurs pastel, gravée au prénom.',                                  22.90, NULL,  'medaille-coeur.jpg',     'both', 'tag',     NULL,          0, '2026-04-11 10:00:00'),
(7,  1, 'Médaille poisson pour chat',     'Petite médaille légère en forme de poisson, idéale pour les chats.',                              21.90, NULL,  'medaille-poisson.jpg',   'cat',  'tag',     NULL,          0, '2026-05-06 10:00:00'),
-- Colliers
(2,  2, 'Collier cuir brodé au prénom',   'Collier en cuir végétal, prénom brodé et boucle dorée. Tailles S à L.',                           34.90, NULL,  'collier-cuir.jpg',       'dog',  'collar',  'Best-seller', 1, '2026-02-14 10:00:00'),
(8,  2, 'Collier chat avec grelot gravé', 'Collier anti-étranglement avec grelot et petite plaque gravée.',                                   24.90, NULL,  'collier-chat.jpg',       'cat',  'collar',  NULL,          0, '2026-03-21 10:00:00'),
(9,  2, 'Collier nylon coloris pastel',   'Collier en nylon doux et résistant, 6 coloris pastel, prénom brodé.',                             27.90, 32.90, 'collier-nylon.jpg',      'dog',  'collar',  NULL,          0, '2026-06-01 10:00:00'),
(10, 2, 'Harnais brodé au prénom',        'Harnais rembourré confortable, prénom brodé sur le dos.',                                         44.90, NULL,  'harnais.jpg',            'dog',  'collar',  NULL,          0, '2026-09-15 10:00:00'),
-- Gamelles
(3,  3, 'Gamelle céramique prénom',       'Gamelle en céramique émaillée avec le prénom de votre animal. Passe au lave-vaisselle.',          29.90, 34.90, 'gamelle-ceramique.jpg',  'both', 'bowl',    NULL,          1, '2026-01-10 10:00:00'),
(11, 3, 'Gamelle inox gravée',            'Gamelle en inox antidérapante, gravure du prénom sur le côté.',                                   26.90, NULL,  'gamelle-inox.jpg',       'dog',  'bowl',    NULL,          0, '2026-02-25 10:00:00'),
(12, 3, 'Gamelle surélevée pour chat',    'Gamelle inclinée sur support en bois, plus confortable pour manger.',                             32.90, NULL,  'gamelle-chat.jpg',       'cat',  'bowl',    'Nouveau',     0, '2026-10-28 10:00:00'),
(13, 3, 'Set 2 gamelles + tapis',         'Deux gamelles en céramique et un tapis en silicone assorti, au prénom de votre animal.',          49.90, NULL,  'set-gamelles.jpg',       'both', 'bowl',    NULL,          0, '2026-07-04 10:00:00'),
-- Plaids & coussins
(4,  4, 'Plaid portrait de votre animal', 'Plaid en polaire très doux imprimé avec la photo de votre compagnon. 130 × 150 cm.',               54.90, NULL,  'plaid-portrait.jpg',     'both', 'blanket', NULL,          1, '2026-04-02 10:00:00'),
(14, 4, 'Coussin portrait',               'Coussin 40 × 40 cm avec le portrait de votre animal, housse lavable.',                            39.90, NULL,  'coussin-portrait.jpg',   'both', 'blanket', NULL,          0, '2026-05-19 10:00:00'),
(15, 4, 'Couverture brodée au prénom',    'Couverture en polaire pour panier ou canapé, prénom brodé dans un coin.',                         44.90, NULL,  'couverture-brodee.jpg',  'both', 'blanket', NULL,          0, '2026-08-08 10:00:00'),
(16, 4, 'Coussin de panier brodé',        'Coussin épais pour panier, déhoussable, prénom brodé.',                                           59.00, NULL,  'coussin-panier.jpg',     'dog',  'blanket', NULL,          0, '2026-09-30 10:00:00'),
-- Mugs
(17, 5, 'Mug portrait photo',             'Mug en céramique 330 ml imprimé avec la photo de votre animal.',                                  22.90, NULL,  'mug-portrait.jpg',       'both', 'mug',     'Nouveau',     0, '2026-11-02 10:00:00'),
(18, 5, 'Mug « Maman de… »',              'Mug avec l''inscription « Maman de » suivie du prénom de votre animal.',                          19.90, NULL,  'mug-maman.jpg',          'both', 'mug',     NULL,          0, '2026-03-08 10:00:00'),
(19, 5, 'Mug « Papa de… »',               'Mug avec l''inscription « Papa de » suivie du prénom de votre animal.',                           19.90, NULL,  'mug-papa.jpg',           'both', 'mug',     NULL,          0, '2026-06-15 10:00:00'),
(20, 5, 'Gourde de balade gravée',        'Gourde avec bol intégré pour les balades, prénom gravé.',                                         24.90, 29.90, 'gourde.jpg',             'dog',  'mug',     NULL,          0, '2026-05-27 10:00:00'),
-- Coffrets cadeaux
(21, 6, 'Coffret adoption chiot',         'Médaille gravée, gamelle au prénom et petit plaid dans une jolie boîte cadeau.',                  49.90, NULL,  'coffret-chiot.jpg',      'dog',  'gift',    'Best-seller', 0, '2026-01-20 10:00:00'),
(22, 6, 'Coffret adoption chaton',        'Collier à grelot gravé, gamelle surélevée et coussin pour accueillir un chaton.',                 49.90, NULL,  'coffret-chaton.jpg',     'cat',  'gift',    NULL,          0, '2026-01-20 10:00:00'),
(23, 6, 'Coffret anniversaire',           'Bandana, médaille « Happy birthday » et mug assorti pour fêter son anniversaire.',                39.90, NULL,  'coffret-anniversaire.jpg','both','gift',    NULL,          0, '2026-07-22 10:00:00'),
(24, 6, 'Coffret « Moi & mon compagnon »','Un mug pour vous et une médaille pour lui, avec le même prénom gravé.',                            57.90, NULL,  'coffret-duo.jpg',        'both', 'gift',    NULL,          0, '2026-10-05 10:00:00');


-- Demo accounts
--   admin@poilu-and-co.be  /  Admin123!
--   client@poilu-and-co.be /  Client123!
INSERT INTO users (id, first_name, last_name, email, password_hash, role, phone, address, postal_code, city) VALUES
(1, 'Admin', 'Poilu',  'admin@poilu-and-co.be',  '$2y$10$wgre6hvt3K0Fl8ejUUr8rOCtMebW/w2LXyXPCA.PlMo.uRqgE.dh.', 'admin',    NULL,              NULL,                  NULL,   NULL),
(2, 'Camille', 'Dupont','client@poilu-and-co.be', '$2y$10$L2V/muqQ1Erl7FZxU2DCu.tIpcw0vwr8b3PfmTRP/aTfAY1f4syqy', 'customer', '+32 470 12 34 56', 'Rue du Bailli 12',    '1050', 'Ixelles');