# SDC310L Course Project — Summit Outfitters Online Store

**Student:** James Strohm (jamstr441)
**Course:** SDC310L — Software Development Capstone Lab
**Term:** 08/03/2026 – 09/06/2026

A PHP online store with a product catalog and shopping cart, built
incrementally over five weeks.

## Current milestone — Week 2: Database and Application Framework

Week 2 delivers the database and the application framework. The catalog and
cart pages render the complete structure the application needs, populated with
placeholder data. They are wired to the database in Week 3, per the project
plan.

**Delivered this week**

- `onlinestore` MariaDB database with a `products` table
- Six seeded products (the project requires at least five)
- Exported, re-runnable SQL script at `database/onlinestore.sql`
- `index.php` catalog page showing Product ID, Name, Description, Cost, and
  Quantity in Cart, with Add / Remove / quantity-adjust controls in place
- `cart.php` page with the line-item table and the order summary block
  (items total, 5% tax, 10% shipping & handling, order total)
- Shared header and footer includes with navigation between the two pages
- Base stylesheet establishing the store look and feel

**Deferred to Week 3 (as planned)** — PDO database connection, live catalog
reads, session-based cart storage, add/remove/quantity handlers, and checkout.

## Repository layout

```
index.php               Catalog page
cart.php                Shopping cart page
includes/header.php     Document head, store banner, navigation
includes/footer.php     Closing markup
css/style.css           Store look and feel
database/onlinestore.sql  Database schema and seed data
docs/                   Screenshot, project plan, design spec
```

## Running it locally

Requires XAMPP (Apache + PHP 8 + MariaDB) or any equivalent PHP 8 stack.

1. **Create the database.** Either import `database/onlinestore.sql` through
   phpMyAdmin, or from a terminal:

   ```
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/onlinestore.sql
   ```

   The script is re-runnable — importing it twice gives the same result as
   importing it once.

2. **Serve the files.** Place this repository inside your web root, or symlink
   it there:

   ```
   ln -s ~/Desktop/SDC310L /Applications/XAMPP/xamppfiles/htdocs/SDC310L
   ```

3. **Open the store** at <http://localhost/SDC310L/>.

## Database schema

Table `products`:

| Column                | Type          | Notes                       |
| --------------------- | ------------- | --------------------------- |
| `product_id`          | INT           | Primary key, auto-increment |
| `product_name`        | VARCHAR(100)  | Not null                    |
| `product_description` | TEXT          |                             |
| `product_cost`        | DECIMAL(10,2) | Not null                    |

`product_cost` is `DECIMAL` rather than a float so currency values carry no
binary rounding error into the Week 3 tax and shipping calculations. Quantity
ordered is not stored here — it is per-user, per-session state and lives in the
PHP session.

## Project schedule

| Week | Focus                                     | Tag        |
| ---- | ----------------------------------------- | ---------- |
| 1    | Project plan                              | —          |
| 2    | Database and application framework        | `Phase #2` |
| 3    | Accessing the database using PHP code     | `Phase #3` |
| 4    | Applying the MVC framework                | `Phase #4` |
| 5    | Finalizing the application and testing    | `Phase #5` |

Week 2 is committed to `main`. Weeks 3 through 5 are developed on branches off
`main` and merged through GitHub.
