<?php
/**
 * Cart mutation handler.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Every change to the cart is a POST to this script, which updates the
 * session and then redirects (Post/Redirect/Get). Redirecting matters: if the
 * catalog handled its own POST and rendered directly, refreshing the page
 * would silently re-submit and add the product a second time.
 *
 * This script renders nothing. It always ends in a redirect.
 *
 * Week 4 note: this becomes the CartController's action methods under MVC.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/session.php';

session_begin();

// Only POST may change state; a GET here is a stray link or a refresh.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect_to('index.php');
}

$action    = (string) ($_POST['action'] ?? '');
$productId = (int) ($_POST['product_id'] ?? 0);
$cart      = session_cart();

switch ($action) {
    case 'add':
        $cart = cart_add($cart, $productId);
        break;

    case 'remove':
        $cart = cart_remove($cart, $productId);
        break;

    case 'increase':
        $cart = cart_adjust($cart, $productId, 1);
        break;

    case 'decrease':
        // cart_adjust clamps at 0, so this can never produce a negative
        // quantity even if the button is submitted when the cart is empty.
        $cart = cart_adjust($cart, $productId, -1);
        break;

    case 'checkout':
        $cart = [];
        session_flash_set('Thank you for your order. Your cart has been cleared.');
        session_cart_save($cart);
        // Checking out always returns to the catalog, whatever page it came from.
        redirect_to('index.php');
        // no break — redirect_to() exits

    default:
        // Unrecognized action: change nothing and send the visitor back.
        redirect_to(safe_return_page());
}

session_cart_save($cart);
redirect_to(safe_return_page());

/**
 * The page to return to, restricted to the two pages of this application.
 *
 * Echoing an arbitrary submitted path into a Location header would let a
 * crafted form redirect visitors off-site, so anything unrecognized falls
 * back to the catalog.
 */
function safe_return_page(): string
{
    $allowed = ['index.php', 'cart.php'];
    $requested = (string) ($_POST['return'] ?? 'index.php');

    return in_array($requested, $allowed, true) ? $requested : 'index.php';
}

/** Send a See Other redirect and stop. */
function redirect_to(string $page): never
{
    header('Location: ' . $page, true, 303);
    exit;
}
