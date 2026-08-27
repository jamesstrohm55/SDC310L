<?php
/**
 * Cross-site request forgery tokens.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * The store's five cart mutations are POSTs, and a browser attaches this
 * site's session cookie to a POST no matter which site the form was served
 * from. Without a token, a page anywhere on the web could submit a form to
 * this store and the visitor's cart would change on their next visit.
 *
 * The defense is a secret the attacker cannot read: a random value held in
 * the session and echoed into every form. Same-origin policy stops a foreign
 * page from reading it, so a forged form cannot carry it.
 *
 * This class is deliberately stateless — it mints a token and compares two of
 * them. Storing the token is SessionCart's job, which is what keeps every
 * rule here checkable with no session and no web request.
 */

declare(strict_types=1);

final class Csrf
{
    /** Bytes of entropy per token. 16 gives a 32-character hex string. */
    private const BYTES = 16;

    /**
     * Mint a new token.
     *
     * random_bytes() is the cryptographically secure source and throws rather
     * than returning weak output if the system has no usable randomness, so a
     * guessable token can never be issued silently. rand() and uniqid() are
     * both predictable from a known seed or a timestamp and are unusable here.
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(self::BYTES));
    }

    /**
     * Whether a submitted token matches the one held in the session.
     *
     * Both arguments are nullable because both are genuinely absent in normal
     * operation: a session predating this feature holds no token, and a forged
     * form submits none. Neither may be treated as a match — in particular
     * two empty strings must not satisfy the check, or a request carrying no
     * token at all would pass against a session holding no token at all.
     *
     * hash_equals compares in time independent of where the first differing
     * byte falls. A plain === leaks that position through response timing,
     * which over many requests is enough to reconstruct the token one byte at
     * a time.
     */
    public static function matches(?string $expected, ?string $given): bool
    {
        if ($expected === null || $expected === '' || $given === null || $given === '') {
            return false;
        }

        return hash_equals($expected, $given);
    }
}
