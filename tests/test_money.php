<?php
/**
 * Tests for currency conversion and display formatting.
 *
 * All money in this application is whole integer cents. These two functions
 * are the only places a value crosses between cents and something else, so
 * they are where a rounding error would enter.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Money.php';

// ---------------------------------------------------------------------------
describe('Money::toCents');

assert_same(12999, Money::toCents('129.99'), 'converts an exact decimal string to cents');
assert_same(24900, Money::toCents('249.00'), 'converts a whole-dollar amount');
assert_same(0, Money::toCents('0.00'), 'converts zero');
assert_same(5, Money::toCents('0.05'), 'converts an amount under a dime');
assert_same(2850, Money::toCents('28.50'), 'converts an amount ending in a zero cent');

// 129.99 has no exact binary representation, so a bare (int) cast of
// 129.99 * 100 truncates to 12998. Rounding after the multiply is what
// keeps the conversion honest.
assert_same(12999, Money::toCents('129.99'), 'rounding absorbs float representation error');
assert_same(1899, Money::toCents('18.99'), 'another amount that truncates without rounding');

// ---------------------------------------------------------------------------
describe('Money::format');

assert_same('0.00', Money::format(0), 'formats zero cents');
assert_same('5.09', Money::format(509), 'formats cents under a dollar boundary');
assert_same('585.33', Money::format(58533), 'formats the worked order total');
assert_same('1,234.56', Money::format(123456), 'groups thousands with a comma');
assert_same('0.05', Money::format(5), 'pads a single-digit cent value');
