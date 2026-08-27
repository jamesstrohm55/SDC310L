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
        'flash'     => SessionCart::flashTake(),
    ]);
    exit;
}

// Every state-changing route is a POST, so every POST must carry this
// session's CSRF token. The check lives here rather than in the five
// CartController methods for the same reason the verb check does: it is
// request plumbing, and one choke point cannot be forgotten by a route added
// later, whereas five separate call sites can.
//
// A rejected request changes nothing and is sent to the catalog. The visitor
// is told why, because the honest common cause is not an attack — it is a
// form left open until the session expired.
if ($route['verb'] === 'POST'
    && !Csrf::matches(SessionCart::token(), post_string('csrf_token'))) {
    SessionCart::flashSet(
        'That request could not be verified, so nothing was changed. '
        . 'Your session may have expired — please try again.',
        'warning'
    );

    redirect_to(Router::DEFAULT_ACTION);
}

$pdo = require __DIR__ . '/config/database.php';

$controller = new $route['controller']($pdo);
$result     = $controller->{$route['method']}();

if (isset($result['redirect'])) {
    redirect_to((string) $result['redirect']);
}

echo View::render((string) $result['view'], (array) $result['data']);
