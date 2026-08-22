# Week 4 MVC Re-Architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Re-architect the Summit Outfitters store into Model-View-Controller with a single front controller, changing no behavior a visitor can observe.

**Architecture:** Refactor in place on the `week-4` branch. Every new file (`core/`, `models/`, `controllers/`, `views/`) is created alongside the working Week 3 application, so the site keeps serving and the test suite keeps passing at every commit. Task 9 then swaps `index.php` for the front controller and deletes the Week 3 page scripts in one atomic change.

**Tech Stack:** PHP 8.2 (XAMPP `/Applications/XAMPP/xamppfiles/bin/php`), MariaDB via PDO, Apache. No Composer, no PHPUnit — the project's own `tests/lib.php` assertion harness.

**Spec:** `docs/superpowers/specs/2026-08-22-week4-mvc-architecture-design.md`

## Global Constraints

- Every PHP file starts with `<?php` and `declare(strict_types=1);` (view templates excepted — they open with `<?php` only where they need code).
- All currency arithmetic is in whole integer cents. `product_cost` stays the exact `DECIMAL` string from MySQL until `Money::toCents()` converts it.
- Tax rate 5%, shipping & handling 10%, each rounded to the cent independently; order total is the sum of the three rounded figures.
- No model emits HTML. No view queries the database. No controller runs SQL or sends headers.
- `models/SessionCart.php` is the only file permitted to name `$_SESSION`.
- Every SQL statement is prepared with bound values; ids are cast to `int` before reaching a query.
- Every value interpolated into HTML goes through `e()`.
- The test runner is `/Applications/XAMPP/xamppfiles/bin/php tests/run.php`. **Never pipe it** — a pipeline returns the last command's exit status and would report a failing suite as success. Read `$?` directly.
- Regression bar to beat: Week 3 baseline is 71 assertions passing, exit 0, `GET /SDC310L/` → 200, six seeded products.
- No schema change. `database/onlinestore.sql` is not edited.

---

### Task 1: `Money` model

Smallest unit, no dependencies. Splits currency conversion out of the cart so both models can use it.

**Files:**
- Create: `models/Money.php`
- Create: `tests/test_money.php`
- Modify: `tests/run.php` (add the new suite to the list)

**Interfaces:**
- Consumes: nothing
- Produces: `Money::toCents(string $amount): int`, `Money::format(int $cents): string`

- [ ] **Step 1: Write the failing test**

Create `tests/test_money.php`:

```php
<?php
/**
 * Tests for currency conversion and display formatting.
 *
 * All money in this application is whole integer cents. These two functions
 * are the only places a value crosses between cents and something else, so
 * they are where a rounding error would enter.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
describe('Money::toCents');

assert_same(12999, Money::toCents('129.99'), 'converts an exact decimal string to cents');
assert_same(24900, Money::toCents('249.00'), 'converts a whole-dollar amount');
assert_same(0, Money::toCents('0.00'), 'converts zero');
assert_same(5, Money::toCents('0.05'), 'converts an amount under a dime');
assert_same(2850, Money::toCents('28.50'), 'converts an amount ending in a zero cent');

// 129.99 has no exact binary representation, so a bare (int) cast of
// 129.99 * 100 truncates to 12998. Rounding after the multiply is what
// keeps the conversion honest.
assert_same(12999, Money::toCents('129.99'), 'rounding absorbs float representation error');
assert_same(1899, Money::toCents('18.99'), 'another amount that truncates without rounding');

// ---------------------------------------------------------------------------
describe('Money::format');

assert_same('0.00', Money::format(0), 'formats zero cents');
assert_same('5.09', Money::format(509), 'formats cents under a dollar boundary');
assert_same('585.33', Money::format(58533), 'formats the worked order total');
assert_same('1,234.56', Money::format(123456), 'groups thousands with a comma');
assert_same('0.05', Money::format(5), 'pads a single-digit cent value');
```

Add the suite to `tests/run.php` — the `$suites` array becomes:

```php
$suites = [
    __DIR__ . '/test_money.php',
    __DIR__ . '/test_cart.php',
    __DIR__ . '/test_products.php',
];
```

- [ ] **Step 2: Run the suite and watch it fail**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/SDC310L
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: a fatal `Error: Class "Money" not found` in `test_money.php`, and a non-zero exit. If it fails for any other reason, stop and find out why before writing code.

- [ ] **Step 3: Write the implementation**

Create `models/Money.php`:

```php
<?php
/**
 * Currency conversion and display formatting.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * All money in this application is held as whole integer cents. These two
 * methods are the only crossings between cents and another representation:
 * toCents() on the way in from the database, format() on the way out to a
 * page. Keeping both here means neither the Cart nor a view re-implements
 * the rounding rule.
 */

declare(strict_types=1);

final class Money
{
    /**
     * Convert an exact DECIMAL string from the database ('129.99') to cents.
     *
     * Rounding after the multiply absorbs the one-ulp error that reading the
     * string as a float introduces: 129.99 is not exactly representable in
     * binary, so a bare (int) cast of 129.99 * 100 truncates to 12998.
     */
    public static function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Format whole cents for display: 58533 becomes "585.33". */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
```

The class is not yet autoloaded, so add a direct require at the top of `tests/test_money.php`, immediately after the `declare` line:

```php
require_once __DIR__ . '/../models/Money.php';
```

(Task 6 introduces the autoloader and this require is removed then.)

- [ ] **Step 4: Run the suite and watch it pass**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: `83 passed, 0 failed` and `exit=0`. The 71 Week 3 assertions still run — the old `includes/` files are untouched.

- [ ] **Step 5: Lint and commit**

```bash
/Applications/XAMPP/xamppfiles/bin/php -l models/Money.php
/Applications/XAMPP/xamppfiles/bin/php -l tests/test_money.php
git add models/Money.php tests/test_money.php tests/run.php
git commit -m "Week 4: add Money model for cents conversion and formatting"
```

---

### Task 2: `Cart` model

Converts the Week 3 pure cart functions into a stateful model. This is the largest behavioral surface in the application.

**Files:**
- Create: `models/Cart.php`
- Rewrite: `tests/test_cart.php` (targets the class instead of the functions)

**Interfaces:**
- Consumes: `Money::toCents()` from Task 1
- Produces:
  - `new Cart(array $items = [])` — sanitizes on construction
  - `Cart::TAX_RATE` (0.05), `Cart::SHIPPING_RATE` (0.10)
  - `items(): array<int,int>`, `isEmpty(): bool`, `quantity(int): int`, `itemCount(): int`
  - `add(int $productId, int $quantity = 1): void`
  - `setQuantity(int $productId, int $quantity): void`
  - `adjust(int $productId, int $delta): void`
  - `remove(int $productId): void`, `clear(): void`
  - `lines(array $products): array` — list of `['product_id','product_name','quantity','cost_cents','line_total_cents']`
  - `static totals(array $lines): array` — `['items_total_cents','tax_cents','shipping_cents','order_total_cents']`

- [ ] **Step 1: Write the failing test**

Replace the whole of `tests/test_cart.php`:

