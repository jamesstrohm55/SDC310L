<?php
/**
 * Application bootstrap.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Registers class autoloading and loads the view helpers. Everything that
 * needs the application's classes requires this file first: the front
 * controller and the test runner.
 *
 * There is no Composer here. The project is graded on a stock XAMPP install
 * and adding a dependency manager for nine classes is not a trade worth
 * making.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

/**
 * Map a class name to a file under models/, controllers/, or core/.
 *
 * The name is validated against a plain PHP identifier before it is used in
 * a path, so no class name can escape those three directories.
 */
spl_autoload_register(static function (string $class): void {
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class) !== 1) {
        return;
    }

    foreach (['models', 'controllers', 'core'] as $directory) {
        $path = APP_ROOT . '/' . $directory . '/' . $class . '.php';

        if (is_file($path)) {
            require_once $path;

            return;
        }
    }
});

// Free functions cannot be autoloaded, so the helpers are loaded outright.
require_once __DIR__ . '/helpers.php';
