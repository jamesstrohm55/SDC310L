# Week 4 Design — Applying the MVC Framework

**Project:** SDC310L Course Project — Summit Outfitters Online Store
**Student:** James Strohm (jamstr441)
**Date:** 2026-08-22
**Milestone:** Week 4 — Architecture: Applying MVC
**Branch:** `week-4` (off `main`, which carries the merged Week 3 work)

---

## 1. Purpose and scope

Week 3 delivered a working store: a database-backed catalog, a session cart,
and correct order totals. It works, but the responsibilities are tangled.
`index.php` opens a database connection, runs a query, computes money, and
emits HTML. `cart-action.php` mixes request parsing, business rules, and
redirects. A page is simultaneously the router, the controller, the model, and
the view.

Week 4 separates those responsibilities into Model, View, and Controller
without changing a single behavior a visitor can observe. This is the
definition of done: **the application does exactly what it did at the `Phase3`
tag, and the code is organized differently.**

### In scope

- A single front controller (`index.php`) that routes every request
- Model classes for products, the cart, money, and session storage
- Controller classes that translate requests into model calls and view data
- View templates that render passed-in data and nothing else
- Porting the existing test suite onto the new classes, plus new router tests
- Two latent defects fixed, both of which the new structure makes natural
  (see §7)

### Out of scope

- Any change to the database schema
- Any change to the visual design or the stylesheet beyond path corrections
- CSRF tokens (noted in the Week 3 plan as a Week 5 hardening candidate)
- Product administration, user accounts, order persistence

### Non-goal

Building a general-purpose framework. The routing, view rendering, and
autoloading here are the smallest implementations that give real separation.
There is no dependency injection container, no abstract base controller, no
Request/Response object hierarchy. Those would be scaffolding this application
has no second consumer for.

---

## 2. Re-architecture approach: in place

The re-architecture is done **in place on a branch**, not as a new project.

The Week 3 code is not being thrown away — it is being redistributed. The cart
rules, the money math, and the PDO access are already correct and already
covered by 71 test assertions. Starting a new project would mean rewriting
verified logic from memory, and the test suite would have nothing to run
against until the rewrite was finished. Refactoring in place keeps the suite
runnable at every step, so a regression announces itself immediately rather
than at the end.

Git makes this safe: the Week 3 application is permanently reachable at the
`Phase3` tag and on `main`, so "delete the old file" costs nothing.

---

## 3. Architecture

### 3.1 Request flow

```
GET /SDC310L/index.php?action=cart
  |
  index.php ......................... front controller
    |-- require core/bootstrap.php ... autoloader, error mode, session start
    |-- Router::resolve('cart','GET') -> ['CartController','index']
    |-- new CartController(...)
    |     `-- index()
    |           |-- SessionCart::load() ............... Cart model
    |           |-- (new Product($pdo))->byIds($ids) .. Product model
    |           |-- $cart->lines($products)
    |           |-- Cart::totals($lines)
    |           `-- returns ['view'=>'cart/index','data'=>[...]]
    `-- View::render('cart/index', $data) -> wrapped in views/layout.php
```

### 3.2 Layer contract

| Layer | May do | May never do |
|---|---|---|
| Model | Query the database, apply rules, compute money, read/write the session | Emit HTML, read `$_GET`/`$_POST`, redirect |
| Controller | Read request input, call models, choose a view or ask for a redirect | Run SQL, emit HTML, send headers, format currency for display |
| View | Render passed-in data, escape it | Query, mutate state, read superglobals |
| Router | Map an action + verb to a controller method | Anything else |

The one deliberate exception: `models/Money.php` exposes `format()`, which
views call to render cents as `1,234.56`. That is a formatting utility, not
business logic, and the alternative — pre-formatting every figure in the
controller — would push presentation concerns up a layer.

### 3.3 File layout

```
index.php                      Front controller: bootstrap, resolve, dispatch, render
config/database.php            PDO connection factory (unchanged from Week 3)

core/bootstrap.php             Autoloader registration, error settings, session start
core/Router.php                Route table and pure resolve()
core/View.php                  Template rendering into the layout; e() escape helper

controllers/CatalogController.php
controllers/CartController.php

