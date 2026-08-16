# SDC310L Week 3 — Accessing the Database Using PHP

**Date:** 2026-08-16
**Author:** James Strohm (jamstr441)
**Course:** SDC310L — Online Store Course Project

## Purpose

Make the store functional: read the catalog from the `onlinestore` database,
hold the cart in the PHP session, and implement every quantity, total, and
checkout rule from the Course Project Overview.

## Scope

**In scope (Week 3):** PDO connection with error handling, live catalog reads,
session cart storage, add / remove / quantity adjust with a floor of zero,
cart line items joined against the database, order totals (5% tax, 10%
shipping and handling), checkout, and a test suite.

**Out of scope (deferred to Week 4):** the MVC re-architecture — models,
controllers, view templates, and a single front controller.

## Architecture

Still a flat PHP site; MVC is next week's milestone. The new pieces are
layered so Week 4 has clean seams to cut along.

```
config/database.php    Returns a configured PDO connection
includes/products.php  Product queries          → becomes the Product model
includes/cart.php      Cart rules, pure         → becomes the Cart model
includes/session.php   The only file touching $_SESSION
cart-action.php        POST handler; always redirects → becomes CartController
index.php, cart.php    Pages                    → become views
tests/                 Assertions over the above
```

The working copy moved from `~/Desktop/SDC310L` into
`/Applications/XAMPP/xamppfiles/htdocs/SDC310L`, with a symlink left on the
Desktop. macOS sets home directories to owner-only permissions and XAMPP's
Apache runs as `daemon`, so a symlink pointing from `htdocs` into a home
folder cannot be traversed. The repository has to live in the web root; the
symlink runs the other way.

## Design decisions

**Cart shape.** The cart is a plain `product_id => quantity` map. It stores
ids, not product rows, so a price change in the database is reflected the next
time the cart renders rather than being frozen at add-to-cart time.

**Pure cart functions.** Every function in `includes/cart.php` takes a cart and
returns a new one, and none of them touch `$_SESSION`. That is what makes the
quantity and money rules testable without a web request. Session reading and
writing lives in `includes/session.php` alone.

**Money in whole cents.** Costs come out of MySQL as exact `DECIMAL` strings
and are converted to integer cents once. Tax and shipping are each rounded to
the cent independently and the order total is the sum of the three rounded
figures, so the printed lines always add up to the printed total. Accumulating
floats and rounding only at the end can produce a total that does not match its
own line items.

**Post/Redirect/Get.** Every cart change is a POST to `cart-action.php`, which
updates the session and then issues a 303 redirect. Without the redirect,
refreshing the page after adding an item would re-submit the POST and add it
again. A GET to the handler changes nothing and redirects to the catalog.

**Clamping is server-side.** The quantity floor of zero is enforced in
`cart_set_quantity`, not in the UI. Disabling the minus button at zero is a
convenience; the rule holds even if the request is submitted directly.

**Bound parameters and cast ids.** `products_by_ids` builds an `IN (...)` list
— the one place a naive implementation would interpolate. Ids are cast to int
first and then bound as placeholders, so neither path reaches SQL as text.

**Escaping at output.** Every value read from the database passes through
`htmlspecialchars` before rendering, including inside `aria-label` attributes.

## Verification

1. `php -l` on all 13 PHP files.
2. `php tests/run.php` — 71 assertions over the cart rules, money rounding,
   and database access; exits non-zero on any failure.
3. The full cart flow driven through Apache with a cookie jar: add, increase,
   decrease past zero, remove, checkout, and the empty-cart state.
4. An injected product row containing `<script>` confirms output escaping,
   and a hostile id string confirms the products table survives.
5. Both pages return HTTP 200 with no PHP warnings or notices; screenshots are
   taken from that verified render.

## Carried into Week 4

The front controller changes document-relative paths in the shared header, the
test suite needs pointing at the new model classes, and the POST handler has no
CSRF token — noted as a Week 5 hardening candidate rather than a Week 3 gap.
