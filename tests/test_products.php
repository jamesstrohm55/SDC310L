<?php
/**
 * Tests for the Product model.
 *
 * These are integration tests: they run against the real `onlinestore`
 * database created by database/onlinestore.sql. If the database has not been
 * imported, they fail loudly rather than silently passing.
 */

declare(strict_types=1);

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