```php
<?php
/**
 * Tests for the Cart model: quantity rules, line building, and order totals.
 *
 * Cart is constructed from a plain array, so every rule is exercised with no
 * session and no web request. That is the property worth protecting: the
 * quantity and money rules must stay testable without HTTP.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Money.php';
require_once __DIR__ . '/../models/Cart.php';

// Stand-in catalog, keyed by product_id, matching what Product::byIds returns.
$catalog = [
    1 => ['product_id' => 1, 'product_name' => 'Trailhead 45L Backpack', 'product_cost' => '129.99'],
    3 => ['product_id' => 3, 'product_name' => 'Cascade 2-Person Tent',  'product_cost' => '249.00'],
    5 => ['product_id' => 5, 'product_name' => 'Summit LED Headlamp',    'product_cost' => '39.99'],
];

// ---------------------------------------------------------------------------
describe('Cart construction');

assert_same([], (new Cart())->items(), 'a cart with no arguments starts empty');
assert_same([2 => 3], (new Cart([2 => 3]))->items(), 'a cart is seeded from a plain array');
assert_true((new Cart())->isEmpty(), 'a new cart reports itself empty');
assert_same(false, (new Cart([2 => 1]))->isEmpty(), 'a seeded cart does not report itself empty');

// The constructor is the sanitizing boundary: session data and hand-edited
// input reach the model here and must not survive as non-integers.
assert_same([2 => 3], (new Cart(['2' => '3']))->items(), 'string keys and values are cast to integers');
assert_same([], (new Cart([2 => 0]))->items(), 'a zero quantity is not a cart line');
assert_same([], (new Cart([2 => -4]))->items(), 'a negative quantity is discarded');
assert_same([], (new Cart([0 => 2]))->items(), 'a product id of zero is discarded');
assert_same([], (new Cart([-1 => 2]))->items(), 'a negative product id is discarded');
assert_same([2 => 1], (new Cart([2 => 1, 0 => 9]))->items(), 'invalid entries are dropped without losing valid ones');

// ---------------------------------------------------------------------------
describe('Cart::add');

$cart = new Cart();
$cart->add(2);
assert_same([2 => 1], $cart->items(), 'adds a product with quantity 1 by default');

$cart = new Cart();
$cart->add(2, 3);
assert_same([2 => 3], $cart->items(), 'adds a product with an explicit quantity');

$cart = new Cart([2 => 1]);
$cart->add(2, 3);
assert_same([2 => 4], $cart->items(), 'accumulates onto an existing quantity');

$cart = new Cart([2 => 1]);
$cart->add(7);
assert_same([2 => 1, 7 => 1], $cart->items(), 'leaves other products untouched');

$cart = new Cart();
$cart->add(2, 0);
assert_same([], $cart->items(), 'adding zero does not create a line');

$cart = new Cart();
$cart->add(2, -5);
assert_same([], $cart->items(), 'adding a negative quantity does not create a line');

// ---------------------------------------------------------------------------
describe('Cart::setQuantity');

$cart = new Cart([2 => 1]);
$cart->setQuantity(2, 5);
assert_same([2 => 5], $cart->items(), 'replaces the quantity outright');

$cart = new Cart();
$cart->setQuantity(2, 4);
assert_same([2 => 4], $cart->items(), 'sets a quantity for a product not yet in the cart');

$cart = new Cart([2 => 3]);
$cart->setQuantity(2, 0);
assert_same([], $cart->items(), 'quantity 0 removes the line');

$cart = new Cart([2 => 3]);
$cart->setQuantity(2, -1);
assert_same([], $cart->items(), 'a negative quantity clamps to 0 and removes the line');

$cart = new Cart([2 => 3, 7 => 2]);
$cart->setQuantity(2, 0);
assert_same([7 => 2], $cart->items(), 'removing one line leaves the others');

// ---------------------------------------------------------------------------
describe('Cart::adjust');

$cart = new Cart([2 => 2]);
$cart->adjust(2, 1);
assert_same([2 => 3], $cart->items(), 'increases the quantity by the delta');

$cart = new Cart([2 => 2]);
$cart->adjust(2, -1);
assert_same([2 => 1], $cart->items(), 'decreases the quantity by the delta');

$cart = new Cart([2 => 1]);
$cart->adjust(2, -1);
assert_same([], $cart->items(), 'decreasing to 0 removes the line');

$cart = new Cart([2 => 1]);
$cart->adjust(2, -9);
assert_same([], $cart->items(), 'quantity never goes below 0');

$cart = new Cart();
$cart->adjust(2, -1);
assert_same([], $cart->items(), 'decreasing a product that is not in the cart is a no-op');

$cart = new Cart();
$cart->adjust(2, 1);
assert_same([2 => 1], $cart->items(), 'increasing a product not in the cart adds it');

// ---------------------------------------------------------------------------
describe('Cart::remove and Cart::clear');

$cart = new Cart([2 => 4]);
$cart->remove(2);
assert_same([], $cart->items(), 'removes the line regardless of quantity');

$cart = new Cart([2 => 4, 7 => 1]);
$cart->remove(2);
assert_same([7 => 1], $cart->items(), 'removes only the requested product');

$cart = new Cart([7 => 1]);
$cart->remove(2);
assert_same([7 => 1], $cart->items(), 'removing an absent product is a no-op');

$cart = new Cart([2 => 4, 7 => 1]);
$cart->clear();
assert_same([], $cart->items(), 'clear empties the whole cart');
assert_true($cart->isEmpty(), 'a cleared cart reports itself empty');

// ---------------------------------------------------------------------------
describe('Cart::quantity and Cart::itemCount');

assert_same(4, (new Cart([2 => 4]))->quantity(2), 'reports the quantity of a product in the cart');
assert_same(0, (new Cart([2 => 4]))->quantity(9), 'reports 0 for a product not in the cart');
assert_same(0, (new Cart())->quantity(1), 'reports 0 for an empty cart');

assert_same(0, (new Cart())->itemCount(), 'an empty cart holds no items');
assert_same(4, (new Cart([2 => 4]))->itemCount(), 'one line counts its quantity');
assert_same(6, (new Cart([2 => 4, 7 => 2]))->itemCount(), 'item count sums quantities across lines');

// ---------------------------------------------------------------------------
describe('Cart::lines');

$lines = (new Cart([1 => 2, 3 => 1]))->lines($catalog);
assert_same(2, count($lines), 'builds one line per cart entry');
assert_same(1, $lines[0]['product_id'], 'line carries the product id');
assert_same('Trailhead 45L Backpack', $lines[0]['product_name'], 'line carries the product name');
assert_same(2, $lines[0]['quantity'], 'line carries the ordered quantity');
assert_same(12999, $lines[0]['cost_cents'], 'unit cost is converted to whole cents');
assert_same(25998, $lines[0]['line_total_cents'], 'line total is unit cost times quantity');
assert_same(24900, $lines[1]['line_total_cents'], 'a quantity of 1 gives a line total of the unit cost');

assert_same([], (new Cart())->lines($catalog), 'an empty cart produces no lines');
assert_same(
    [],
    (new Cart([99 => 2]))->lines($catalog),
    'a cart entry with no matching product is skipped rather than fataling'
);
assert_same(
    1,
    count((new Cart([1 => 2, 99 => 1]))->lines($catalog)),
    'a missing product does not discard the valid lines alongside it'
);

// ---------------------------------------------------------------------------
describe('Cart::totals');

// $129.99 x 2 + $249.00 = $508.98 pre-tax.
$totals = Cart::totals((new Cart([1 => 2, 3 => 1]))->lines($catalog));
assert_same(50898, $totals['items_total_cents'], 'items total is the sum of the line totals');
assert_same(2545, $totals['tax_cents'], 'tax is 5% of the pre-tax total');
assert_same(5090, $totals['shipping_cents'], 'shipping and handling is 10% of the pre-tax total');
assert_same(58533, $totals['order_total_cents'], 'order total is items plus tax plus shipping');

$empty = Cart::totals([]);
assert_same(0, $empty['items_total_cents'], 'an empty cart has a zero items total');
assert_same(0, $empty['tax_cents'], 'an empty cart has zero tax');
assert_same(0, $empty['shipping_cents'], 'an empty cart has zero shipping');
assert_same(0, $empty['order_total_cents'], 'an empty cart has a zero order total');

// $10.10 pre-tax puts tax on an exact half cent (50.5), which is where float
// accumulation would drift. Integer cents plus round() keeps it deterministic.
$halfCent = Cart::totals([['line_total_cents' => 1010]]);
assert_same(51, $halfCent['tax_cents'], 'a half-cent tax rounds up rather than truncating');
assert_same(101, $halfCent['shipping_cents'], 'shipping on the same total rounds exactly');
assert_same(1162, $halfCent['order_total_cents'], 'order total sums the rounded components');

// The printed lines must add up to the printed total — the reason the three
// figures are each rounded independently and then summed.
assert_same(
    $halfCent['order_total_cents'],
    $halfCent['items_total_cents'] + $halfCent['tax_cents'] + $halfCent['shipping_cents'],
    'the printed components always sum to the printed order total'
);

// ---------------------------------------------------------------------------
describe('Cart rate constants');

assert_same(0.05, Cart::TAX_RATE, 'tax rate is 5% per the Course Project Overview');
assert_same(0.10, Cart::SHIPPING_RATE, 'shipping and handling rate is 10%');
```

- [ ] **Step 2: Run the suite and watch it fail**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: `Failed opening required '.../models/Cart.php'` and a non-zero exit.

- [ ] **Step 3: Write the implementation**

Create `models/Cart.php`:

```php
<?php
/**
 * Shopping cart model.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * The cart holds a map of product_id => quantity. It is constructed from a
 * plain array, which is what keeps every rule here testable with no session
 * and no web request; SessionCart is the separate, thin layer that loads one
 * of these in and saves it back out.
 *
 * Money is handled in whole cents throughout. Costs arrive from the database
 * as exact DECIMAL strings and are converted once by Money::toCents(), so no
 * sequence of additions or percentages can accumulate binary float error into
 * an order total.
 */

declare(strict_types=1);

final class Cart
{
    /** Order total rules from the Course Project Overview. */
    public const TAX_RATE      = 0.05;  // 5% of the pre-tax total
    public const SHIPPING_RATE = 0.10;  // 10% of the pre-tax total

    /** @var array<int,int> product_id => quantity */
    private array $items = [];

    /**
     * @param array<int|string, int|string> $items
     */
    public function __construct(array $items = [])
    {
        // The constructor is the sanitizing boundary. Carts arrive from the
        // session, which may have been written by an older build or edited by
        // hand, so nothing is trusted to already be an integer.
        foreach ($items as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity  = (int) $quantity;

            if ($productId > 0 && $quantity > 0) {
                $this->items[$productId] = $quantity;
            }
        }
    }

    /** @return array<int,int> */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function quantity(int $productId): int
    {
        return $this->items[$productId] ?? 0;
    }

    /** Total number of items ordered, counting quantities. */
    public function itemCount(): int
    {
        return array_sum($this->items);
    }

    /** Add to the quantity already in the cart. */
    public function add(int $productId, int $quantity = 1): void
    {
        $this->setQuantity($productId, $this->quantity($productId) + $quantity);
    }

    /**
     * Set an absolute quantity, clamped to 0 or more.
     *
     * Zero is not stored as a line: a product with no quantity is simply not
     * in the cart, which is what lets the cart page show only ordered
     * products. Clamping here rather than in the controller means a forged
     * POST cannot drive a quantity negative.
     */
    public function setQuantity(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($productId);

            return;
        }

        $this->items[$productId] = $quantity;
    }

    /** Move a quantity up or down by a delta, never below 0. */
    public function adjust(int $productId, int $delta): void
    {
        $this->setQuantity($productId, $this->quantity($productId) + $delta);
    }

    /** Drop a product from the cart entirely, whatever its quantity. */
    public function remove(int $productId): void
    {
        unset($this->items[$productId]);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Join the cart against catalog rows to produce display lines.
     *
     * A cart entry whose product is no longer in the catalog is skipped
     * rather than fataling, so a stale session cannot break the page.
     *
     * @param  array<int,array> $products product_id => row, as Product::byIds returns
     * @return list<array{product_id:int, product_name:string, quantity:int,
     *                    cost_cents:int, line_total_cents:int}>
     */
    public function lines(array $products): array
    {
        $lines = [];

        foreach ($this->items as $productId => $quantity) {
            if (!isset($products[$productId])) {
                continue;
            }

            $costCents = Money::toCents((string) $products[$productId]['product_cost']);

            $lines[] = [
                'product_id'       => $productId,
                'product_name'     => (string) $products[$productId]['product_name'],
                'quantity'         => $quantity,
                'cost_cents'       => $costCents,
                'line_total_cents' => $costCents * $quantity,
            ];
        }

        return $lines;
    }

    /**
     * Order totals, in whole cents.
     *
     * Static because it is a pure computation over lines and depends on no
     * instance state.
     *
     * Tax and shipping are each a percentage of the pre-tax total, rounded to
     * the cent independently, and the order total is the sum of the three
     * rounded figures — so the printed lines always add up to the printed
     * total.
     *
     * @param  list<array{line_total_cents:int}> $lines
     * @return array{items_total_cents:int, tax_cents:int,
     *               shipping_cents:int, order_total_cents:int}
     */
    public static function totals(array $lines): array
    {
        $itemsTotal = 0;
        foreach ($lines as $line) {
            $itemsTotal += (int) $line['line_total_cents'];
        }

        $tax      = (int) round($itemsTotal * self::TAX_RATE);
        $shipping = (int) round($itemsTotal * self::SHIPPING_RATE);

        return [
            'items_total_cents' => $itemsTotal,
            'tax_cents'         => $tax,
            'shipping_cents'    => $shipping,
            'order_total_cents' => $itemsTotal + $tax + $shipping,
        ];
    }
}
```

