-- ---------------------------------------------------------------------------
-- SDC310L Online Store Course Project
-- James Strohm (jamstr441)
--
-- Week 2: Database creation and seed data.
--
-- This script is re-runnable. Importing it a second time produces the same
-- result as importing it once.
--
-- Import from the command line:
--     mysql -u root < database/onlinestore.sql
--
-- Or in phpMyAdmin: Import tab -> choose this file -> Go.
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `onlinestore`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `onlinestore`;

-- ---------------------------------------------------------------------------
-- Table: products
--
-- Holds the catalog. Quantity ordered is deliberately not stored here: it is
-- per-user, per-session state and is held in the PHP session (Week 3), not in
-- the shared product catalog.
--
-- product_cost is DECIMAL rather than FLOAT so currency values carry no
-- binary rounding error into the Week 3 tax and shipping calculations.
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
    `product_id`          INT            NOT NULL AUTO_INCREMENT,
    `product_name`        VARCHAR(100)   NOT NULL,
    `product_description` TEXT           NULL,
    `product_cost`        DECIMAL(10,2)  NOT NULL,
    PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- Seed data: six products (course project requires a minimum of five).
-- ---------------------------------------------------------------------------

INSERT INTO `products`
    (`product_id`, `product_name`, `product_description`, `product_cost`)
VALUES
    (1, 'Trailhead 45L Backpack',
        'Lightweight 45 liter internal frame pack with a ventilated back panel, adjustable torso, and a rain cover stowed in the base.',
        129.99),
    (2, 'Alpine 20-Degree Sleeping Bag',
        'Mummy-style down bag rated to 20 degrees Fahrenheit. Compresses to the size of a loaf of bread and includes a cotton storage sack.',
        184.50),
    (3, 'Cascade 2-Person Tent',
        'Freestanding three-season tent with two doors, two vestibules, and a full-coverage fly. Packed weight just under five pounds.',
        249.00),
    (4, 'Titanium Camp Stove',
        'Folding titanium canister stove weighing under three ounces. Boils one liter of water in roughly three and a half minutes.',
        74.95),
    (5, 'Summit LED Headlamp',
        'Rechargeable 400 lumen headlamp with a red night mode, a lockout switch, and up to sixty hours of runtime on low.',
        39.99),
    (6, 'Insulated 32oz Water Bottle',
        'Double-wall vacuum insulated stainless steel bottle. Keeps drinks cold for twenty-four hours or hot for twelve.',
        28.50);
