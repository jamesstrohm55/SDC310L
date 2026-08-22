<?php
/**
 * Shopping cart model.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * The cart holds a map of product_id => quantity. It is constructed from a
 * plain array, which is what keeps every rule here testable with no session
 * and no web request; SessionCart is the separate, thin layer that loads one
 * of these in and saves it back out.
 *
 * Money is handled in whole cents throughout. Costs arrive from the database
 * as exact DECIMAL strings and are converted once by Money::toCents(), so no
 * sequence of additions or percentages can accumulate binary float error into
 * an order total.
 */

declare(strict_types=1);

final class Cart
{
    /** Order total rules from the Course Project Overview. */
    public const TAX_RATE      = 0.05;  // 5% of the pre-tax total
    public const SHIPPING_RATE = 0.10;  // 10% of the pre-tax total

    /** @var array<int,int> product_id => quantity */
    private array $items = [];

    /**
     * @param array<int|string, int|string> $items
     */
    public function __construct(array $items = [])
    {
        // The constructor is the sanitizing boundary. Carts arrive from the
        // session, which may have been written by an older build or edited by
        // hand, so nothing is trusted to already be an integer.
        foreach ($items as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity  = (int) $quantity;

            if ($productId > 0 && $quantity > 0) {
                $this->items[$productId] = $quantity;
            }
        }
    }

    /** @return array<int,int> */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function quantity(int $productId): int
    {
        return $this->items[$productId] ?? 0;
    }

    /** Total number of items ordered, counting quantities. */
    public function itemCount(): int
    {
        return array_sum($this->items);
    }

    /** Add to the quantity already in the cart. */
    public function add(int $productId, int $quantity = 1): void
    {
        $this->setQuantity($productId, $this->quantity($productId) + $quantity);
    }

    /**
     * Set an absolute quantity, clamped to 0 or more.
     *
     * Zero is not stored as a line: a product with no quantity is simply not
     * in the cart, which is what lets the cart page show only ordered
     * products. Clamping here rather than in the controller means a forged
     * POST cannot drive a quantity negative.
     */
    public function setQuantity(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($productId);

            return;
        }

        $this->items[$productId] = $quantity;
    }

    /** Move a quantity up or down by a delta, never below 0. */
    public function adjust(int $productId, int $delta): void
    {
        $this->setQuantity($productId, $this->quantity($productId) + $delta);
    }

    /** Drop a product from the cart entirely, whatever its quantity. */
    public function remove(int $productId): void
    {
        unset($this->items[$productId]);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Drop any cart entry whose product is not in the given id list.
     *
     * A cart can outlive a product being deleted from the catalog. Left in
     * place, a stale entry is counted by itemCount() but skipped by lines(),
     * so the navigation badge and the rendered cart disagree and nothing ever
     * clears the entry. Pruning against the products the catalog actually
     * returned keeps the two in step.
     *
     * @param array<int|string> $productIds
     */
    public function retain(array $productIds): void
    {
        $keep = array_flip(array_map('intval', $productIds));

        $this->items = array_intersect_key($this->items, $keep);
    }

    /**
     * Join the cart against catalog rows to produce display lines.
     *
     * A cart entry whose product is no longer in the catalog is skipped
     * rather than fataling, so a stale session cannot break the page.
     *
     * @param  array<int,array> $products product_id => row, as Product::byIds returns
     * @return list<array{product_id:int, product_name:string, quantity:int,
     *                    cost_cents:int, line_total_cents:int}>
     */
    public function lines(array $products): array
    {
        $lines = [];

        foreach ($this->items as $productId => $quantity) {
            if (!isset($products[$productId])) {
                continue;
            }

            $costCents = Money::toCents((string) $products[$productId]['product_cost']);

            $lines[] = [
                'product_id'       => $productId,
                'product_name'     => (string) $products[$productId]['product_name'],
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
     * Static because it is a pure computation over lines and depends on no
     * instance state.
     *
     * Tax and shipping are each a percentage of the pre-tax total, rounded to
     * the cent independently, and the order total is the sum of the three
     * rounded figures — so the printed lines always add up to the printed
     * total.
     *
     * @param  list<array{line_total_cents:int}> $lines
     * @return array{items_total_cents:int, tax_cents:int,
     *               shipping_cents:int, order_total_cents:int}
     */
    public static function totals(array $lines): array
    {
        $itemsTotal = 0;
        foreach ($lines as $line) {
            $itemsTotal += (int) $line['line_total_cents'];
        }

        $tax      = (int) round($itemsTotal * self::TAX_RATE);
        $shipping = (int) round($itemsTotal * self::SHIPPING_RATE);

        return [
            'items_total_cents' => $itemsTotal,
            'tax_cents'         => $tax,
            'shipping_cents'    => $shipping,
            'order_total_cents' => $itemsTotal + $tax + $shipping,
        ];
    }
}