- [ ] **Step 4: Run the suite and watch it pass**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: every assertion passes, `exit=0`. Total should be roughly 100.

- [ ] **Step 5: Confirm the Week 3 site still works**

The old `includes/cart.php` is untouched and still backs the live pages.

```bash
curl -s -o /dev/null -w 'catalog %{http_code}\n' http://localhost/SDC310L/
curl -s -o /dev/null -w 'cart    %{http_code}\n' http://localhost/SDC310L/cart.php
```

Expected: `200` for both.

- [ ] **Step 6: Lint and commit**

```bash
/Applications/XAMPP/xamppfiles/bin/php -l models/Cart.php
/Applications/XAMPP/xamppfiles/bin/php -l tests/test_cart.php
git add models/Cart.php tests/test_cart.php
git commit -m "Week 4: add Cart model with quantity rules and order totals"
```

---

### Task 3: `Product` model

**Files:**
- Create: `models/Product.php`
- Rewrite: `tests/test_products.php`

**Interfaces:**
- Consumes: a `PDO` from `config/database.php`
- Produces:
  - `new Product(PDO $pdo)`
  - `all(): array` — list of rows ordered by `product_id`
  - `byId(int $productId): ?array` — one row or `null`
  - `byIds(array $ids): array` — rows keyed by `product_id`

- [ ] **Step 1: Write the failing test**

Replace the whole of `tests/test_products.php`:

```php
<?php
/**
 * Tests for the Product model.
 *
 * These are integration tests: they run against the real `onlinestore`
 * database created by database/onlinestore.sql. If the database has not been
 * imported, they fail loudly rather than silently passing.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Product.php';

$pdo = require __DIR__ . '/../config/database.php';
$products = new Product($pdo);

// ---------------------------------------------------------------------------
describe('database connection');

assert_true($pdo instanceof PDO, 'config/database.php returns a PDO connection');
assert_same(
    PDO::ERRMODE_EXCEPTION,
    $pdo->getAttribute(PDO::ATTR_ERRMODE),
    'the connection throws on error rather than failing silently'
);
// PDO reports this attribute as int 0 rather than bool false, so the cast is
// the honest assertion: what matters is that emulation is off.
assert_same(
    false,
    (bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES),
    'prepared statements are sent to the server rather than emulated'
);

// ---------------------------------------------------------------------------
describe('Product::all');

$all = $products->all();

assert_true(count($all) >= 5, 'the catalog holds at least the five required products');
assert_same(6, count($all), 'the seeded catalog holds six products');

$first = $all[0];
assert_same(1, $first['product_id'], 'rows come back ordered by product id');
assert_same('Trailhead 45L Backpack', $first['product_name'], 'the product name is read from the database');
assert_same('129.99', $first['product_cost'], 'the cost is read as an exact decimal string, not a float');
assert_true(
    is_string($first['product_description']) && $first['product_description'] !== '',
    'the product description is populated'
);
assert_same(
    ['product_id', 'product_name', 'product_description', 'product_cost'],
    array_keys($first),
    'only the four catalog columns are selected'
);

$ids = array_column($all, 'product_id');
assert_same([1, 2, 3, 4, 5, 6], $ids, 'every seeded product id is present, in order');
assert_true(
    $ids === array_values(array_unique($ids)),
    'no product is returned twice'
);

// ---------------------------------------------------------------------------
describe('Product::byId');

$one = $products->byId(3);
assert_true(is_array($one), 'a known id returns a row');
assert_same(3, $one['product_id'], 'the row is the requested product');
assert_same('Cascade 2-Person Tent', $one['product_name'], 'the row carries the product name');
assert_same('249.00', $one['product_cost'], 'the row carries the exact cost');

assert_same(null, $products->byId(9999), 'an unknown id returns null rather than an empty array');
assert_same(null, $products->byId(0), 'a zero id returns null');
assert_same(null, $products->byId(-1), 'a negative id returns null');

// ---------------------------------------------------------------------------
describe('Product::byIds');

$some = $products->byIds([3, 1]);

assert_same(2, count($some), 'returns exactly the requested products');
assert_same([1, 3], array_keys($some), 'the result is keyed by product id for direct lookup');
assert_same('Cascade 2-Person Tent', $some[3]['product_name'], 'the keyed row is the right product');
assert_same('249.00', $some[3]['product_cost'], 'the keyed row carries the exact cost');

assert_same([], $products->byIds([]), 'an empty id list returns an empty array without querying');
assert_same([], $products->byIds([9999]), 'unknown ids return no rows rather than erroring');
assert_same(
    [1],
    array_keys($products->byIds([1, 9999])),
    'a mix of known and unknown ids returns only the known ones'
);

// A cart id arriving from a request is a string; it must still match.
assert_same(
    [1],
    array_keys($products->byIds(['1'])),
    'string ids from request input are coerced and still match'
);

// ---------------------------------------------------------------------------
describe('sql injection safety');

// byIds builds an IN (...) list, which is the one place a naive
// implementation would interpolate. Two defenses have to hold: ids are cast to
// int before the query, and the values are bound rather than concatenated.

// A wholly non-numeric payload casts to 0 and therefore matches nothing.
assert_same(
    [],
    $products->byIds(["'); DROP TABLE products; --"]),
    'a non-numeric hostile id matches no product'
);

// A payload with a leading digit casts to that digit — the SQL tail is
// discarded by the cast, so it degrades to an ordinary lookup of product 1
// rather than executing anything.
assert_same(
    [1],
    array_keys($products->byIds(['1); DROP TABLE products; --'])),
    'a hostile id with a numeric prefix degrades to that plain id'
);

// byId takes an int parameter, so strict_types rejects a string outright;
// the surviving risk is a hostile value already cast upstream, which is a
// plain integer lookup.
assert_same(null, $products->byId((int) "9999); DROP TABLE products; --"), 'a cast hostile id is an ordinary lookup');

assert_same(6, count($products->all()), 'the products table survived every hostile id');
```

- [ ] **Step 2: Run the suite and watch it fail**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: `Failed opening required '.../models/Product.php'`, non-zero exit.

- [ ] **Step 3: Write the implementation**

Create `models/Product.php`:

```php
<?php
/**
 * Product catalog model.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Every SQL statement in the application lives here. Each one is a prepared
 * statement with bound values. Costs are returned as the exact DECIMAL
 * strings MySQL sends ('129.99'), not floats, so no precision is lost before
 * Money::toCents() converts them.
 */

declare(strict_types=1);

final class Product
{
    private const COLUMNS = 'product_id, product_name, product_description, product_cost';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Every product in the catalog, ordered by id.
     *
     * @return list<array{product_id:int, product_name:string,
     *                    product_description:?string, product_cost:string}>
     */
    public function all(): array
    {
        $sql  = 'SELECT ' . self::COLUMNS . ' FROM products ORDER BY product_id';
        $rows = $this->pdo->query($sql)->fetchAll();

        return array_map([self::class, 'normalize'], $rows);
    }

    /**
     * One product, or null if no such product exists.
     *
     * Null rather than an empty array so a caller cannot mistake "no such
     * product" for "a product with no fields" — the add-to-cart path depends
     * on telling those apart.
     *
     * @return array{product_id:int, product_name:string,
     *               product_description:?string, product_cost:string}|null
     */
    public function byId(int $productId): ?array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM products WHERE product_id = ? LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([$productId]);
        $row = $statement->fetch();

        return $row === false ? null : self::normalize($row);
    }

    /**
     * The requested products, keyed by product id for direct lookup.
     *
     * Unknown ids are simply absent from the result; they are not an error,
     * since a cart can outlive a product being deleted from the catalog.
     *
     * @param  array<int|string> $ids
     * @return array<int, array{product_id:int, product_name:string,
     *                          product_description:?string, product_cost:string}>
     */
    public function byIds(array $ids): array
    {
        // Ids arrive from the session or a request, so they may be strings.
        // Casting first means a non-numeric id becomes 0 and matches nothing,
        // rather than reaching the query as text.
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        // One placeholder per id: the values are bound, never interpolated.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT ' . self::COLUMNS . ' FROM products'
             . ' WHERE product_id IN (' . $placeholders . ')'
             . ' ORDER BY product_id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($ids);

        $byId = [];
        foreach ($statement->fetchAll() as $row) {
            $row = self::normalize($row);
            $byId[$row['product_id']] = $row;
        }

        return $byId;
    }

    /**
     * Give a raw database row consistent PHP types.
     *
     * MySQL hands back every column as a string over this driver; product_id
     * is an integer everywhere else in the application, and product_cost stays
     * a string so its decimal precision survives.
     */
    private static function normalize(array $row): array
    {
        $row['product_id'] = (int) $row['product_id'];

        return $row;
    }
}
```

- [ ] **Step 4: Run the suite and watch it pass**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: all pass, `exit=0`.

