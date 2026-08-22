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
describe('post_int()');

// (int) on an array yields 1 in PHP with no warning, so a crafted
// product_id[]=99 would silently act on product 1. Non-scalar input must
// become 0, which names no product.
$_POST = ['product_id' => '7'];
assert_same(7, post_int('product_id'), 'a numeric string becomes an integer');

$_POST = ['product_id' => 7];
assert_same(7, post_int('product_id'), 'an integer passes through');

$_POST = [];
assert_same(0, post_int('product_id'), 'an absent key is 0');

$_POST = ['product_id' => ['99']];
assert_same(0, post_int('product_id'), 'an array is rejected as 0, not cast to 1');

$_POST = ['product_id' => []];
assert_same(0, post_int('product_id'), 'an empty array is rejected as 0');

$_POST = ['product_id' => null];
assert_same(0, post_int('product_id'), 'a null value is 0');

$_POST = ['product_id' => 'abc'];
assert_same(0, post_int('product_id'), 'a non-numeric string is 0');

$_POST = ['product_id' => '3abc'];
assert_same(3, post_int('product_id'), 'a numeric prefix is taken, as intval does');

// ---------------------------------------------------------------------------
describe('post_string()');

$_POST = ['return' => 'cart'];
assert_same('cart', post_string('return', 'catalog'), 'a string passes through');

$_POST = [];
assert_same('catalog', post_string('return', 'catalog'), 'an absent key gives the default');

$_POST = ['return' => ['cart']];
assert_same('catalog', post_string('return', 'catalog'), 'an array gives the default rather than warning');

$_POST = ['return' => null];
assert_same('catalog', post_string('return', 'catalog'), 'a null value gives the default');

$_POST = [];

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

// A template that throws after rendering has begun must not leave the output
// buffer open, or PHP flushes the half-drawn page at shutdown with the fatal
// appended, instead of failing cleanly.
$bufferDepth = ob_get_level();
assert_throws(
    static fn () => View::render('catalog/index', [
        'products'  => [['product_id' => 1, 'product_name' => 'x',
                         'product_description' => 'y', 'product_cost' => '1.00']],
        'cart'      => null,   // the template calls $cart->quantity() and dies
        'flash'     => null,
        'cartCount' => 0,
    ]),
    'a template that throws mid-render propagates the error'
);
assert_same($bufferDepth, ob_get_level(), 'a failed render leaves no output buffer open');
