<?php
/**
 * Request routing.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Routing is a whitelist: an action that is not in the table below does not
 * reach any controller. Each route also declares the HTTP verb it accepts,
 * which is what preserves Post/Redirect/Get — a cart mutation reached by GET
 * (a stray link, a bookmarked form target, a refreshed POST) resolves to
 * nothing and changes no state.
 *
 * resolve() is pure: action and verb in, route or null out. No superglobals,
 * no side effects. The front controller applies the outcome; this class only
 * decides it.
 */

declare(strict_types=1);

final class Router
{
    public const DEFAULT_ACTION = 'catalog';

    /** action => [controller class, method, accepted verb] */
    private const ROUTES = [
        'catalog'       => ['CatalogController', 'index',    'GET'],
        'cart'          => ['CartController',    'index',    'GET'],
        'cart.add'      => ['CartController',    'add',      'POST'],
        'cart.remove'   => ['CartController',    'remove',   'POST'],
        'cart.increase' => ['CartController',    'increase', 'POST'],
        'cart.decrease' => ['CartController',    'decrease', 'POST'],
        'cart.checkout' => ['CartController',    'checkout', 'POST'],
    ];

    /** @return list<string> */
    public static function actions(): array
    {
        return array_keys(self::ROUTES);
    }

    /** An absent, empty, or whitespace action means the catalog. */
    public static function normalize(?string $action): string
    {
        $action = trim((string) $action);

        return $action === '' ? self::DEFAULT_ACTION : $action;
    }

    /**
     * Whether the action names a route at all, regardless of verb.
     *
     * The front controller needs this to tell "no such page" (404) apart from
     * "right page, wrong verb" (redirect), since resolve() returns null for
     * both.
     */
    public static function exists(?string $action): bool
    {
        return isset(self::ROUTES[self::normalize($action)]);
    }

    /**
     * The route for an action and verb, or null if there is none.
     *
     * @return array{controller:string, method:string, verb:string}|null
     */
    public static function resolve(?string $action, string $method): ?array
    {
        $action = self::normalize($action);

        if (!isset(self::ROUTES[$action])) {
            return null;
        }

        [$controller, $handler, $verb] = self::ROUTES[$action];

        if (strtoupper($method) !== $verb) {
            return null;
        }

        return [
            'controller' => $controller,
            'method'     => $handler,
            'verb'       => $verb,
        ];
    }
}