- [ ] **Step 5: Confirm the products table survived the hostile-id tests**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "SELECT COUNT(*) FROM onlinestore.products;"
```

Expected: `6`.

- [ ] **Step 6: Lint and commit**

```bash
/Applications/XAMPP/xamppfiles/bin/php -l models/Product.php
/Applications/XAMPP/xamppfiles/bin/php -l tests/test_products.php
git add models/Product.php tests/test_products.php
git commit -m "Week 4: add Product model encapsulating all database access"
```

---

### Task 4: `SessionCart` model

**Files:**
- Create: `models/SessionCart.php`
- Create: `tests/test_session_cart.php`
- Modify: `tests/run.php`

**Interfaces:**
- Consumes: `Cart` from Task 2
- Produces: `SessionCart::start(): void`, `load(): Cart`, `save(Cart): void`, `flashSet(string): void`, `flashTake(): ?string`

**Note on testing this:** `$_SESSION` is an ordinary superglobal array. A CLI test can assign to it directly without ever calling `session_start()`, so load/save/flash are testable without a web request. `start()` itself is not exercised — it is one guarded call to a PHP builtin.

- [ ] **Step 1: Write the failing test**

Create `tests/test_session_cart.php`:

```php
<?php
/**
 * Tests for session storage of the cart.
 *
 * $_SESSION is an ordinary superglobal array, so these tests assign to it
 * directly and never start a real session. That is the point of keeping this
 * class thin: the storage layer is checkable without a web request.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Money.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/SessionCart.php';

// ---------------------------------------------------------------------------
describe('SessionCart::load');

$_SESSION = [];
assert_same([], SessionCart::load()->items(), 'an absent session key loads an empty cart');
assert_true(SessionCart::load() instanceof Cart, 'load always returns a Cart object');

$_SESSION = ['cart' => [2 => 3, 7 => 1]];
assert_same([2 => 3, 7 => 1], SessionCart::load()->items(), 'a stored cart is loaded back');

// A session written by an older build, or hand-edited, must not reach the
// cart rules as non-integers. Cart's constructor is the sanitizer; this
// confirms SessionCart routes through it.
$_SESSION = ['cart' => ['2' => '3', '0' => '9', '5' => '-1']];
assert_same([2 => 3], SessionCart::load()->items(), 'invalid stored entries are discarded on load');

$_SESSION = ['cart' => 'not an array'];
assert_same([], SessionCart::load()->items(), 'a non-array session value loads an empty cart');

$_SESSION = ['cart' => 42];
assert_same([], SessionCart::load()->items(), 'a scalar session value loads an empty cart');

// ---------------------------------------------------------------------------
describe('SessionCart::save');

$_SESSION = [];
SessionCart::save(new Cart([4 => 2]));
assert_same(['cart' => [4 => 2]], $_SESSION, 'save writes the cart items under the cart key');

SessionCart::save(new Cart());
assert_same(['cart' => []], $_SESSION, 'saving an empty cart clears the stored items');

$_SESSION = ['cart' => [1 => 1], 'flash' => 'keep me'];
SessionCart::save(new Cart([9 => 5]));
assert_same('keep me', $_SESSION['flash'], 'save does not disturb other session keys');
assert_same([9 => 5], $_SESSION['cart'], 'save replaces the stored cart outright');

// A save followed by a load must produce an equivalent cart.
$_SESSION = [];
SessionCart::save(new Cart([3 => 4, 6 => 1]));
assert_same([3 => 4, 6 => 1], SessionCart::load()->items(), 'a saved cart round-trips through load');

// ---------------------------------------------------------------------------
describe('SessionCart flash messages');

$_SESSION = [];
assert_same(null, SessionCart::flashTake(), 'taking an absent flash returns null');

SessionCart::flashSet('Thank you for your order.');
assert_same('Thank you for your order.', $_SESSION['flash'], 'flashSet stores the message');
assert_same('Thank you for your order.', SessionCart::flashTake(), 'flashTake returns the message');
assert_same(null, SessionCart::flashTake(), 'a flash message is consumed by the first take');
assert_true(!isset($_SESSION['flash']), 'flashTake unsets the session key');

$_SESSION = [];
```

Add it to `tests/run.php`:

```php
$suites = [
    __DIR__ . '/test_money.php',
    __DIR__ . '/test_cart.php',
    __DIR__ . '/test_session_cart.php',
    __DIR__ . '/test_products.php',
];
```

- [ ] **Step 2: Run the suite and watch it fail**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: `Failed opening required '.../models/SessionCart.php'`, non-zero exit.

- [ ] **Step 3: Write the implementation**

Create `models/SessionCart.php`:

```php
<?php
/**
 * Session storage for the cart.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * This is the only file in the application that names $_SESSION. The cart
 * rules themselves live in Cart, which is constructed from a plain array and
 * therefore stays testable without a web request; this class is the thin
 * layer that loads one in and saves it back out.
 */

declare(strict_types=1);

final class SessionCart
{
    private const CART_KEY  = 'cart';
    private const FLASH_KEY = 'flash';

    /** Start the session unless one is already running. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * The current cart.
     *
     * Whatever is in the session is handed to the Cart constructor, which
     * discards anything that is not a positive id with a positive quantity.
     * A session written by an older build, or hand-edited, therefore cannot
     * feed bad values into the cart rules.
     */
    public static function load(): Cart
    {
        $stored = $_SESSION[self::CART_KEY] ?? [];

        return new Cart(is_array($stored) ? $stored : []);
    }

    public static function save(Cart $cart): void
    {
        $_SESSION[self::CART_KEY] = $cart->items();
    }

    /** Store a one-time message to show on the next page load. */
    public static function flashSet(string $message): void
    {
        $_SESSION[self::FLASH_KEY] = $message;
    }

    /** Read and clear the one-time message, if any. */
    public static function flashTake(): ?string
    {
        if (!isset($_SESSION[self::FLASH_KEY])) {
            return null;
        }

        $message = (string) $_SESSION[self::FLASH_KEY];
        unset($_SESSION[self::FLASH_KEY]);

        return $message;
    }
}
```

- [ ] **Step 4: Run the suite and watch it pass**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: all pass, `exit=0`.

- [ ] **Step 5: Lint and commit**

```bash
/Applications/XAMPP/xamppfiles/bin/php -l models/SessionCart.php
/Applications/XAMPP/xamppfiles/bin/php -l tests/test_session_cart.php
git add models/SessionCart.php tests/test_session_cart.php tests/run.php
git commit -m "Week 4: add SessionCart model isolating all session access"
```

---

### Task 5: `Router`

**Files:**
- Create: `core/Router.php`
- Create: `tests/test_router.php`
- Modify: `tests/run.php`

**Interfaces:**
- Consumes: nothing (pure)
- Produces:
  - `Router::DEFAULT_ACTION` (`'catalog'`)
  - `Router::normalize(?string $action): string`
  - `Router::exists(?string $action): bool`
  - `Router::resolve(?string $action, string $method): ?array` returning `['controller' => string, 'method' => string, 'verb' => string]`
  - `Router::actions(): list<string>`

**Why `exists()` and `resolve()` are separate:** `resolve()` returns `null` both for an unknown action and for a known action reached with the wrong verb, but those need different responses — 404 versus a 303 redirect. `exists()` is how the front controller tells them apart.

- [ ] **Step 1: Write the failing test**

Create `tests/test_router.php`:

```php
<?php
/**
 * Tests for route resolution.
 *
 * Router::resolve is a pure function — action and verb in, route or null out,
 * no superglobals and no side effects — which is what makes the whole routing
 * table checkable without issuing a request.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Router.php';

// ---------------------------------------------------------------------------
describe('Router::normalize');

assert_same('catalog', Router::normalize(null), 'a missing action defaults to the catalog');
assert_same('catalog', Router::normalize(''), 'an empty action defaults to the catalog');
assert_same('catalog', Router::normalize('   '), 'a whitespace action defaults to the catalog');
assert_same('cart', Router::normalize('cart'), 'a real action is returned unchanged');
assert_same('cart.add', Router::normalize(' cart.add '), 'surrounding whitespace is trimmed');
assert_same('catalog', Router::DEFAULT_ACTION, 'the default action is the catalog');

// ---------------------------------------------------------------------------
describe('Router::exists');

assert_true(Router::exists(null), 'the default action exists');
assert_true(Router::exists(''), 'an empty action resolves to the existing default');
assert_true(Router::exists('catalog'), 'the catalog action exists');
assert_true(Router::exists('cart'), 'the cart action exists');
assert_true(Router::exists('cart.add'), 'the cart.add action exists');
assert_true(Router::exists('cart.checkout'), 'the cart.checkout action exists');
assert_same(false, Router::exists('nope'), 'an unknown action does not exist');
assert_same(false, Router::exists('Cart'), 'action names are case sensitive');
assert_same(false, Router::exists('../config/database'), 'a path-like action does not exist');

// ---------------------------------------------------------------------------
describe('Router::resolve — GET pages');

$catalog = Router::resolve('catalog', 'GET');
assert_same('CatalogController', $catalog['controller'], 'the catalog routes to CatalogController');
assert_same('index', $catalog['method'], 'the catalog routes to the index method');
assert_same('GET', $catalog['verb'], 'the catalog is a GET route');

assert_same(
    'CatalogController',
    Router::resolve(null, 'GET')['controller'],
    'a missing action falls through to the catalog controller'
);
assert_same(
    'CatalogController',
    Router::resolve('', 'GET')['controller'],
    'an empty action falls through to the catalog controller'
);

$cart = Router::resolve('cart', 'GET');
assert_same('CartController', $cart['controller'], 'the cart page routes to CartController');
assert_same('index', $cart['method'], 'the cart page routes to the index method');

// ---------------------------------------------------------------------------
describe('Router::resolve — POST actions');

$expected = [
    'cart.add'      => 'add',
    'cart.remove'   => 'remove',
    'cart.increase' => 'increase',
    'cart.decrease' => 'decrease',
    'cart.checkout' => 'checkout',
];

foreach ($expected as $action => $method) {
    $route = Router::resolve($action, 'POST');
    assert_true(is_array($route), $action . ' resolves for POST');
    assert_same('CartController', $route['controller'], $action . ' routes to CartController');
    assert_same($method, $route['method'], $action . ' routes to ' . $method . '()');
    assert_same('POST', $route['verb'], $action . ' is a POST route');
}

// ---------------------------------------------------------------------------
describe('Router::resolve — verb enforcement');

// A cart mutation reached by GET must not resolve. This is what preserves
// Post/Redirect/Get: a stray link or a bookmarked action cannot change state.
assert_same(null, Router::resolve('cart.add', 'GET'), 'a POST action does not resolve for GET');
assert_same(null, Router::resolve('cart.checkout', 'GET'), 'checkout does not resolve for GET');
assert_same(null, Router::resolve('catalog', 'POST'), 'a GET page does not resolve for POST');
assert_same(null, Router::resolve('cart', 'POST'), 'the cart page does not resolve for POST');
assert_same(null, Router::resolve('cart.add', 'DELETE'), 'an unrelated verb does not resolve');

// The verb comparison is case-insensitive; the action name is not.
assert_true(is_array(Router::resolve('catalog', 'get')), 'a lowercase verb still resolves');
assert_true(is_array(Router::resolve('cart.add', 'post')), 'a lowercase POST verb still resolves');

// ---------------------------------------------------------------------------
describe('Router::resolve — unknown actions');

assert_same(null, Router::resolve('nope', 'GET'), 'an unknown action does not resolve');
assert_same(null, Router::resolve('nope', 'POST'), 'an unknown action does not resolve for POST');
assert_same(null, Router::resolve('../config/database', 'GET'), 'a path-like action does not resolve');
assert_same(null, Router::resolve('cart.destroy', 'POST'), 'an action absent from the table does not resolve');

// ---------------------------------------------------------------------------
describe('Router table integrity');

$actions = Router::actions();
assert_same(7, count($actions), 'the table declares seven routes');
assert_true(in_array('catalog', $actions, true), 'the catalog route is in the table');
assert_true(in_array('cart.checkout', $actions, true), 'the checkout route is in the table');

// Every route must name a controller method that actually exists, or the
// route table and the controllers can drift apart silently.
require_once __DIR__ . '/../controllers/CatalogController.php';
require_once __DIR__ . '/../controllers/CartController.php';

foreach ($actions as $action) {
    foreach (['GET', 'POST'] as $verb) {
        $route = Router::resolve($action, $verb);
        if ($route === null) {
            continue;
        }
        assert_true(
            class_exists($route['controller']),
            $action . ' names a controller class that exists'
        );
        assert_true(
            method_exists($route['controller'], $route['method']),
            $action . ' names a controller method that exists'
        );
    }
}
```

Then add the suite to `tests/run.php`:

```php
$suites = [
    __DIR__ . '/test_money.php',
    __DIR__ . '/test_cart.php',
    __DIR__ . '/test_session_cart.php',
    __DIR__ . '/test_products.php',
    __DIR__ . '/test_router.php',
];
```

- [ ] **Step 2: Run the suite and watch it fail**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: `Failed opening required '.../core/Router.php'`, non-zero exit.

- [ ] **Step 3: Write the implementation**

Create `core/Router.php`:

```php
<?php
/**
 * Request routing.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Routing is a whitelist: an action that is not in the table below does not
 * reach any controller. Each route also declares the HTTP verb it accepts,
 * which is what preserves Post/Redirect/Get — a cart mutation reached by GET
 * (a stray link, a bookmarked form target, a refreshed POST) resolves to
 * nothing and changes no state.
 *
 * resolve() is pure: action and verb in, route or null out. No superglobals,
 * no side effects. The front controller applies the outcome; this class only
 * decides it.
 */