models/Product.php             Product database access
models/Cart.php                Cart quantity rules, line building, order totals
models/Money.php               Cents conversion and display formatting
models/SessionCart.php         The only file that touches $_SESSION

views/layout.php               Document shell, header, navigation, footer
views/catalog/index.php        Catalog table
views/cart/index.php           Cart table and order summary
views/error/not-found.php      404 body

css/style.css                  Unchanged
database/onlinestore.sql       Unchanged
tests/                         run.php, lib.php, test_cart.php, test_products.php,
                               test_money.php, test_router.php
```

**Removed:** `cart.php`, `cart-action.php`, `includes/cart.php`,
`includes/products.php`, `includes/session.php`, `includes/header.php`,
`includes/footer.php`. The `includes/` directory ceases to exist.

### 3.4 Autoloading

`core/bootstrap.php` registers one `spl_autoload_register` callback that maps a
class name to a file by searching `models/`, `controllers/`, and `core/` in
that order. Class names are restricted to `[A-Za-z_][A-Za-z0-9_]*` before being
used in a path, so a class name can never escape those directories.

No Composer. The project is graded on a stock XAMPP install and adding a
dependency manager for a seven-class application is not a trade worth making.

---

## 4. Routing

### 4.1 Route table

Routing is a whitelist. An action that is not in the table does not reach any
controller.

| `?action=` | Controller | Method | Verb |
|---|---|---|---|
| *(absent)* or `catalog` | `CatalogController` | `index` | GET |
| `cart` | `CartController` | `index` | GET |
| `cart.add` | `CartController` | `add` | POST |
| `cart.remove` | `CartController` | `remove` | POST |
| `cart.increase` | `CartController` | `increase` | POST |
| `cart.decrease` | `CartController` | `decrease` | POST |
| `cart.checkout` | `CartController` | `checkout` | POST |

The dotted names group cart mutations visibly under the cart resource while
staying a single flat query-string value, which keeps the front controller a
lookup rather than a parser.

### 4.2 Resolution rules

`Router::resolve(?string $action, string $method): ?array` is a pure function —
input in, route or `null` out, no superglobals, no side effects — so it is unit
testable. The front controller applies the outcome:

| Condition | Outcome |
|---|---|
| Action absent or empty | Treated as `catalog` |
| Action in table, verb matches | Dispatch |
| Action in table, verb is wrong | HTTP 303 to the catalog |
| Action not in table | HTTP 404, render `views/error/not-found.php` |

The verb-mismatch redirect preserves the Post/Redirect/Get guarantee that Week
3 established. A cart mutation reached by GET — a stray link, a bookmarked
form target, a browser refresh of a POST that was already applied — changes
nothing and returns the visitor to the catalog.

### 4.3 Controller return contract

Every controller method returns one of exactly two shapes:

```php
['view' => 'cart/index', 'data' => [...]]   // render this template
['redirect' => 'cart']                       // 303 to this route name
```

The controller therefore never calls `header()` or `exit` itself, which is what
lets controller methods be reasoned about — and, if useful later, tested —
without a live request.

---

## 5. Models

### 5.1 `Product`

Constructed with a PDO connection; encapsulates every SQL statement in the
application.

| Method | Returns |
|---|---|
| `all(): array` | Every catalog row, ordered by `product_id` |
| `byId(int $id): ?array` | One row, or `null` if no such product |
| `byIds(array $ids): array` | Rows keyed by `product_id`, unknown ids absent |

Rows stay associative arrays with the four catalog columns. `product_id` is
cast to `int`; `product_cost` stays the exact `DECIMAL` string MySQL sends, so
no precision is lost before `Money::toCents()` converts it.

Both existing safety properties carry over unchanged: every statement is
prepared with bound values, and ids are cast to `int` before reaching the
`IN (...)` list so a non-numeric payload becomes `0` and matches nothing.

### 5.2 `Cart`

A stateful model holding `array<int,int> $items` as `product_id => quantity`.
Constructed from a plain array — `new Cart([1 => 2])` — so every rule is
testable with no session and no HTTP request, exactly as the Week 3 pure
functions were.

| Method | Behavior |
|---|---|
| `add(int $id, int $qty = 1)` | Adds to the existing quantity |
| `setQuantity(int $id, int $qty)` | Absolute set; `<= 0` removes the line |
| `adjust(int $id, int $delta)` | Moves by a delta, clamped at 0 |
| `remove(int $id)` | Drops the line whatever its quantity |
| `clear()` | Empties the cart |
| `quantity(int $id): int` | 0 when absent |
| `itemCount(): int` | Sum of quantities |
| `isEmpty(): bool` | |
| `items(): array` | The raw map, for storage and for `Product->byIds()` |
| `lines(array $products): array` | Joins the cart against catalog rows |
| `static totals(array $lines): array` | Items total, tax, shipping, order total |

Mutators change `$this` and return `void`. This is the one deliberate
divergence from Week 3, where the functions were pure and returned a new cart.
An object that holds state and mutates it is what makes this a model rather
than a namespace of functions, and testability is preserved by the constructor
taking a plain array. `totals()` is `static` because it is a pure computation
over lines and depends on no instance state.

Class constants `Cart::TAX_RATE = 0.05` and `Cart::SHIPPING_RATE = 0.10` carry
the Course Project Overview rules.

A cart entry whose product is no longer in the catalog is skipped by `lines()`
rather than fataling, so a stale session cannot break the page.

### 5.3 `Money`

Two static methods, no state:

- `toCents(string $amount): int` — `'129.99'` becomes `12999`. Rounding after
  the multiply absorbs the one-ulp error from reading the decimal string as a
  float.
- `format(int $cents): string` — `58533` becomes `'585.33'`, with thousands
  separators.

All currency arithmetic remains in whole integer cents. Tax and shipping are
each rounded to the cent independently and the order total is the sum of the
three rounded figures, so the printed lines always add up to the printed total.

### 5.4 `SessionCart`

The only file in the application that names `$_SESSION`.

| Method | Behavior |
|---|---|
| `start(): void` | Starts a session unless one is running |
| `load(): Cart` | Reads the stored map, re-casting and discarding invalid entries, and returns a `Cart` |
| `save(Cart $cart): void` | Writes `$cart->items()` back |
| `flashSet(string $m): void` | Stores a one-time message |
| `flashTake(): ?string` | Reads and clears it |

`load()` re-casts on the way out so a session written by an older build, or
hand-edited, cannot feed non-integers into the cart rules.

---

## 6. Controllers

### 6.1 `CatalogController::index` (GET)

Loads the cart from the session, fetches every product, and returns the catalog
view with the products, the cart (so each row can show its current quantity),
the item count for the nav badge, and any flash message.

### 6.2 `CartController`

`index` (GET) loads the cart, fetches only the products actually in it — the
query stays proportional to the order rather than the catalog — builds the
lines and totals, and returns the cart view.

The five mutation methods each read `product_id` from the POST body, apply one
model call, save, and return a redirect:

| Method | Model call | Redirects to |
|---|---|---|
| `add` | `$cart->add($id)` | the submitted return route |
| `remove` | `$cart->remove($id)` | the submitted return route |
| `increase` | `$cart->adjust($id, 1)` | the submitted return route |
| `decrease` | `$cart->adjust($id, -1)` | the submitted return route |
| `checkout` | `$cart->clear()` + flash | always `catalog` |

**Return-route safety.** The `return` field is validated against the route
names `catalog` and `cart`; anything else falls back to `catalog`. This is the
Week 3 open-redirect guard restated in the new vocabulary — previously it
whitelisted the filenames `index.php` and `cart.php`. Echoing an arbitrary
submitted value into a `Location` header would let a crafted form redirect
visitors off-site.

---

## 7. Defects fixed by the restructure

Two problems in the Week 3 code are corrected here. Both are fixed because the
new structure makes the fix natural, not as unrelated opportunistic changes.

**7.1 Nonexistent products can enter the cart.** `cart-action.php` adds
whatever `product_id` it is given. Nothing checks the product exists. The id
sits in the session, and the cart page silently skips it, so the visitor's
"Add to Cart" appears to do nothing with no explanation. `Product->byId()`
gives `CartController::add` a real existence check: an unknown id is rejected
and the cart is left untouched. This is also what justifies `byId()` existing —
the Week 4 plan promised the method, and this is its caller.

**7.2 The footer states the wrong milestone.** `includes/footer.php` describes
the Week 3 build. `views/layout.php` describes Week 4.

---

## 8. Path handling under a single entry point

The Week 4 plan anticipated this as unplanned work, and it is the change most
likely to break silently, because a wrong relative path produces an unstyled
page rather than an error.

Every URL the application emits changes:

| Week 3 | Week 4 |
|---|---|
| `href="index.php"` | `href="index.php"` (unchanged) |
| `href="cart.php"` | `href="index.php?action=cart"` |
| `action="cart-action.php"` + `name="action"` button values | `action="index.php?action=cart.add"` etc. |
| `name="return" value="index.php"` | `name="return" value="catalog"` |

The stylesheet link stays `css/style.css`. The front controller occupies the
same URL path `index.php` already did, so the document base remains
`/SDC310L/` whether the request is `/SDC310L/`, `/SDC310L/index.php`, or
`/SDC310L/index.php?action=cart`, and the relative path still resolves to
`/SDC310L/css/style.css`.

That reasoning is not sufficient evidence. Verification (§10) fetches the
rendered HTML of every route and confirms the stylesheet resolves to HTTP 200,
rather than inferring it from how relative URLs are supposed to work.

Because the mutation verb now lives in the query string rather than in a
button's `value`, the two-button quantity control becomes two separate
single-button forms. Nested forms are invalid HTML, so the buttons sit as
siblings within a flex container that reproduces the current layout. This is a
markup change with no visual change, and it is the one place where "no
observable behavior changes" needs checking against a rendered page rather than
against the test suite.

---

## 9. Testing

Per the project's working agreement, each unit is written test-first: the
assertions move to the new class, the suite is run and watched to fail with a
clear "class not found" error, and only then is the class implemented.

| Suite | Covers |
|---|---|
| `test_cart.php` | Quantity rules, clamping, line building, totals, rounding — ported from Week 3 |
| `test_products.php` | `all()`, `byId()`, `byIds()`, prepared-statement safety — ported, plus `byId()` cases |
| `test_money.php` | `toCents()` and `format()`, split out of the Week 3 cart tests |
| `test_router.php` | Resolution, default action, unknown action, verb mismatch, table completeness |

`test_products.php` remains an integration test against the real `onlinestore`
database and fails loudly if the database has not been imported, rather than
silently passing.

The suite must finish with a **non-zero exit code on any failure**, and the
exit code is read directly rather than through a pipe, because a pipeline
returns the exit status of its last command and would report a failing suite as
success.

Target: at least the 71 Week 3 assertions, plus the new router, `byId()`, and
money cases.

---

## 10. Verification before completion

No claim of "done" is made until each of these has been run and its actual
output read.

1. `php -l` on every PHP file in the project — zero syntax errors.
2. `php tests/run.php`; exit code checked explicitly and confirmed `0`.
3. Every one of the seven routes fetched over HTTP through Apache, status codes
   recorded: 200 for the two GET pages, 303 for the five POST actions, 404 for
   an unknown action, 303 for a POST route reached by GET.
4. `css/style.css` fetched as the rendered page links it — HTTP 200, so the
   front controller did not break the stylesheet path.
5. A full cart walk through Apache with a cookie jar: add, increase, decrease,
   remove, add several, check out. Line totals and the order total are read out
   of the response body and checked against hand-computed figures.
6. The rejected-unknown-product path exercised: posting `product_id=9999`
   leaves the cart unchanged.
7. Apache's `error_log` inspected after the walk — no PHP warnings or notices.
8. The live database schema compared against `database/onlinestore.sql` to
   confirm the checked-in export is still current.

---

## 11. Database export

The schema is unchanged this week. Week 4 is a code re-architecture; no table,
column, type, or constraint is added, dropped, or altered, and the seed data is
untouched. `database/onlinestore.sql` therefore remains the current export.
Step 10.8 verifies that claim against the live database rather than asserting
it from memory.

---

## 12. Definition of done

- Every request enters through `index.php`; no other PHP file is reachable as a
  page
- No model emits HTML; no view queries the database; no controller runs SQL
- The test suite passes with exit code 0 and covers the router
- Every route verified over HTTP with the status codes above
- A full cart flow produces the same figures as the `Phase3` build
- `README.md` updated to describe the MVC structure
- Committed on `week-4`, tagged `Phase4`, pushed, pull request opened
- Project plan updated: Week 4 sections I–V, and Week 5 Additional Work
