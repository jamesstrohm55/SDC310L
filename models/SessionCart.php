<?php
/**
 * Session storage for the cart.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * This is the only file in the application that names $_SESSION. The cart
 * rules themselves live in Cart, which is constructed from a plain array and
 * therefore stays testable without a web request; this class is the thin
 * layer that loads one in and saves it back out.
 */

declare(strict_types=1);

final class SessionCart
{
    private const CART_KEY  = 'cart';
    private const FLASH_KEY = 'flash';

    /** Start the session unless one is already running. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * The current cart.
     *
     * Whatever is in the session is handed to the Cart constructor, which
     * discards anything that is not a positive id with a positive quantity.
     * A session written by an older build, or hand-edited, therefore cannot
     * feed bad values into the cart rules.
     */
    public static function load(): Cart
    {
        $stored = $_SESSION[self::CART_KEY] ?? [];

        return new Cart(is_array($stored) ? $stored : []);
    }

    public static function save(Cart $cart): void
    {
        $_SESSION[self::CART_KEY] = $cart->items();
    }

    /** Store a one-time message to show on the next page load. */
    public static function flashSet(string $message): void
    {
        $_SESSION[self::FLASH_KEY] = $message;
    }

    /** Read and clear the one-time message, if any. */
    public static function flashTake(): ?string
    {
        if (!isset($_SESSION[self::FLASH_KEY])) {
            return null;
        }

        $message = (string) $_SESSION[self::FLASH_KEY];
        unset($_SESSION[self::FLASH_KEY]);

        return $message;
    }
}