declare(strict_types=1);

final class Router
{
    public const DEFAULT_ACTION = 'catalog';

    /** action => [controller class, method, accepted verb] */
    private const ROUTES = [
        'catalog'       => ['CatalogController', 'index',    'GET'],
        'cart'          => ['CartController',    'index',    'GET'],
        'cart.add'      => ['CartController',    'add',      'POST'],
        'cart.remove'   => ['CartController',    'remove',   'POST'],
        'cart.increase' => ['CartController',    'increase', 'POST'],
        'cart.decrease' => ['CartController',    'decrease', 'POST'],
        'cart.checkout' => ['CartController',    'checkout', 'POST'],
    ];

    /** @return list<string> */
    public static function actions(): array
    {
        return array_keys(self::ROUTES);
    }

    /** An absent, empty, or whitespace action means the catalog. */
    public static function normalize(?string $action): string
    {
        $action = trim((string) $action);

        return $action === '' ? self::DEFAULT_ACTION : $action;
    }

    /**
     * Whether the action names a route at all, regardless of verb.
     *
     * The front controller needs this to tell "no such page" (404) apart from
     * "right page, wrong verb" (redirect), since resolve() returns null for
     * both.
     */
    public static function exists(?string $action): bool
    {
        return isset(self::ROUTES[self::normalize($action)]);
    }

    /**
     * The route for an action and verb, or null if there is none.
     *
     * @return array{controller:string, method:string, verb:string}|null
     */
    public static function resolve(?string $action, string $method): ?array
    {
        $action = self::normalize($action);

        if (!isset(self::ROUTES[$action])) {
            return null;
        }

        [$controller, $handler, $verb] = self::ROUTES[$action];

        if (strtoupper($method) !== $verb) {
            return null;
        }

        return [
            'controller' => $controller,
            'method'     => $handler,
            'verb'       => $verb,
        ];
    }
}
```

- [ ] **Step 4: Run the suite and watch the router tests pass**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: the router tests pass up to the "Router table integrity" section, which fails on `Failed opening required '.../controllers/CatalogController.php'`. **That failure is expected and correct** — the controllers arrive in Task 7. Note the assertion count and move on; the suite goes green again at the end of Task 7.

If you would rather keep the suite green in between, temporarily comment out the two `require_once` lines and the final `foreach` block in the "Router table integrity" section, and restore them in Task 7 Step 5. Either is fine; do not delete the block.

- [ ] **Step 5: Lint and commit**

```bash
/Applications/XAMPP/xamppfiles/bin/php -l core/Router.php
/Applications/XAMPP/xamppfiles/bin/php -l tests/test_router.php
git add core/Router.php tests/test_router.php tests/run.php
git commit -m "Week 4: add Router with whitelist route table and verb enforcement"
```

---

### Task 6: Bootstrap, helpers, and `View`

**Files:**
- Create: `core/bootstrap.php`
- Create: `core/helpers.php`
- Create: `core/View.php`
- Create: `tests/test_view.php`
- Modify: `tests/run.php`
- Modify: `tests/test_money.php`, `tests/test_cart.php`, `tests/test_products.php`, `tests/test_session_cart.php`, `tests/test_router.php` — drop the direct `require_once` lines now that autoloading works

**Interfaces:**
- Consumes: nothing
- Produces:
  - `View::render(string $template, array $data = []): string`
  - `e(?string $value): string` — HTML escaping
  - `url(string $action = ''): string` — builds `index.php` or `index.php?action=X`
  - `redirect_to(string $action): never`
  - An autoloader searching `models/`, `controllers/`, `core/`
  - `APP_ROOT` constant

- [ ] **Step 1: Write the failing test**

Create `tests/test_view.php`:

```php
<?php
/**
 * Tests for the view helpers and the template renderer.
 *
 * e() is the application's only XSS defense, so it is tested directly rather
 * than trusted because "the views call it".
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
describe('e()');

assert_same('plain text', e('plain text'), 'ordinary text passes through unchanged');
assert_same('', e(null), 'null becomes an empty string rather than a warning');
assert_same('', e(''), 'an empty string stays empty');
assert_same(
    '&lt;script&gt;alert(1)&lt;/script&gt;',
    e('<script>alert(1)</script>'),
    'angle brackets are escaped so injected markup cannot execute'
);
assert_same('Tom &amp; Jerry', e('Tom & Jerry'), 'ampersands are escaped');
assert_same('&quot;quoted&quot;', e('"quoted"'), 'double quotes are escaped so an attribute cannot be broken out of');
assert_same('&#039;quoted&#039;', e("'quoted'"), 'single quotes are escaped too');
assert_same('café', e('café'), 'UTF-8 accented characters survive escaping intact, not mangled into entities');

// ---------------------------------------------------------------------------
describe('url()');

assert_same('index.php', url(), 'no action gives the bare front controller');
assert_same('index.php', url(''), 'an empty action gives the bare front controller');
assert_same('index.php', url('catalog'), 'the default action is left implicit in the URL');
assert_same('index.php?action=cart', url('cart'), 'a named action becomes a query string');
assert_same('index.php?action=cart.add', url('cart.add'), 'a dotted action is not mangled');
assert_same('index.php?action=cart.checkout', url('cart.checkout'), 'the checkout action builds correctly');

// ---------------------------------------------------------------------------
describe('View::render');

// Rendering an unknown template must be a loud failure, not a blank page.
assert_throws(
    static fn () => View::render('no/such/template'),
    'rendering a missing template throws'
);

// A template name is never user input, but the check costs nothing and means
// a future caller cannot turn one into a file read.
assert_throws(
    static fn () => View::render('../config/database'),
    'a path-traversing template name is rejected'
);
assert_throws(
    static fn () => View::render('catalog/../../config/database'),
    'a traversal buried mid-path is rejected'
);
```

Add it to `tests/run.php` and drop the now-unneeded per-file requires. `tests/run.php` becomes:

```php
<?php
/**
 * Test runner for the SDC310L course project.
 *
 *     php tests/run.php
 *
 * Exits 0 when every assertion passes, 1 otherwise, so a non-zero exit is a
 * real failure signal and not something a pipe can swallow.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/lib.php';

$suites = [
    __DIR__ . '/test_money.php',
    __DIR__ . '/test_cart.php',
    __DIR__ . '/test_session_cart.php',
    __DIR__ . '/test_products.php',
    __DIR__ . '/test_view.php',
    __DIR__ . '/test_router.php',
];

foreach ($suites as $suite) {
    echo "\n=== " . basename($suite) . " ===\n";
    require $suite;
}

report_and_exit();
```

Then delete these lines from the test files (the autoloader replaces them):

- `tests/test_money.php`: `require_once __DIR__ . '/../models/Money.php';`
- `tests/test_cart.php`: both `require_once` lines for `Money.php` and `Cart.php`
- `tests/test_session_cart.php`: all three `require_once` lines
- `tests/test_products.php`: `require_once __DIR__ . '/../models/Product.php';` (keep the `$pdo = require ...config/database.php` line — that is a value, not a class)
- `tests/test_router.php`: `require_once __DIR__ . '/../core/Router.php';` and the two controller requires in the integrity block

- [ ] **Step 2: Run the suite and watch it fail**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: `Failed opening required '.../core/bootstrap.php'`, non-zero exit.

- [ ] **Step 3: Write the implementations**

Create `core/helpers.php`:

```php
<?php
/**
 * View and request helpers.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Three free functions the templates and the front controller need. They are
 * functions rather than static methods because a view template calls e() on
 * nearly every value it prints, and `e($x)` stays readable where
 * `View::e($x)` would not.
 */

declare(strict_types=1);

/**
 * Escape a value for HTML output.
 *
 * This is the application's only defense against injected markup, so every
 * value a template prints goes through it — product names and descriptions
 * come from the database and flash messages come from the session.
 *
 * ENT_QUOTES escapes single quotes as well as double, so a value is safe
 * inside either kind of attribute delimiter.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Build a URL for a route.
 *
 * The catalog is left implicit — url('catalog') gives 'index.php' rather than
 * 'index.php?action=catalog' — so the store's home page has one canonical
 * address instead of two that render the same thing.
 */
function url(string $action = ''): string
{
    if ($action === '' || $action === Router::DEFAULT_ACTION) {
        return 'index.php';
    }

    return 'index.php?action=' . rawurlencode($action);
}

/**
 * Send a See Other redirect to a route and stop.
 *
 * 303 rather than 302 so the browser is required to follow with GET, which is
 * what makes Post/Redirect/Get work: refreshing the destination re-runs the
 * GET, never the POST that preceded it.
 */
function redirect_to(string $action): never
{
    header('Location: ' . url($action), true, 303);
    exit;
}
```

Create `core/View.php`:

```php
<?php
/**
 * Template rendering.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * A view template is a plain PHP file under views/. render() runs the
 * template with the controller's data in scope, captures its output, and then
 * runs views/layout.php with that output available as $content.
 *
 * Templates receive only what the controller passed them. They have no
 * database connection and no access to the session, which is what enforces
 * the rule that a view renders and nothing else.
 */

