<?php
/**
 * Database connection.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Returns a configured PDO connection to the `onlinestore` database:
 *
 *     $pdo = require __DIR__ . '/config/database.php';
 *
 * Credentials are the XAMPP defaults (root with no password) so the project
 * runs on a stock XAMPP install with no setup beyond importing
 * database/onlinestore.sql. That is appropriate for local coursework and
 * would not be appropriate for a deployed application.
 */

declare(strict_types=1);

const DB_HOST     = '127.0.0.1';
const DB_NAME     = 'onlinestore';
const DB_USER     = 'root';
const DB_PASSWORD = '';
const DB_CHARSET  = 'utf8mb4';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

$options = [
    // Surface database problems as exceptions instead of silent false returns.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Associative arrays only; the duplicate numeric columns are never used.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Real server-side prepares, so placeholders are never string-interpolated.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    return new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (PDOException $e) {
    // The message can carry connection details, so it goes to the log rather
    // than the page. The visitor gets a plain, actionable sentence.
    error_log('SDC310L database connection failed: ' . $e->getMessage());

    $message = 'The store is temporarily unavailable because the database could not be '
        . 'reached. If you are running this locally, start MySQL in the XAMPP '
        . 'control panel and import database/onlinestore.sql.';

    // Week 5: exit() given a string prints it and exits with status 0. On a
    // web request that is harmless, because the 503 above already carries the
    // failure. On the command line it is not: tests/run.php requires this
    // file, and a database that was simply not running made the suite stop
    // part-way through and still report success to the shell — the exact
    // masked failure a test suite exists to prevent. The command line gets a
    // real non-zero status and writes to stderr instead of stdout.
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'FATAL: ' . $message . PHP_EOL);
        exit(1);
    }

    http_response_code(503);
    exit($message);
}
