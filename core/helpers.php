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
 * Read a submitted value as an integer, rejecting anything non-scalar.
 *
 * The cast matters: (int) on an array yields 1 in PHP with no warning, so a
 * crafted `product_id[]=99` would silently act on product 1 rather than being
 * rejected. Anything that is not a scalar becomes 0, which names no product.
 */
function post_int(string $key): int
{
    $raw = $_POST[$key] ?? null;

    return is_scalar($raw) ? (int) $raw : 0;
}

/**
 * Read a submitted value as a string, rejecting anything non-scalar.
 *
 * (string) on an array raises "Array to string conversion" and yields the
 * literal "Array", which on a host that displays warnings would emit output
 * before the redirect header and break it.
 */
function post_string(string $key, string $default = ''): string
{
    $raw = $_POST[$key] ?? null;

    return is_scalar($raw) ? (string) $raw : $default;
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
