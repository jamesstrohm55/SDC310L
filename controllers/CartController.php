<?php
/**
 * Shopping cart controller.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * index() renders the cart. The five mutation methods each apply one model
 * call and return a redirect rather than rendering, which is the
 * Post/Redirect/Get pattern: without the redirect, refreshing the browser
 * after adding a product would silently re-submit and add it a second time.
 */

declare(strict_types=1);

final class CartController
{
    /**
     * Routes a cart form may ask to return to.
     *
     * Echoing an arbitrary submitted value into a Location header would let a
     * crafted form redirect visitors off-site, so anything unrecognized falls
     * back to the catalog.
     */
    private const RETURN_ROUTES = ['catalog', 'cart'];

    public function __construct(private PDO $pdo)
    {
    }

    /** GET: the cart's line items and order totals. */
    public function index(): array
    {
        $cart = SessionCart::load();

        // Only the products actually in the cart are fetched, rather than the
        // whole catalog, so the query stays proportional to the order.
        $products = (new Product($this->pdo))->byIds(array_keys($cart->items()));

        // Drop anything whose product has left the catalog. Without this the
        // nav badge counts entries the page does not render, and the stale
        // entry is never cleared. byIds queried exactly the cart's ids, so a
        // missing row means the product is genuinely gone — a database that
        // could not be reached would have exited in config/database.php.
        $cart->retain(array_keys($products));
        SessionCart::save($cart);

        $lines = $cart->lines($products);

        return [
            'view' => 'cart/index',
            'data' => [
                'pageTitle' => 'Shopping Cart',
                'activeNav' => 'cart',
                'lines'     => $lines,
                'totals'    => Cart::totals($lines),
                'cartCount' => $cart->itemCount(),
                'flash'     => SessionCart::flashTake(),
                'csrfToken' => SessionCart::token(),
            ],
        ];
    }

    /** POST: add one of a product to the cart. */
    public function add(): array
    {
        $productId = $this->productId();

        if ($this->productExists($productId)) {
            $cart = SessionCart::load();
            $cart->add($productId);
            SessionCart::save($cart);
        }

        return $this->back();
    }

    /** POST: raise a product's quantity by one. */
    public function increase(): array
    {
        $productId = $this->productId();

        if ($this->productExists($productId)) {
            $cart = SessionCart::load();
            $cart->adjust($productId, 1);
            SessionCart::save($cart);
        }

        return $this->back();
    }

    /**
     * POST: lower a product's quantity by one.
     *
     * No existence check: this can only shrink the cart. Cart::adjust clamps
     * at 0, so this can never produce a negative quantity even if the button
     * is submitted when the cart is already empty.
     */
    public function decrease(): array
    {
        $cart = SessionCart::load();
        $cart->adjust($this->productId(), -1);
        SessionCart::save($cart);

        return $this->back();
    }

    /** POST: drop a product from the cart entirely. */
    public function remove(): array
    {
        $cart = SessionCart::load();
        $cart->remove($this->productId());
        SessionCart::save($cart);

        return $this->back();
    }

    /** POST: empty the cart and confirm the order. */
    public function checkout(): array
    {
        $cart = SessionCart::load();
        $cart->clear();
        SessionCart::save($cart);
        SessionCart::flashSet('Thank you for your order. Your cart has been cleared.');

        // Checking out always returns to the catalog, whatever page it came from.
        return ['redirect' => Router::DEFAULT_ACTION];
    }

    /**
     * The submitted product id, or 0 when absent, non-numeric, or not scalar.
     *
     * post_int() rejects arrays rather than casting them: (int) on an array is
     * 1 in PHP, so `product_id[]=99` would otherwise act on product 1.
     */
    private function productId(): int
    {
        return post_int('product_id');
    }

    /**
     * Whether a product id names a real catalog product.
     *
     * Guards the two operations that can create a new cart line. Week 3 let
     * any id in; it then sat in the session and the cart page silently
     * skipped it, so the visitor's click appeared to do nothing.
     */
    private function productExists(int $productId): bool
    {
        return $productId > 0 && (new Product($this->pdo))->byId($productId) !== null;
    }

    /** Redirect back to the page the form was submitted from. */
    private function back(): array
    {
        $requested = post_string('return', Router::DEFAULT_ACTION);

        return [
            'redirect' => in_array($requested, self::RETURN_ROUTES, true)
                ? $requested
                : Router::DEFAULT_ACTION,
        ];
    }
}