declare(strict_types=1);

final class View
{
    private const TEMPLATE_DIR = __DIR__ . '/../views/';
    private const LAYOUT       = 'layout';

    /**
     * Render a template inside the site layout and return the HTML.
     *
     * Returns a string rather than echoing so the front controller decides
     * when output begins — which matters because a redirect must be able to
     * send headers after a controller has run.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        $content = self::capture($template, $data);

        return self::capture(self::LAYOUT, $data + ['content' => $content]);
    }

    /**
     * Run one template and capture its output.
     *
     * @param array<string,mixed> $data
     */
    private static function capture(string $template, array $data): string
    {
        // Template names come from the controllers, never from a request. The
        // check costs nothing and means a future caller cannot turn one into
        // an arbitrary file read.
        if (preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$#', $template) !== 1) {
            throw new InvalidArgumentException('Invalid view template name: ' . $template);
        }

        $path = self::TEMPLATE_DIR . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException('View template not found: ' . $template);
        }

        // EXTR_SKIP so a data key can never overwrite $path or $template.
        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}
```

Create `core/bootstrap.php`:

```php
<?php
/**
 * Application bootstrap.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Registers class autoloading and loads the view helpers. Everything that
 * needs the application's classes requires this file first: the front
 * controller and the test runner.
 *
 * There is no Composer here. The project is graded on a stock XAMPP install
 * and adding a dependency manager for seven classes is not a trade worth
 * making.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

/**
 * Map a class name to a file under models/, controllers/, or core/.
 *
 * The name is validated against a plain PHP identifier before it is used in
 * a path, so no class name can escape those three directories.
 */
spl_autoload_register(static function (string $class): void {
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class) !== 1) {
        return;
    }

    foreach (['models', 'controllers', 'core'] as $directory) {
        $path = APP_ROOT . '/' . $directory . '/' . $class . '.php';

        if (is_file($path)) {
            require_once $path;

            return;
        }
    }
});

// Free functions cannot be autoloaded, so the helpers are loaded outright.
require_once __DIR__ . '/helpers.php';
```

- [ ] **Step 4: Run the suite**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: everything passes except the "Router table integrity" block, which still needs the controllers from Task 7. If you commented that block out in Task 5, the suite is fully green here.

- [ ] **Step 5: Verify the autoloader actually resolves each class**

```bash
/Applications/XAMPP/xamppfiles/bin/php -r '
require "core/bootstrap.php";
foreach (["Money","Cart","Product","SessionCart","Router","View"] as $c) {
    printf("%-12s %s\n", $c, class_exists($c) ? "OK" : "MISSING");
}
printf("%-12s %s\n", "e()", function_exists("e") ? "OK" : "MISSING");
printf("%-12s %s\n", "url()", function_exists("url") ? "OK" : "MISSING");
'
```

Expected: `OK` on every line.

- [ ] **Step 6: Lint and commit**

```bash
for f in core/bootstrap.php core/helpers.php core/View.php tests/test_view.php tests/run.php; do
  /Applications/XAMPP/xamppfiles/bin/php -l "$f"
done
git add core/ tests/
git commit -m "Week 4: add autoloader, view helpers, and template renderer"
```

---

### Task 7: Controllers

**Files:**
- Create: `controllers/CatalogController.php`
- Create: `controllers/CartController.php`

**Interfaces:**
- Consumes: `Product`, `Cart`, `SessionCart`, `Router::DEFAULT_ACTION`
- Produces: controller methods returning either `['view' => string, 'data' => array]` or `['redirect' => string]`

**The existence guard.** Week 3 let any `product_id` into the cart. The id then sat in the session and the cart page silently skipped it, so "Add to Cart" appeared to do nothing with no explanation. The guard belongs on every operation that can *create* a new cart line — `add` and `increase`. `remove` and `decrease` only ever shrink the cart, so they need no database round-trip. This is a small widening of spec §7.1, which named only `add`; applying it to both is what keeps the rule statable in one sentence.

- [ ] **Step 1: Write `CatalogController`**

Create `controllers/CatalogController.php`:

```php
<?php
/**
 * Catalog page controller.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Translates a request into model calls and hands the result to a view. It
 * runs no SQL of its own, emits no HTML, and sends no headers — the front
 * controller does that with what this returns.
 */

declare(strict_types=1);

final class CatalogController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET: the whole catalog, with each row showing its current cart quantity. */
    public function index(): array
    {
        $cart = SessionCart::load();

        return [
            'view' => 'catalog/index',
            'data' => [
                'pageTitle' => 'Catalog',
                'activeNav' => 'catalog',
                'products'  => (new Product($this->pdo))->all(),
                'cart'      => $cart,
                'cartCount' => $cart->itemCount(),
                'flash'     => SessionCart::flashTake(),
            ],
        ];
    }
}
```

- [ ] **Step 2: Write `CartController`**

Create `controllers/CartController.php`:

```php
<?php
/**
 * Shopping cart controller.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * index() renders the cart. The five mutation methods each apply one model
 * call and return a redirect rather than rendering, which is the
 * Post/Redirect/Get pattern: without the redirect, refreshing the browser
 * after adding a product would silently re-submit and add it a second time.
 */

declare(strict_types=1);

final class CartController
{
    /**
     * Routes a cart form may ask to return to.
     *
     * Echoing an arbitrary submitted value into a Location header would let a
     * crafted form redirect visitors off-site, so anything unrecognized falls
     * back to the catalog.
     */
    private const RETURN_ROUTES = ['catalog', 'cart'];

    public function __construct(private PDO $pdo)
    {
    }

    /** GET: the cart's line items and order totals. */
    public function index(): array
    {
        $cart = SessionCart::load();

        // Only the products actually in the cart are fetched, rather than the
        // whole catalog, so the query stays proportional to the order.
        $products = (new Product($this->pdo))->byIds(array_keys($cart->items()));
        $lines    = $cart->lines($products);

        return [
            'view' => 'cart/index',
            'data' => [
                'pageTitle' => 'Shopping Cart',
                'activeNav' => 'cart',
                'lines'     => $lines,
                'totals'    => Cart::totals($lines),
                'cartCount' => $cart->itemCount(),
                'flash'     => SessionCart::flashTake(),
            ],
        ];
    }

    /** POST: add one of a product to the cart. */
    public function add(): array
    {
        $productId = $this->productId();

        if ($this->productExists($productId)) {
            $cart = SessionCart::load();
            $cart->add($productId);
            SessionCart::save($cart);
        }

        return $this->back();
    }

    /** POST: raise a product's quantity by one. */
    public function increase(): array
    {
        $productId = $this->productId();

        if ($this->productExists($productId)) {
            $cart = SessionCart::load();
            $cart->adjust($productId, 1);
            SessionCart::save($cart);
        }

        return $this->back();
    }

    /**
     * POST: lower a product's quantity by one.
     *
     * No existence check: this can only shrink the cart. Cart::adjust clamps
     * at 0, so this can never produce a negative quantity even if the button
     * is submitted when the cart is already empty.
     */
    public function decrease(): array
    {
        $cart = SessionCart::load();
        $cart->adjust($this->productId(), -1);
        SessionCart::save($cart);

        return $this->back();
    }

    /** POST: drop a product from the cart entirely. */
    public function remove(): array
    {
        $cart = SessionCart::load();
        $cart->remove($this->productId());
        SessionCart::save($cart);

        return $this->back();
    }

    /** POST: empty the cart and confirm the order. */
    public function checkout(): array
    {
        $cart = SessionCart::load();
        $cart->clear();
        SessionCart::save($cart);
        SessionCart::flashSet('Thank you for your order. Your cart has been cleared.');

        // Checking out always returns to the catalog, whatever page it came from.
        return ['redirect' => Router::DEFAULT_ACTION];
    }

    /** The submitted product id, or 0 when absent or non-numeric. */
    private function productId(): int
    {
        return (int) ($_POST['product_id'] ?? 0);
    }

    /**
     * Whether a product id names a real catalog product.
     *
     * Guards the two operations that can create a new cart line. Week 3 let
     * any id in; it then sat in the session and the cart page silently
     * skipped it, so the visitor's click appeared to do nothing.
     */
    private function productExists(int $productId): bool
    {
        return $productId > 0 && (new Product($this->pdo))->byId($productId) !== null;
    }

    /** Redirect back to the page the form was submitted from. */
    private function back(): array
    {
        $requested = (string) ($_POST['return'] ?? Router::DEFAULT_ACTION);

        return [
            'redirect' => in_array($requested, self::RETURN_ROUTES, true)
                ? $requested
                : Router::DEFAULT_ACTION,
        ];
    }
}
```

- [ ] **Step 3: Restore the router integrity block if you commented it out**

If you commented out the controller requires and the `foreach` in `tests/test_router.php` during Task 5, uncomment them now. The `require_once` lines are no longer needed — the autoloader handles the classes — so restore only the `foreach` block.

- [ ] **Step 4: Run the suite and watch it pass**

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: fully green, `exit=0`. The router integrity block now confirms every route names a controller method that exists.

- [ ] **Step 5: Lint and commit**

```bash
/Applications/XAMPP/xamppfiles/bin/php -l controllers/CatalogController.php
/Applications/XAMPP/xamppfiles/bin/php -l controllers/CartController.php
git add controllers/ tests/test_router.php
git commit -m "Week 4: add catalog and cart controllers"
```

---

### Task 8: View templates

**Files:**
- Create: `views/layout.php`
- Create: `views/catalog/index.php`
- Create: `views/cart/index.php`
- Create: `views/error/not-found.php`
- Modify: `css/style.css` (one small addition for the split quantity form)

**Interfaces:**
- Consumes: `e()`, `url()`, `Money::format()`, `Cart::TAX_RATE`, `Cart::SHIPPING_RATE`; the data arrays from Task 7
- Produces: rendered HTML

**Markup change to be aware of.** In Week 3 the `−` / `+` quantity buttons shared one form and were told apart by their `value`. The verb now lives in the URL, so each button needs its own `action`, and nested forms are invalid HTML. The two buttons therefore become two sibling forms inside a flex wrapper. This is a markup change with **no intended visual change** — Step 5 checks that against the rendered page.

- [ ] **Step 1: Write the layout**

Create `views/layout.php`:

```php
<?php
/**
 * Site layout.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Wraps every rendered view. Receives from the controller:
 *   $content    string  The rendered view, already escaped by its template.
 *   $pageTitle  string  Text appended to the store name in <title>.
 *   $activeNav  string  'catalog' or 'cart'; highlights the current nav link.
 *   $cartCount  int     Items in the cart; shown as a badge on the cart link.
 */

