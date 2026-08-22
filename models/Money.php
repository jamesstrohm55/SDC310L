<?php
/**
 * Currency conversion and display formatting.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * All money in this application is held as whole integer cents. These two
 * methods are the only crossings between cents and another representation:
 * toCents() on the way in from the database, format() on the way out to a
 * page. Keeping both here means neither the Cart nor a view re-implements
 * the rounding rule.
 */

declare(strict_types=1);

final class Money
{
    /**
     * Convert an exact DECIMAL string from the database ('129.99') to cents.
     *
     * Rounding after the multiply absorbs the one-ulp error that reading the
     * string as a float introduces: 129.99 is not exactly representable in
     * binary, so a bare (int) cast of 129.99 * 100 truncates to 12998.
     */
    public static function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Format whole cents for display: 58533 becomes "585.33". */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
