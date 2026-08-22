<?php
/**
 * Tests for session storage of the cart.
 *
 * $_SESSION is an ordinary superglobal array, so these tests assign to it
 * directly and never start a real session. That is the point of keeping this
 * class thin: the storage layer is checkable without a web request.
 */

declare(strict_types=1);

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
