<?php
/**
 * Tests for the Cart model: quantity rules, line building, and order totals.
 *
 * Cart is constructed from a plain array, so every rule is exercised with no
 * session and no web request. That is the property worth protecting: the
 * quantity and money rules must stay testable without HTTP.
 */

declare(strict_types=1);

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
describe('Cart::retain');

// A cart can outlive a product being deleted from the catalog. Left alone, the
// stale entry is counted by itemCount() but skipped by lines(), so the nav
// badge and the rendered cart disagree and nothing ever clears it.
$cart = new Cart([1 => 2, 99 => 3]);
$cart->retain([1, 3, 5]);
assert_same([1 => 2], $cart->items(), 'entries whose product is gone are dropped');
assert_same(2, $cart->itemCount(), 'the item count no longer counts the dropped entry');

$cart = new Cart([1 => 2, 3 => 1]);
$cart->retain([1, 3]);
assert_same([1 => 2, 3 => 1], $cart->items(), 'nothing is dropped when every product still exists');

$cart = new Cart([1 => 2]);
$cart->retain([]);
assert_same([], $cart->items(), 'an empty catalog empties the cart');

$cart = new Cart();
$cart->retain([1, 2]);
assert_same([], $cart->items(), 'retaining on an empty cart is a no-op');

$cart = new Cart([1 => 2, 99 => 3]);
$cart->retain(['1']);
assert_same([1 => 2], $cart->items(), 'string ids from a query result still match');

// After retain, itemCount and lines must agree — the whole point of the method.
$cart = new Cart([1 => 2, 99 => 3]);
$cart->retain(array_keys($catalog));
assert_same(
    $cart->itemCount(),
    array_sum(array_column($cart->lines($catalog), 'quantity')),
    'after retain the item count matches the rendered lines'
);

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
