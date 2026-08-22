<?php
/**
 * Tests for route resolution.
 *
 * Router::resolve is a pure function — action and verb in, route or null out,
 * no superglobals and no side effects — which is what makes the whole routing
 * table checkable without issuing a request.
 */

declare(strict_types=1);

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

// RESTORED IN TASK 7 — the controllers do not exist yet. Every route must
// name a controller method that actually exists, or the route table and the
// controllers can drift apart silently.
//
// foreach ($actions as $action) {
//     foreach (['GET', 'POST'] as $verb) {
//         $route = Router::resolve($action, $verb);
//         if ($route === null) {
//             continue;
//         }
//         assert_true(
//             class_exists($route['controller']),
//             $action . ' names a controller class that exists'
//         );
//         assert_true(
//             method_exists($route['controller'], $route['method']),
//             $action . ' names a controller method that exists'
//         );
//     }
// }
