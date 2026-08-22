# SDC310L Course Project — Summit Outfitters Online Store

**Student:** James Strohm (jamstr441)
**Course:** SDC310L — Software Development Capstone Lab
**Term:** 08/03/2026 – 09/06/2026

A PHP online store with a product catalog and shopping cart, built
incrementally over five weeks.

## Current milestone — Week 4: Applying the MVC Framework

The store is re-architected into Model-View-Controller. Every request now
enters through a single front controller; no other PHP file is reachable as a
page. **No behavior a visitor can observe has changed** — that was the
definition of done for this milestone.

**Delivered this week**

- `index.php` is a front controller: it resolves `?action=` to a controller
  method, calls it, and renders or redirects. No business rules, no SQL, no
  markup.
- `core/Router.php` — a whitelist route table. Each route declares the HTTP
  verb it accepts, so a cart mutation reached by GET changes nothing.
- Models: `Product` (all database access), `Cart` (quantity rules and order
  totals), `Money` (cents conversion and formatting), `SessionCart` (the only
  file that touches `$_SESSION`).
- Controllers: `CatalogController` and `CartController`, which call models and
  choose a view but never run SQL, emit HTML, or send headers.
- View templates under `views/`, rendered inside a shared `layout.php`.
- The test suite grew from 71 assertions to 208, now covering the router, the
  view helpers, and session storage alongside the cart and product rules.

**Two Week 3 defects fixed by the restructure**

- Adding a product id that does not exist used to succeed silently: the id sat
  in the session and the cart page skipped it, so the click appeared to do
  nothing. `Product->byId()` now guards the two operations that can create a
  cart line — `add` and `increase`. `remove` and `decrease` only shrink the
  cart, so they need no check.
- The open-redirect guard whitelisted filenames (`index.php`, `cart.php`). It
  now whitelists route names (`catalog`, `cart`) — the same defense in the new
  vocabulary.

### Request flow

```
GET /SDC310L/index.php?action=cart
  |
  index.php ......................... front controller
    |-- require core/bootstrap.php ... autoloader and helpers
    |-- Router::resolve('cart','GET') -> ['CartController','index']
    |-- new CartController($pdo)
    |     `-- index()
    |           |-- SessionCart::load() ............... Cart model
    |           |-- (new Product($pdo))->byIds($ids) .. Product model
    |           |-- $cart->lines($products)
    |           |-- Cart::totals($lines)
    |           `-- returns ['view'=>'cart/index','data'=>[...]]
    `-- View::render('cart/index', $data) -> wrapped in views/layout.php
```

| Layer | May do | May never do |
| --- | --- | --- |
| Model | Query the database, apply rules, compute money, read/write the session | Emit HTML, read `$_GET`/`$_POST`, redirect |
| Controller | Read request input, call models, choose a view or ask for a redirect | Run SQL, emit HTML, send headers |
| View | Render passed-in data, escape it | Query, mutate state, read superglobals |

### Routes

| `?action=` | Handler | Verb |
| --- | --- | --- |
| *(absent)* or `catalog` | `CatalogController::index` | GET |
| `cart` | `CartController::index` | GET |
| `cart.add` | `CartController::add` | POST |
| `cart.remove` | `CartController::remove` | POST |
| `cart.increase` | `CartController::increase` | POST |
| `cart.decrease` | `CartController::decrease` | POST |
| `cart.checkout` | `CartController::checkout` | POST |

An unknown action returns 404. A POST route reached by GET redirects to the
catalog, which is what keeps Post/Redirect/Get intact.

### How the money is handled

All currency is computed in **whole cents**. Costs come out of MySQL as exact
`DECIMAL` strings and are converted once by `Money::toCents()`, so no sequence
of additions or percentages can accumulate binary floating-point error into an
order total. Tax and shipping are each rounded to the cent independently and
the order total is the sum of the three rounded figures, which means the
printed lines always add up to the printed total.

### How cart changes are handled

Every cart change is a `POST` to a `cart.*` action. The controller updates the
session and returns a redirect, which the front controller performs as a 303
(Post/Redirect/Get). The redirect is the point: if a page handled its own POST
and rendered directly, refreshing the browser would silently re-submit and add
the product a second time.

## Repository layout

```
index.php                      Front controller — the only entry point
config/database.php            PDO connection

core/bootstrap.php             Autoloader and helper loading
core/Router.php                Route table and resolution
core/View.php                  Template rendering
core/helpers.php               e(), url(), redirect_to()

controllers/CatalogController.php
controllers/CartController.php

models/Product.php             Product database access
models/Cart.php                Cart rules and order totals
models/Money.php               Cents conversion and formatting
models/SessionCart.php         Session storage

views/layout.php               Document shell, header, navigation, footer
views/catalog/index.php        Catalog table
views/cart/index.php           Cart table and order summary
views/error/not-found.php      404 body

css/style.css                  Store look and feel
database/onlinestore.sql       Database schema and seed data
tests/                         Test suite (php tests/run.php)
docs/                          Screenshots, project plan, design spec and plan
```

There is no Composer. Classes are autoloaded from `models/`, `controllers/`,
and `core/` by a single `spl_autoload_register` callback in
`core/bootstrap.php` — the project is graded on a stock XAMPP install, and a
dependency manager for seven classes is not a trade worth making.

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

The schema is unchanged since Week 2. Week 4 was a code re-architecture: no
table, column, type, or constraint was added, dropped, or altered, and the
seed data is untouched. `database/onlinestore.sql` therefore remains the
current export, verified against the live database rather than assumed.

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

Weeks 2 through 4 are merged into `main`. Each week is developed on its own
branch off `main` and merged through a GitHub pull request.
