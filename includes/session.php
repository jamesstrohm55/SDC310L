<?php
/**
 * Session storage for the cart.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * This is the only file that touches $_SESSION. The cart rules themselves are
 * pure functions in includes/cart.php, which keeps them testable; this file
 * is the thin storage layer that loads a cart in and saves it back out.
 */

declare(strict_types=1);

const CART_SESSION_KEY = 'cart';

/** Start the session unless one is already running. */
function session_begin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * The current cart as product_id => quantity.
 *
 * Values are re-cast on the way out, so a session written by an older build
 * (or hand-edited) cannot feed non-integers into the cart functions.
 *
 * @return array<int,int>
 */
function session_cart(): array
{
    $stored = $_SESSION[CART_SESSION_KEY] ?? [];

    if (!is_array($stored)) {
        return [];
    }

    $cart = [];
    foreach ($stored as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity  = (int) $quantity;

        if ($productId > 0 && $quantity > 0) {
            $cart[$productId] = $quantity;
        }
    }

    return $cart;
}

/** @param array<int,int> $cart */
function session_cart_save(array $cart): void
{
    $_SESSION[CART_SESSION_KEY] = $cart;
}

/** Store a one-time message to show on the next page load. */
function session_flash_set(string $message): void
{
    $_SESSION['flash'] = $message;
}

/** Read and clear the one-time message, if any. */
function session_flash_take(): ?string
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $message = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $message;
}
