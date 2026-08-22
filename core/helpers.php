<?php
/**
 * View and request helpers.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Three free functions the templates and the front controller need. They are
 * functions rather than static methods because a view template calls e() on
 * nearly every value it prints, and `e($x)` stays readable where
 * `View::e($x)` would not.
 */

declare(strict_types=1);

/**
 * Escape a value for HTML output.
 *
 * This is the application's only defense against injected markup, so every
 * value a template prints goes through it — product names and descriptions
 * come from the database and flash messages come from the session.
 *
 * ENT_QUOTES escapes single quotes as well as double, so a value is safe
 * inside either kind of attribute delimiter.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Build a URL for a route.
 *
 * The catalog is left implicit — url('catalog') gives 'index.php' rather than
 * 'index.php?action=catalog' — so the store's home page has one canonical
 * address instead of two that render the same thing.
 */
function url(string $action = ''): string
{
    if ($action === '' || $action === Router::DEFAULT_ACTION) {
        return 'index.php';
    }

    return 'index.php?action=' . rawurlencode($action);
}

/**
 * Send a See Other redirect to a route and stop.
 *
 * 303 rather than 302 so the browser is required to follow with GET, which is
 * what makes Post/Redirect/Get work: refreshing the destination re-runs the
 * GET, never the POST that preceded it.
 */
function redirect_to(string $action): never
{
    header('Location: ' . url($action), true, 303);
    exit;
}