$storeName = 'Summit Outfitters';
$pageTitle = $pageTitle ?? 'Online Store';
$activeNav = $activeNav ?? '';
$cartCount = (int) ($cartCount ?? 0);
$content   = $content ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($storeName . ' — ' . $pageTitle); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?php echo url(); ?>">
            <span class="brand-mark">&#9650;</span>
            <span class="brand-name"><?php echo e($storeName); ?></span>
        </a>
        <nav class="site-nav">
            <a href="<?php echo url('catalog'); ?>"<?php echo $activeNav === 'catalog' ? ' class="active"' : ''; ?>>Catalog</a>
            <a href="<?php echo url('cart'); ?>"<?php echo $activeNav === 'cart' ? ' class="active"' : ''; ?>>View Cart<?php
                if ($cartCount > 0): ?><span class="nav-badge"><?php echo $cartCount; ?></span><?php
                endif; ?></a>
        </nav>
    </div>
</header>

<main class="wrap">
<?php echo $content; ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <p>SDC310L Course Project — James Strohm (jamstr441)</p>
        <p class="build-note">Week 4 build: the application is re-architected to Model-View-Controller. Every request enters through a single front controller.</p>
    </div>
</footer>

</body>
</html>
```

- [ ] **Step 2: Write the catalog view**

Create `views/catalog/index.php`:

```php
<?php
/**
 * Catalog view.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Receives from CatalogController::index:
 *   $products  array  Catalog rows.
 *   $cart      Cart   Used only to read each row's current quantity.
 *   $flash     ?string
 *
 * This template renders and nothing else: no query, no session, no state
 * change.
 */
?>
<div class="page-intro">
    <h1>Product Catalog</h1>
    <p>Gear for the trail, the ridgeline, and everything between.</p>
</div>

<?php if ($flash !== null): ?>
    <p class="notice notice-success" role="status"><?php echo e($flash); ?></p>
<?php endif; ?>

<?php if ($products === []): ?>

    <p class="empty-state">
        The catalog is empty. If you are running this locally, import
        <code>database/onlinestore.sql</code> to load the products.
    </p>

<?php else: ?>

    <div class="table-scroll">
        <table class="catalog-table">
            <thead>
                <tr>
                    <th scope="col" class="col-id">Product ID</th>
                    <th scope="col" class="col-name">Product Name</th>
                    <th scope="col" class="col-desc">Product Description</th>
                    <th scope="col" class="col-cost">Product Cost</th>
                    <th scope="col" class="col-qty">Quantity Ordered</th>
                    <th scope="col" class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <?php
                $id       = (int) $product['product_id'];
                $quantity = $cart->quantity($id);
                // Every control names its product, so a screen-reader user can
                // tell six identical "Add to Cart" buttons apart.
                $label    = e($product['product_name']);
                ?>
                <tr>
                    <td class="col-id"><?php echo $id; ?></td>
                    <td class="col-name"><?php echo $label; ?></td>
                    <td class="col-desc"><?php echo e($product['product_description']); ?></td>
                    <td class="col-cost">$<?php echo Money::format(Money::toCents((string) $product['product_cost'])); ?></td>

                    <td class="col-qty">
                        <div class="qty-control">
                            <form method="post" action="<?php echo url('cart.decrease'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Decrease quantity of <?php echo $label; ?>"
                                        <?php echo $quantity < 1 ? 'disabled' : ''; ?>>&minus;</button>
                            </form>
                            <span class="qty-value"><?php echo $quantity; ?></span>
                            <form method="post" action="<?php echo url('cart.increase'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Increase quantity of <?php echo $label; ?>">+</button>
                            </form>
                        </div>
                    </td>

                    <td class="col-actions">
                        <div class="action-control">
                            <form method="post" action="<?php echo url('cart.add'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-add"
                                        aria-label="Add <?php echo $label; ?> to cart">Add to Cart</button>
                            </form>
                            <form method="post" action="<?php echo url('cart.remove'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-remove"
                                        aria-label="Remove <?php echo $label; ?> from cart"
                                        <?php echo $quantity < 1 ? 'disabled' : ''; ?>>Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<p class="page-actions">
    <a class="btn btn-primary" href="<?php echo url('cart'); ?>">View Cart<?php
        echo $cartCount > 0 ? ' (' . (int) $cartCount . ')' : ''; ?></a>
</p>
```

- [ ] **Step 3: Write the cart view**

Create `views/cart/index.php`:

```php
<?php
/**
 * Shopping cart view.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Receives from CartController::index:
 *   $lines   array  Line items, money already in whole cents.
 *   $totals  array  items_total_cents, tax_cents, shipping_cents, order_total_cents.
 *   $flash   ?string
 */
?>
<div class="page-intro">
    <h1>Shopping Cart</h1>
    <p>Review your order before checking out.</p>
</div>

<?php if ($flash !== null): ?>
    <p class="notice notice-success" role="status"><?php echo e($flash); ?></p>
<?php endif; ?>

<?php if ($lines === []): ?>

    <p class="empty-state">
        Your cart is empty. Head back to the catalog to add something to it.
    </p>

    <p class="page-actions">
        <a class="btn btn-primary" href="<?php echo url('catalog'); ?>">Continue Shopping</a>
    </p>

<?php else: ?>

    <div class="table-scroll">
        <table class="cart-table">
            <thead>
                <tr>
                    <th scope="col" class="col-id">Product ID</th>
                    <th scope="col" class="col-name">Product Name</th>
                    <th scope="col" class="col-qty">Quantity Ordered</th>
                    <th scope="col" class="col-cost">Product Cost</th>
                    <th scope="col" class="col-total">Product Total</th>
                    <th scope="col" class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <?php
                $id    = (int) $line['product_id'];
                $label = e($line['product_name']);
                ?>
                <tr>
                    <td class="col-id"><?php echo $id; ?></td>
                    <td class="col-name"><?php echo $label; ?></td>
                    <td class="col-qty">
                        <div class="qty-control">
                            <form method="post" action="<?php echo url('cart.decrease'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="cart">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Decrease quantity of <?php echo $label; ?>">&minus;</button>
                            </form>
                            <span class="qty-value"><?php echo (int) $line['quantity']; ?></span>
                            <form method="post" action="<?php echo url('cart.increase'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="cart">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Increase quantity of <?php echo $label; ?>">+</button>
                            </form>
                        </div>
                    </td>
                    <td class="col-cost">$<?php echo Money::format((int) $line['cost_cents']); ?></td>
                    <td class="col-total">$<?php echo Money::format((int) $line['line_total_cents']); ?></td>
                    <td class="col-actions">
                        <div class="action-control">
                            <form method="post" action="<?php echo url('cart.remove'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="cart">
                                <button type="submit" class="btn btn-remove"
                                        aria-label="Remove <?php echo $label; ?> from cart">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="order-summary">
        <h2>Order Summary</h2>
        <dl>
            <dt>Total of Items Ordered</dt>
            <dd>$<?php echo Money::format((int) $totals['items_total_cents']); ?></dd>

            <dt>Tax (<?php echo (int) round(Cart::TAX_RATE * 100); ?>%)</dt>
            <dd>$<?php echo Money::format((int) $totals['tax_cents']); ?></dd>

            <dt>Shipping &amp; Handling (<?php echo (int) round(Cart::SHIPPING_RATE * 100); ?>%)</dt>
            <dd>$<?php echo Money::format((int) $totals['shipping_cents']); ?></dd>

            <dt class="grand">Order Total</dt>
            <dd class="grand">$<?php echo Money::format((int) $totals['order_total_cents']); ?></dd>
        </dl>
    </div>

    <div class="page-actions">
        <a class="btn btn-primary" href="<?php echo url('catalog'); ?>">Continue Shopping</a>
        <form method="post" action="<?php echo url('cart.checkout'); ?>">
            <button type="submit" class="btn btn-checkout">Check Out</button>
        </form>
    </div>

<?php endif; ?>
```

- [ ] **Step 4: Write the 404 view**

Create `views/error/not-found.php`:

```php
<?php
/**
 * 404 view.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Rendered by the front controller when an ?action= value names no route.
 */
?>
<div class="page-intro">
    <h1>Page Not Found</h1>
    <p>That address does not match anything in this store.</p>
</div>

<p class="empty-state">
    The link may be out of date. The catalog and the cart are both reachable
    from the navigation above.
</p>

<p class="page-actions">
    <a class="btn btn-primary" href="<?php echo url('catalog'); ?>">Back to the Catalog</a>
</p>
```

- [ ] **Step 5: Adjust the stylesheet for the split forms**

Week 3's `.qty-control` and `.action-control` were `<form>` elements styled as flex rows. They are now `<div>` wrappers holding sibling forms, so the forms themselves must not introduce block-level breaks.

Read the existing rules first:

```bash
grep -n "qty-control\|action-control\|qty-value" css/style.css
```

Then make the two wrapper rules also lay out their child forms. Append to `css/style.css`:

```css
/* Week 4: the quantity and action controls became wrappers around one form
   per button, because each button now posts to its own front-controller
   action and nested forms are invalid HTML. The forms must not add layout of
   their own, so the wrapper's flex row stays visually identical to Week 3. */
.qty-control > form,
.action-control > form {
    display: contents;
}
```

`display: contents` makes each form's box disappear from layout while its button remains a direct flex item of the wrapper — which is exactly the Week 3 arrangement. If the existing `.qty-control` / `.action-control` rules are scoped with `form.qty-control` or similar element selectors, change those selectors to plain `.qty-control` / `.action-control` so they still apply to a `div`.

- [ ] **Step 6: Lint and commit**

Templates cannot run standalone, so lint is the check available here; rendering is verified in Task 9.

```bash
for f in views/layout.php views/catalog/index.php views/cart/index.php views/error/not-found.php; do
  /Applications/XAMPP/xamppfiles/bin/php -l "$f"
