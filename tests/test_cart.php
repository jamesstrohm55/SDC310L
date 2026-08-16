<?php
/**
 * Tests for the session cart: quantity rules, line building, and order totals.
 *
 * These functions are pure — they take a cart array and return a new one — so
 * the rules can be tested without a web request or a session.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/cart.php';

// Stand-in catalog, keyed by product_id, matching what products_by_ids returns.
$catalog = [
    1 => ['product_id' => 1, 'product_name' => 'Trailhead 45L Backpack', 'product_cost' => '129.99'],
    3 => ['product_id' => 3, 'product_name' => 'Cascade 2-Person Tent',  'product_cost' => '249.00'],
    5 => ['product_id' => 5, 'product_name' => 'Summit LED Headlamp',    'product_cost' => '39.99'],
];

// ---------------------------------------------------------------------------
describe('cart_add');

assert_same([2 => 1], cart_add([], 2), 'adds a product with quantity 1 by default');
assert_same([2 => 3], cart_add([], 2, 3), 'adds a product with an explicit quantity');
assert_same([2 => 4], cart_add([2 => 1], 2, 3), 'accumulates onto an existing quantity');
assert_same([2 => 1, 7 => 1], cart_add([2 => 1], 7), 'leaves other products untouched');
assert_same([], cart_add([], 2, 0), 'adding zero does not create a line');
assert_same([], cart_add([], 2, -5), 'adding a negative quantity does not create a line');

// ---------------------------------------------------------------------------
describe('cart_set_quantity');

assert_same([2 => 5], cart_set_quantity([2 => 1], 2, 5), 'replaces the quantity outright');
assert_same([2 => 4], cart_set_quantity([], 2, 4), 'sets a quantity for a product not yet in the cart');
assert_same([], cart_set_quantity([2 => 3], 2, 0), 'quantity 0 removes the line');
assert_same([], cart_set_quantity([2 => 3], 2, -1), 'a negative quantity clamps to 0 and removes the line');
assert_same([7 => 2], cart_set_quantity([2 => 3, 7 => 2], 2, 0), 'removing one line leaves the others');

// ---------------------------------------------------------------------------
describe('cart_adjust');

assert_same([2 => 3], cart_adjust([2 => 2], 2, 1), 'increases the quantity by the delta');
assert_same([2 => 1], cart_adjust([2 => 2], 2, -1), 'decreases the quantity by the delta');
assert_same([], cart_adjust([2 => 1], 2, -1), 'decreasing to 0 removes the line');
assert_same([], cart_adjust([2 => 1], 2, -9), 'quantity never goes below 0');
assert_same([], cart_adjust([], 2, -1), 'decreasing a product that is not in the cart is a no-op');
assert_same([2 => 1], cart_adjust([], 2, 1), 'increasing a product not in the cart adds it');

// ---------------------------------------------------------------------------
describe('cart_remove');

assert_same([], cart_remove([2 => 4], 2), 'removes the line regardless of quantity');
assert_same([7 => 1], cart_remove([2 => 4, 7 => 1], 2), 'removes only the requested product');
assert_same([7 => 1], cart_remove([7 => 1], 2), 'removing an absent product is a no-op');

// ---------------------------------------------------------------------------
describe('cart_quantity');

assert_same(4, cart_quantity([2 => 4], 2), 'reports the quantity of a product in the cart');
assert_same(0, cart_quantity([2 => 4], 9), 'reports 0 for a product not in the cart');
assert_same(0, cart_quantity([], 1), 'reports 0 for an empty cart');

// ---------------------------------------------------------------------------
describe('cart_lines');

$lines = cart_lines([1 => 2, 3 => 1], $catalog);
assert_same(2, count($lines), 'builds one line per cart entry');
assert_same(1, $lines[0]['product_id'], 'line carries the product id');
assert_same('Trailhead 45L Backpack', $lines[0]['product_name'], 'line carries the product name');
assert_same(2, $lines[0]['quantity'], 'line carries the ordered quantity');
assert_same(12999, $lines[0]['cost_cents'], 'unit cost is converted to whole cents');
assert_same(25998, $lines[0]['line_total_cents'], 'line total is unit cost times quantity');
assert_same(24900, $lines[1]['line_total_cents'], 'a quantity of 1 gives a line total of the unit cost');

assert_same([], cart_lines([], $catalog), 'an empty cart produces no lines');
assert_same(
    [],
    cart_lines([99 => 2], $catalog),
    'a cart entry with no matching product is skipped rather than fataling'
);
assert_same(
    1,
    count(cart_lines([1 => 2, 99 => 1], $catalog)),
    'a missing product does not discard the valid lines alongside it'
);

// ---------------------------------------------------------------------------
describe('cart_totals');

// $129.99 x 2 + $249.00 = $508.98 pre-tax.
$totals = cart_totals(cart_lines([1 => 2, 3 => 1], $catalog));
assert_same(50898, $totals['items_total_cents'], 'items total is the sum of the line totals');
assert_same(2545, $totals['tax_cents'], 'tax is 5% of the pre-tax total');
assert_same(5090, $totals['shipping_cents'], 'shipping and handling is 10% of the pre-tax total');
assert_same(58533, $totals['order_total_cents'], 'order total is items plus tax plus shipping');

$empty = cart_totals([]);
assert_same(0, $empty['items_total_cents'], 'an empty cart has a zero items total');
assert_same(0, $empty['tax_cents'], 'an empty cart has zero tax');
assert_same(0, $empty['shipping_cents'], 'an empty cart has zero shipping');
assert_same(0, $empty['order_total_cents'], 'an empty cart has a zero order total');

// $10.10 pre-tax puts tax on an exact half cent (50.5), which is where float
// accumulation would drift. Integer cents plus round() keeps it deterministic.
$halfCent = cart_totals([['line_total_cents' => 1010]]);
assert_same(51, $halfCent['tax_cents'], 'a half-cent tax rounds up rather than truncating');
assert_same(101, $halfCent['shipping_cents'], 'shipping on the same total rounds exactly');
assert_same(1162, $halfCent['order_total_cents'], 'order total sums the rounded components');

// ---------------------------------------------------------------------------
describe('money');

assert_same('0.00', money(0), 'formats zero cents');
assert_same('5.09', money(509), 'formats cents under a dollar boundary');
assert_same('585.33', money(58533), 'formats the worked order total');
assert_same('1,234.56', money(123456), 'groups thousands with a comma');
