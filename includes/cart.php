<?php
/**
 * Shopping cart rules.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * The cart is a plain map of product_id => quantity. Every function here is
 * pure: it takes a cart and returns a new one, and never touches $_SESSION.
 * That keeps the quantity and money rules testable without a web request
 * (see tests/test_cart.php) and leaves the session as a thin storage detail
 * handled in cart-action.php.
 *
 * Money is handled in whole cents. Costs arrive from the database as exact
 * DECIMAL strings and are converted once, so no sequence of additions or
 * percentages can accumulate binary float error into an order total.
 *
 * Week 4 note: these functions become the Cart model under MVC.
 */

declare(strict_types=1);

// Order total rules from the Course Project Overview.
const CART_TAX_RATE      = 0.05;  // 5% of the pre-tax total
const CART_SHIPPING_RATE = 0.10;  // 10% of the pre-tax total

/**
 * Add to the quantity already in the cart.
 *
 * @param array<int,int> $cart
 * @return array<int,int>
 */
function cart_add(array $cart, int $productId, int $quantity = 1): array
{
    return cart_set_quantity($cart, $productId, cart_quantity($cart, $productId) + $quantity);
}

/**
 * Set an absolute quantity, clamped to 0 or more.
 *
 * Zero is not stored as a line: a product with no quantity is simply not in
 * the cart, which is what lets the cart page show only ordered products.
 *
 * @param array<int,int> $cart
 * @return array<int,int>
 */
function cart_set_quantity(array $cart, int $productId, int $quantity): array
{
    if ($quantity <= 0) {
        return cart_remove($cart, $productId);
    }

    $cart[$productId] = $quantity;

    return $cart;
}

/**
 * Move a quantity up or down by a delta, never below 0.
 *
 * @param array<int,int> $cart
 * @return array<int,int>
 */
function cart_adjust(array $cart, int $productId, int $delta): array
{
    return cart_set_quantity($cart, $productId, cart_quantity($cart, $productId) + $delta);
}

/**
 * Drop a product from the cart entirely, whatever its quantity.
 *
 * @param array<int,int> $cart
 * @return array<int,int>
 */
function cart_remove(array $cart, int $productId): array
{
    unset($cart[$productId]);

    return $cart;
}

/** @param array<int,int> $cart */
function cart_quantity(array $cart, int $productId): int
{
    return (int) ($cart[$productId] ?? 0);
}

/**
 * Total number of items ordered, counting quantities.
 *
 * @param array<int,int> $cart
 */
function cart_item_count(array $cart): int
{
    return array_sum($cart);
}

/**
 * Join the cart against catalog rows to produce display lines.
 *
 * A cart entry whose product is no longer in the catalog is skipped rather
 * than fataling, so a stale session cannot break the page.
 *
 * @param array<int,int>   $cart     product_id => quantity
 * @param array<int,array> $products product_id => row, as products_by_ids returns
 * @return list<array{product_id:int, product_name:string, quantity:int,
 *                    cost_cents:int, line_total_cents:int}>
 */
function cart_lines(array $cart, array $products): array
{
    $lines = [];

    foreach ($cart as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity  = (int) $quantity;

        if ($quantity < 1 || !isset($products[$productId])) {
            continue;
        }

        $costCents = money_to_cents($products[$productId]['product_cost']);

        $lines[] = [
            'product_id'       => $productId,
            'product_name'     => $products[$productId]['product_name'],
            'quantity'         => $quantity,
            'cost_cents'       => $costCents,
            'line_total_cents' => $costCents * $quantity,
        ];
    }

    return $lines;
}

/**
 * Order totals, in whole cents.
 *
 * Tax and shipping are each a percentage of the pre-tax total, rounded to the
 * cent independently, and the order total is the sum of the three rounded
 * figures — so the printed lines always add up to the printed total.
 *
 * @param list<array{line_total_cents:int}> $lines
 * @return array{items_total_cents:int, tax_cents:int,
 *               shipping_cents:int, order_total_cents:int}
 */
function cart_totals(array $lines): array
{
    $itemsTotal = 0;
    foreach ($lines as $line) {
        $itemsTotal += (int) $line['line_total_cents'];
    }

    $tax      = (int) round($itemsTotal * CART_TAX_RATE);
    $shipping = (int) round($itemsTotal * CART_SHIPPING_RATE);

    return [
        'items_total_cents' => $itemsTotal,
        'tax_cents'         => $tax,
        'shipping_cents'    => $shipping,
        'order_total_cents' => $itemsTotal + $tax + $shipping,
    ];
}

/**
 * Convert an exact DECIMAL string from the database ('129.99') to cents.
 *
 * Rounding after the multiply absorbs the one-ulp error that reading the
 * string as a float can introduce (129.99 is not exactly representable).
 */
function money_to_cents(string $amount): int
{
    return (int) round(((float) $amount) * 100);
}

/** Format whole cents for display: 58533 becomes "585.33". */
function money(int $cents): string
{
    return number_format($cents / 100, 2);
}