done
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
git add views/ css/style.css
git commit -m "Week 4: add layout, catalog, cart, and 404 view templates"
```

---

### Task 9: Front controller — the swap

The one task where the running application changes. Everything before this was additive.

**Files:**
- Rewrite: `index.php`
- Delete: `cart.php`, `cart-action.php`, `includes/cart.php`, `includes/products.php`, `includes/session.php`, `includes/header.php`, `includes/footer.php` (and the now-empty `includes/` directory)

**Interfaces:**
- Consumes: everything from Tasks 1–8
- Produces: the application's only HTTP entry point

- [ ] **Step 1: Write the front controller**

Replace the whole of `index.php`:

```php
<?php
/**
 * Front controller — the application's single entry point.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Every request to this store arrives here. This file resolves the ?action=
 * value to a controller method, calls it, and turns what it returns into
 * either a rendered page or a redirect. It contains no business rules, no
 * SQL, and no markup.
 *
 * Week 4: replaces the Week 3 arrangement in which index.php, cart.php, and
 * cart-action.php were each simultaneously the router, the controller, the
 * model, and the view.
 */

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

SessionCart::start();

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

$route = Router::resolve($action, $method);

if ($route === null) {
    if (Router::exists($action)) {
        // The action is real but the verb is wrong: a stray link to a cart
        // mutation, or a refreshed POST. Change nothing and send the visitor
        // to the catalog. This is what keeps Post/Redirect/Get intact.
        redirect_to(Router::DEFAULT_ACTION);
    }

    http_response_code(404);
    echo View::render('error/not-found', [
        'pageTitle' => 'Page Not Found',
        'activeNav' => '',
        'cartCount' => SessionCart::load()->itemCount(),
    ]);
    exit;
}

$pdo = require __DIR__ . '/config/database.php';

$controller = new $route['controller']($pdo);
$result     = $controller->{$route['method']}();

if (isset($result['redirect'])) {
    redirect_to((string) $result['redirect']);
}

echo View::render((string) $result['view'], (array) $result['data']);
```

- [ ] **Step 2: Delete the Week 3 entry points and includes**

```bash
git rm cart.php cart-action.php \
       includes/cart.php includes/products.php includes/session.php \
       includes/header.php includes/footer.php
ls includes 2>/dev/null || echo "includes/ is gone"
```

- [ ] **Step 3: Run the test suite**

```bash
/Applications/XAMPP/xamppfiles/bin/php -l index.php
/Applications/XAMPP/xamppfiles/bin/php tests/run.php; echo "exit=$?"
```

Expected: fully green, `exit=0`. Nothing in the suite referenced the deleted files.

- [ ] **Step 4: Check every route over HTTP**

```bash
BASE=http://localhost/SDC310L

echo "-- GET pages (expect 200) --"
curl -s -o /dev/null -w 'catalog (bare)  %{http_code}\n' "$BASE/"
curl -s -o /dev/null -w 'catalog         %{http_code}\n' "$BASE/index.php?action=catalog"
curl -s -o /dev/null -w 'cart            %{http_code}\n' "$BASE/index.php?action=cart"

echo "-- POST actions (expect 303) --"
for a in cart.add cart.remove cart.increase cart.decrease cart.checkout; do
  curl -s -o /dev/null -w "$a  %{http_code}\n" -X POST -d 'product_id=1' "$BASE/index.php?action=$a"
done

echo "-- POST route reached by GET (expect 303) --"
curl -s -o /dev/null -w 'cart.add via GET  %{http_code}\n' "$BASE/index.php?action=cart.add"

echo "-- unknown action (expect 404) --"
curl -s -o /dev/null -w 'nope            %{http_code}\n' "$BASE/index.php?action=nope"

echo "-- deleted pages (expect 404) --"
curl -s -o /dev/null -w 'cart.php        %{http_code}\n' "$BASE/cart.php"
curl -s -o /dev/null -w 'cart-action.php %{http_code}\n' "$BASE/cart-action.php"
```

Every line must match its expectation. A `500` anywhere means read `/Applications/XAMPP/xamppfiles/logs/error_log` before changing anything.

- [ ] **Step 5: Confirm the stylesheet path survived the front controller**

This is the failure that produces an ugly page rather than an error, so check it against the rendered HTML rather than reasoning about relative URLs.

```bash
BASE=http://localhost/SDC310L
curl -s "$BASE/index.php?action=cart" | grep -o 'href="[^"]*style.css"'
curl -s -o /dev/null -w 'stylesheet %{http_code}\n' "$BASE/css/style.css"
```

Expected: `href="css/style.css"` and `200`.

- [ ] **Step 6: Walk the full cart flow with a cookie jar**

```bash
JAR=$(mktemp)
BASE=http://localhost/SDC310L

post() { curl -s -o /dev/null -b "$JAR" -c "$JAR" -X POST -d "$2" "$BASE/index.php?action=$1"; }
cart() { curl -s -b "$JAR" -c "$JAR" "$BASE/index.php?action=cart"; }

post cart.add 'product_id=1&return=cart'      # Trailhead 45L Backpack @ 129.99
post cart.add 'product_id=1&return=cart'      # qty 2
post cart.add 'product_id=3&return=cart'      # Cascade 2-Person Tent @ 249.00
cart | grep -oE '\$[0-9,]+\.[0-9]{2}'
```

Expected figures — 129.99 × 2 + 249.00 = 508.98 pre-tax, 5% tax = 25.45, 10% S&H = 50.90, order total 585.33:

```
$129.99   unit cost, backpack
$259.98   line total, backpack
$249.00   unit cost, tent
$249.00   line total, tent
$508.98   total of items ordered
$25.45    tax
$50.90    shipping & handling
$585.33   order total
```

Then exercise the rest:

```bash
post cart.increase 'product_id=3&return=cart'
post cart.decrease 'product_id=1&return=cart'
post cart.remove   'product_id=1&return=cart'
cart | grep -oE 'Trailhead|Cascade'            # expect Cascade only
post cart.checkout ''
curl -s -b "$JAR" -c "$JAR" "$BASE/" | grep -o 'Thank you for your order[^<]*'
cart | grep -o 'Your cart is empty'
rm -f "$JAR"
```

Expected: only `Cascade` after the remove; the thank-you flash on the catalog after checkout; `Your cart is empty` afterwards.

- [ ] **Step 7: Confirm a nonexistent product is rejected**

The Week 3 defect this refactor fixes.

```bash
BASE=http://localhost/SDC310L
JAR=$(mktemp)
curl -s -o /dev/null -b "$JAR" -c "$JAR" -X POST -d 'product_id=9999&return=cart' "$BASE/index.php?action=cart.add"
curl -s -b "$JAR" -c "$JAR" "$BASE/index.php?action=cart" | grep -c 'Your cart is empty'
rm -f "$JAR"
```

Expected: `1` — the cart is still empty, because id 9999 names no product.

- [ ] **Step 8: Confirm the quantity control still looks right**

The one change with no test coverage. Load `http://localhost/SDC310L/` in a browser and confirm the `−  0  +` control on each catalog row is still a single horizontal row, not stacked or wrapped. If it stacked, the `display: contents` rule from Task 8 Step 5 did not take — check that the wrapper selectors are not element-scoped.

- [ ] **Step 9: Check the Apache error log**

```bash
tail -30 /Applications/XAMPP/xamppfiles/logs/error_log
```

Expected: no PHP warnings, notices, or fatals from this walk.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "Week 4: route every request through a single front controller

Replaces index.php with the front controller and deletes the Week 3 page
scripts and includes/. Their logic now lives in models/, controllers/, and
views/; the Week 3 versions remain reachable at the Phase3 tag."
```

---

### Task 10: Documentation, schema check, and final verification

**Files:**
- Modify: `README.md`
- Verify: `database/onlinestore.sql`

- [ ] **Step 1: Confirm the checked-in schema still matches the live database**

The spec claims no structural change. Check it rather than assert it.

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "SHOW CREATE TABLE onlinestore.products\G"
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "SELECT COUNT(*) AS rows_seeded FROM onlinestore.products;"
grep -A 8 'CREATE TABLE' database/onlinestore.sql
```

Compare column names, types, nullability, the primary key, and the row count against the checked-in script. They must agree. If they do, no re-export is needed and the plan's §11 claim holds. If they differ, re-export with:

```bash
/Applications/XAMPP/xamppfiles/bin/mysqldump -u root --databases onlinestore \
  > database/onlinestore.sql
```

and say so in the project plan.

- [ ] **Step 2: Update the README**

Rewrite the "Current milestone" section for Week 4 and replace the repository-layout block. The layout becomes:

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

Also add a short "Request flow" section reproducing the diagram from spec §3.1, mark Week 4 as delivered in the schedule table, note that `Phase4` is the tag, and update the sentence about the schema being unchanged to say the export remains current as of Week 4.

- [ ] **Step 3: Full verification sweep**

Every check, run together, output read rather than assumed.

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/SDC310L

echo "== lint =="
find . -name '*.php' -not -path './.git/*' -print0 \
  | xargs -0 -n1 /Applications/XAMPP/xamppfiles/bin/php -l \
  | grep -v '^No syntax errors' || echo "all files clean"

echo "== tests =="
/Applications/XAMPP/xamppfiles/bin/php tests/run.php
echo "test suite exit=$?"

echo "== no stray entry points =="
ls *.php

echo "== layering =="
grep -rln '\$_SESSION' models/ controllers/ views/ core/ index.php
echo "^ must list only models/SessionCart.php"
grep -rln 'SELECT\|INSERT\|UPDATE\|DELETE' views/ controllers/ \
  && echo "^ FAIL: SQL outside the models" || echo "no SQL in views or controllers"
grep -rln '<html\|<table\|<div' models/ \
  && echo "^ FAIL: markup in a model" || echo "no markup in models"
```

Expected: no syntax errors; the suite exits 0; `index.php` is the only top-level PHP file; `$_SESSION` appears only in `models/SessionCart.php`; no SQL outside `models/`; no markup in `models/`.

- [ ] **Step 4: Capture fresh screenshots**

Replace `docs/screenshot-catalog.png` and `docs/screenshot-cart.png` from the running MVC application, with a populated cart so the totals are visible. These go in the submission alongside the project plan.

- [ ] **Step 5: Commit**

```bash
git add README.md docs/
git commit -m "Week 4: update README for the MVC structure and refresh screenshots"
```

---

## Wrap-up (after Task 10)

These are the submission steps from spec §12, not implementation tasks:

- [ ] `/code-review` on the full `week-4` diff; verify each finding against the code before acting on it, fix what is real, re-run the suite
- [ ] Tag `Phase4` and push the branch and the tag
- [ ] Open a pull request from `week-4` to `main`
- [ ] Add the instructor as a collaborator (needs their GitHub username)
- [ ] Update `docs/SDC310L Project Plan James Strohm.docx`: Week 4 sections I–V, and Week 5 section I
