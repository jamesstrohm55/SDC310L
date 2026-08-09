# SDC310L Week 2 — Database and Application Framework

**Date:** 2026-08-09
**Author:** James Strohm (jamstr441)
**Course:** SDC310L — Online Store Course Project

## Purpose

Deliver the Week 2 milestone of the course project: create the database the
application will use and stand up the application framework (pages, shared
includes, navigation, styling). Per the Week 1 project plan, all functional
cart behavior is deliberately deferred to Week 3.

## Scope

**In scope (Week 2):**

- `onlinestore` MariaDB database with a `products` table
- At least five seeded products
- Exported, re-runnable SQL script committed to the repo
- `index.php` (catalog) and `cart.php` skeleton pages with static placeholder
  content
- Shared `includes/header.php` and `includes/footer.php`
- Navigation between catalog and cart
- Base stylesheet establishing the store look and feel
- GitHub repository, `Phase #2` tag, screenshot, updated project plan

**Out of scope (deferred to Week 3 per the project plan):**

- PDO connection code and database reads from PHP
- Session-based cart storage
- Add / remove / quantity-adjust handlers
- Live total, tax, and shipping calculations
- Checkout behavior

The Week 2 pages render the complete *structure* those Week 3 features will
fill in — every required column and control is present, but inert.

## Architecture

A flat PHP site served by XAMPP Apache. No framework and no MVC layering this
week; the MVC re-architecture is the Week 4 milestone. The git repository lives
at `~/Desktop/SDC310L` and is symlinked into `htdocs` so Apache serves the
working tree directly and no build or copy step can drift.

```
SDC310L/
├── index.php              Catalog page (static placeholder rows)
├── cart.php               Cart page (static placeholder + totals block)
├── includes/
│   ├── header.php         Document head, store banner, navigation
│   └── footer.php         Closing markup
├── css/style.css          Store look and feel
├── database/
│   └── onlinestore.sql    Exported schema + seed data
├── docs/                  Screenshot and project plan
├── README.md
└── .gitignore
```

## Data model

Database `onlinestore`, single table `products`:

| Column                | Type          | Constraints                  |
| --------------------- | ------------- | ---------------------------- |
| `product_id`          | INT           | PRIMARY KEY, AUTO_INCREMENT  |
| `product_name`        | VARCHAR(100)  | NOT NULL                     |
| `product_description` | TEXT          |                              |
| `product_cost`        | DECIMAL(10,2) | NOT NULL                     |

`product_cost` is `DECIMAL`, not a float, because currency must not carry
binary rounding error into the Week 3 tax and shipping math.

Quantity ordered is intentionally **not** a column. It is per-user, per-session
state, and Week 3 stores it in the PHP session rather than in the products
table.

`database/onlinestore.sql` is re-runnable: `CREATE DATABASE IF NOT EXISTS`
followed by `DROP TABLE IF EXISTS products` and the seed inserts. Importing it
twice yields the same result as importing it once.

## Page contracts

**`index.php` — catalog.** Displays one row per product with all five required
attributes: Product ID, Product Name, Product Description, Product Cost, and
Quantity currently in the cart. Each row carries the controls Week 3 will wire
up — Add to Cart, Remove from Cart, and quantity increase/decrease — rendered
as disabled controls. Includes a link to the cart page.

**`cart.php` — shopping cart.** Displays the line-item table (Product ID,
Product Name, Quantity Ordered, Product Cost, Product Total) and the order
summary block: Total of Items Ordered, Tax at 5%, Shipping & Handling at 10% of
the pre-tax total, and Order Total. Includes Continue Shopping and Check Out
controls. All figures are static placeholders this week.

Both pages include the shared header and footer so the Week 4 view extraction
has an obvious seam to cut along.

## Verification

No claim of completion is made until each of these has been run and its output
read:

1. `php -l` on every `.php` file — no syntax errors.
2. Drop and re-import `database/onlinestore.sql` into MariaDB from a clean
   state, then `SELECT` from `products` and confirm at least five rows return
   with correct types.
3. Request both pages through Apache and confirm HTTP 200 with no PHP
   warnings, notices, or errors in the response body or the Apache error log.
4. Capture the screenshot from that verified live render, not from a mockup.

## Delivery

All Week 2 work commits directly to `main` and is pushed to a new public
repository, `jamesstrohm55/SDC310L`, tagged `Phase #2`. Weeks 3 through 5 each
get a branch off `main`, merged through GitHub.
