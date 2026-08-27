<?php
/**
 * Catalog page controller.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Translates a request into model calls and hands the result to a view. It
 * runs no SQL of its own, emits no HTML, and sends no headers — the front
 * controller does that with what this returns.
 */

declare(strict_types=1);

final class CatalogController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET: the whole catalog, with each row showing its current cart quantity. */
    public function index(): array
    {
        $cart = SessionCart::load();

        return [
            'view' => 'catalog/index',
            'data' => [
                'pageTitle' => 'Catalog',
                'activeNav' => 'catalog',
                'products'  => (new Product($this->pdo))->all(),
                'cart'      => $cart,
                'cartCount' => $cart->itemCount(),
                'flash'     => SessionCart::flashTake(),
                'csrfToken' => SessionCart::token(),
            ],
        ];
    }
}
