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
    private const CSRF_KEY  = 'csrf';

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

    /**
     * This session's CSRF token, or null if none has been minted.
     *
     * Verifying a submitted token uses this rather than token(), so a POST
     * from a client with no session is rejected without creating one. token()
     * would mint and store a value, meaning any anonymous drive-by POST left a
     * session file behind.
     */
    public static function existingToken(): ?string
    {
        $stored = $_SESSION[self::CSRF_KEY] ?? null;

        return is_string($stored) && $stored !== '' ? $stored : null;
    }

    /** Whether a one-time message is already waiting to be shown. */
    public static function hasFlash(): bool
    {
        return isset($_SESSION[self::FLASH_KEY]);
    }

    /**
     * This session's CSRF token, minting one on first use.
     *
     * The token is stable for the life of the session rather than rotated per
     * request. Rotating it would invalidate every form already rendered in the
     * visitor's browser, so a back button or a second open tab would have its
     * next click rejected as a forgery.
     *
     * A stored value that is not a non-empty string — a session written by a
     * build that predates this feature, or one edited by hand — is replaced
     * rather than trusted, so Csrf::matches() is never handed a non-string.
     */
    public static function token(): string
    {
        $stored = $_SESSION[self::CSRF_KEY] ?? null;

        if (!is_string($stored) || $stored === '') {
            $stored = Csrf::generate();
            $_SESSION[self::CSRF_KEY] = $stored;
        }

        return $stored;
    }

    /** Flash types the stylesheet renders. Anything else falls back to warning. */
    private const FLASH_TYPES = ['success', 'warning'];

    /**
     * Store a one-time message to show on the next page load.
     *
     * The type travels with the message because the two flashes this
     * application raises are opposite in meaning: a completed order and a
     * rejected request. Rendering the rejection in the success styling would
     * tell the visitor their action worked when it did not.
     */
    public static function flashSet(string $message, string $type = 'success'): void
    {
        $_SESSION[self::FLASH_KEY] = [
            'message' => $message,
            'type'    => in_array($type, self::FLASH_TYPES, true) ? $type : 'warning',
        ];
    }

    /**
     * Read and clear the one-time message, if any.
     *
     * The key is unset before any decision about the stored value, so a
     * malformed entry is discarded rather than left to be re-read on every
     * subsequent page load.
     *
     * @return array{message:string, type:string}|null
     */
    public static function flashTake(): ?array
    {
        if (!isset($_SESSION[self::FLASH_KEY])) {
            return null;
        }

        $stored = $_SESSION[self::FLASH_KEY];
        unset($_SESSION[self::FLASH_KEY]);

        // Week 4 stored a bare string under this key. A session created by
        // that build can outlive the upgrade, so it is read rather than
        // fataling on the array access.
        if (is_string($stored)) {
            return ['message' => $stored, 'type' => 'success'];
        }

        if (!is_array($stored) || !isset($stored['message']) || !is_scalar($stored['message'])) {
            return null;
        }

        $type = $stored['type'] ?? '';

        return [
            'message' => (string) $stored['message'],
            'type'    => in_array($type, self::FLASH_TYPES, true) ? (string) $type : 'warning',
        ];
    }
}
