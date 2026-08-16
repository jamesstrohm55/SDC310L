<?php
/**
 * Shopping cart page.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Week 3: the session cart is joined against the onlinestore.products table
 * to build the line items, totals are computed in whole cents, and Check Out
 * clears the cart and returns to the catalog.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/session.php';

session_begin();

$cart = session_cart();

// Only the products actually in the cart are fetched, rather than the whole
// catalog, so the query stays proportional to the order.
$pdo      = require __DIR__ . '/config/database.php';
$products = products_by_ids($pdo, array_keys($cart));

$lines  = cart_lines($cart, $products);
$totals = cart_totals($lines);

$pageTitle = 'Shopping Cart';
$activeNav = 'cart';
$cartCount = cart_item_count($cart);

require __DIR__ . '/includes/header.php';
?>

<div class="page-intro">
    <h1>Shopping Cart</h1>
    <p>Review your order before checking out.</p>
</div>

<?php if ($lines === []): ?>

    <p class="empty-state">
        Your cart is empty. Head back to the catalog to add something to it.
    </p>

    <p class="page-actions">
        <a class="btn btn-primary" href="index.php">Continue Shopping</a>
    </p>

<?php else: ?>

    <div class="table-scroll">
        <table class="cart-table">
            <thead>
                <tr>
                    <th scope="col" class="col-id">Product ID</th>
                    <th scope="col" class="col-name">Product Name</th>
                    <th scope="col" class="col-qty">Quantity Ordered</th>
                    <th scope="col" class="col-cost">Product Cost</th>
                    <th scope="col" class="col-total">Product Total</th>
                    <th scope="col" class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <?php $label = htmlspecialchars($line['product_name']); ?>
                <tr>
                    <td class="col-id"><?php echo $line['product_id']; ?></td>
                    <td class="col-name"><?php echo $label; ?></td>
                    <td class="col-qty">
                        <form method="post" action="cart-action.php" class="qty-control">
                            <input type="hidden" name="product_id" value="<?php echo $line['product_id']; ?>">
                            <input type="hidden" name="return" value="cart.php">
                            <button type="submit" name="action" value="decrease"
                                    class="btn btn-step"
                                    aria-label="Decrease quantity of <?php echo $label; ?>">&minus;</button>
                            <span class="qty-value"><?php echo $line['quantity']; ?></span>
                            <button type="submit" name="action" value="increase"
                                    class="btn btn-step"
                                    aria-label="Increase quantity of <?php echo $label; ?>">+</button>
                        </form>
                    </td>
                    <td class="col-cost">$<?php echo money($line['cost_cents']); ?></td>
                    <td class="col-total">$<?php echo money($line['line_total_cents']); ?></td>
                    <td class="col-actions">
                        <form method="post" action="cart-action.php" class="action-control">
                            <input type="hidden" name="product_id" value="<?php echo $line['product_id']; ?>">
                            <input type="hidden" name="return" value="cart.php">
                            <button type="submit" name="action" value="remove"
                                    class="btn btn-remove"
                                    aria-label="Remove <?php echo $label; ?> from cart">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="order-summary">
        <h2>Order Summary</h2>
        <dl>
            <dt>Total of Items Ordered</dt>
            <dd>$<?php echo money($totals['items_total_cents']); ?></dd>

            <dt>Tax (<?php echo (int) (CART_TAX_RATE * 100); ?>%)</dt>
            <dd>$<?php echo money($totals['tax_cents']); ?></dd>

            <dt>Shipping &amp; Handling (<?php echo (int) (CART_SHIPPING_RATE * 100); ?>%)</dt>
            <dd>$<?php echo money($totals['shipping_cents']); ?></dd>

            <dt class="grand">Order Total</dt>
            <dd class="grand">$<?php echo money($totals['order_total_cents']); ?></dd>
        </dl>
    </div>

    <div class="page-actions">
        <a class="btn btn-primary" href="index.php">Continue Shopping</a>
        <form method="post" action="cart-action.php">
            <button type="submit" name="action" value="checkout" class="btn btn-checkout">Check Out</button>
        </form>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
