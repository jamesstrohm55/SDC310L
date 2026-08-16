<?php
/**
 * Tests for product database access.
 *
 * These are integration tests: they run against the real `onlinestore`
 * database created by database/onlinestore.sql. If the database has not been
 * imported, they fail loudly rather than silently passing.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/products.php';

$pdo = require __DIR__ . '/../config/database.php';

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
describe('products_all');

$all = products_all($pdo);

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
describe('products_by_ids');

$some = products_by_ids($pdo, [3, 1]);

assert_same(2, count($some), 'returns exactly the requested products');
assert_same([1, 3], array_keys($some), 'the result is keyed by product id for direct lookup');
assert_same('Cascade 2-Person Tent', $some[3]['product_name'], 'the keyed row is the right product');
assert_same('249.00', $some[3]['product_cost'], 'the keyed row carries the exact cost');

assert_same([], products_by_ids($pdo, []), 'an empty id list returns an empty array without querying');
assert_same([], products_by_ids($pdo, [9999]), 'unknown ids return no rows rather than erroring');
assert_same(
    [1],
    array_keys(products_by_ids($pdo, [1, 9999])),
    'a mix of known and unknown ids returns only the known ones'
);

// A cart id arriving from a request is a string; it must still match.
assert_same(
    [1],
    array_keys(products_by_ids($pdo, ['1'])),
    'string ids from request input are coerced and still match'
);

// ---------------------------------------------------------------------------
describe('sql injection safety');

// products_by_ids builds an IN (...) list, which is the one place a naive
// implementation would interpolate. Two defenses have to hold: ids are cast to
// int before the query, and the values are bound rather than concatenated.

// A wholly non-numeric payload casts to 0 and therefore matches nothing.
assert_same(
    [],
    products_by_ids($pdo, ["'); DROP TABLE products; --"]),
    'a non-numeric hostile id matches no product'
);

// A payload with a leading digit casts to that digit — the SQL tail is
// discarded by the cast, so it degrades to an ordinary lookup of product 1
// rather than executing anything.
assert_same(
    [1],
    array_keys(products_by_ids($pdo, ['1); DROP TABLE products; --'])),
    'a hostile id with a numeric prefix degrades to that plain id'
);

assert_same(6, count(products_all($pdo)), 'the products table survived both hostile ids');
