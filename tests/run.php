<?php
/**
 * Test runner for the SDC310L course project.
 *
 *     php tests/run.php
 *
 * Exits 0 when every assertion passes, 1 otherwise, so a non-zero exit is a
 * real failure signal and not something a pipe can swallow.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/lib.php';

$suites = [
    __DIR__ . '/test_money.php',
    __DIR__ . '/test_cart.php',
    __DIR__ . '/test_session_cart.php',
    __DIR__ . '/test_products.php',
];

foreach ($suites as $suite) {
    echo "\n=== " . basename($suite) . " ===\n";
    require $suite;
}

report_and_exit();
