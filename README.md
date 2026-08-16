# SDC310L Course Project — Summit Outfitters Online Store

**Student:** James Strohm (jamstr441)
**Course:** SDC310L — Software Development Capstone Lab
**Term:** 08/03/2026 – 09/06/2026

A PHP online store with a product catalog and shopping cart, built
incrementally over five weeks.

## Current milestone — Week 3: Accessing the Database Using PHP

The store is functional. The catalog reads from the `onlinestore` database
over PDO, the cart lives in the PHP session, and every quantity, total, and
checkout rule from the Course Project Overview works end to end.

**Delivered this week**

- `config/database.php` — PDO connection with exception error mode, real
  server-side prepared statements, and try/catch handling
- Catalog rendered live from the `products` table
- Session-based cart (`product_id => quantity`) with add, remove, and
  quantity up/down, clamped to 0 or more on the server
- Cart page line items joined against the database, with items total,
  5% tax, 10% shipping & handling, and order total
- Check Out clears the cart and returns to the catalog with a confirmation
- A test suite covering the cart rules, the money math, and database access

**Deferred to Week 4 (as planned)** — the MVC re-architecture: models,
controllers, view templates, and a single front controller.

### How the money is handled

All currency is computed in **whole cents**. Costs come out of MySQL as exact
`DECIMAL` strings and are converted once, so no sequence of additions or
percentages can accumulate binary floating-point error into an order total.
Tax and shipping are each rounded to the cent independently and the order
total is the sum of the three rounded figures, which means the printed lines
always add up to the printed total.

### How cart changes are handled

Every cart change is a `POST` to `cart-action.php`, which updates the session
and then redirects (Post/Redirect/Get). The redirect is the point: if a page
handled its own POST and rendered directly, refreshing the browser would
silently re-submit and add the product a second time.

## Repository layout

```
index.php                 Catalog page
cart.php                  Shopping cart page
cart-action.php           POST handler for cart changes; always redirects
config/database.php       PDO connection
includes/products.php     Product queries (becomes the Product model in Week 4)
includes/cart.php         Cart rules and money math, as pure functions
includes/session.php      Session storage for the cart
includes/header.php       Document head, store banner, navigation
includes/footer.php       Closing markup
css/style.css             Store look and feel
database/onlinestore.sql  Database schema and seed data
tests/                    Test suite (php tests/run.php)
docs/                     Screenshots, project plan, design spec
```

## Running the tests

```
/Applications/XAMPP/xamppfiles/bin/php tests/run.php
```

Exits 0 when every assertion passes and 1 otherwise. The product tests are
integration tests and need the `onlinestore` database imported first.

## Running it locally

Requires XAMPP (Apache + PHP 8 + MariaDB) or any equivalent PHP 8 stack.

1. **Create the database.** Either import `database/onlinestore.sql` through
   phpMyAdmin, or from a terminal:

   ```
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/onlinestore.sql
   ```

   The script is re-runnable — importing it twice gives the same result as
   importing it once.

2. **Serve the files.** Place this repository inside the web root — on a
   default macOS XAMPP install that is
   `/Applications/XAMPP/xamppfiles/htdocs/SDC310L`.

   Symlinking into `htdocs` from a home directory does *not* work on macOS:
   Apache runs as the `daemon` user and cannot traverse a home folder whose
   permissions are owner-only, so the request fails with
   `AH00037: Symbolic link not allowed or link target not accessible` in the
   Apache error log. Put the working copy in `htdocs` and symlink the other
   way if you want it visible elsewhere.

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
binary rounding error into the tax and shipping calculations. Quantity ordered
is not stored here — it is per-user, per-session state and lives in the PHP
session.

The schema is unchanged since Week 2, so `database/onlinestore.sql` remains
the current export.

## Project schedule

| Week | Focus                                     | Tag      |
| ---- | ----------------------------------------- | -------- |
| 1    | Project plan                              | —        |
| 2    | Database and application framework        | `Phase2` |
| 3    | Accessing the database using PHP code     | `Phase3` |
| 4    | Applying the MVC framework                | `Phase4` |
| 5    | Finalizing the application and testing    | `Phase5` |

Git ref names cannot contain spaces, so the assignment's `Phase #2` style is
tagged as `Phase2`.

Week 2 is committed to `main`. Weeks 3 through 5 are developed on branches off
`main` and merged through GitHub.
