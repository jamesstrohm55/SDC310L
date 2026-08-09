<?php
/**
 * Shopping cart page.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Week 2 scope: page framework only. The line items below come from a
 * hardcoded placeholder array matching the quantities shown on the catalog
 * page. Week 3 replaces $placeholderCart with the session cart, joins it
 * against the onlinestore.products table, and makes Check Out functional.
 */

$pageTitle = 'Shopping Cart';
$activeNav = 'cart';

// Order total rules from the Course Project Overview.
const TAX_RATE      = 0.05;  // 5% of the pre-tax total
const SHIPPING_RATE = 0.10;  // 10% of the pre-tax total

// --- Placeholder data (Week 3 replaces this with the session cart) ---------
// Only products with a quantity of at least one appear in the cart.
$placeholderCart = [
    [
        'product_id'   => 1,
        'product_name' => 'Trailhead 45L Backpack',
        'quantity'     => 2,
        'product_cost' => 129.99,
    ],
    [
        'product_id'   => 3,
        'product_name' => 'Cascade 2-Person Tent',
        'quantity'     => 1,
        'product_cost' => 249.00,
    ],
];

// --- Order totals ----------------------------------------------------------
$itemsTotal = 0.00;
foreach ($placeholderCart as $line) {
    $itemsTotal += $line['quantity'] * $line['product_cost'];
}

$tax        = round($itemsTotal * TAX_RATE, 2);
$shipping   = round($itemsTotal * SHIPPING_RATE, 2);
$orderTotal = $itemsTotal + $tax + $shipping;

require __DIR__ . '/includes/header.php';
?>

<div class="page-intro">
    <h1>Shopping Cart</h1>
    <p>Review your order before checking out.</p>
</div>

<p class="notice">
    <strong>Week 2 build.</strong> The cart framework, line-item table, and
    order summary are in place, populated with placeholder data. The cart is
    connected to the session and the database in Week 3.
</p>

<?php if (empty($placeholderCart)): ?>

    <p class="empty-cart">Your cart is empty.</p>

<?php else: ?>

    <table class="cart-table">
        <thead>
            <tr>
                <th scope="col" class="col-id">Product ID</th>
                <th scope="col" class="col-name">Product Name</th>
                <th scope="col" class="col-qty">Quantity Ordered</th>
                <th scope="col" class="col-cost">Product Cost</th>
                <th scope="col" class="col-total">Product Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($placeholderCart as $line): ?>
            <tr>
                <td class="col-id"><?php echo (int) $line['product_id']; ?></td>
                <td class="col-name"><?php echo htmlspecialchars($line['product_name']); ?></td>
                <td class="col-qty"><?php echo (int) $line['quantity']; ?></td>
                <td class="col-cost">$<?php echo number_format($line['product_cost'], 2); ?></td>
                <td class="col-total">$<?php echo number_format($line['quantity'] * $line['product_cost'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

<div class="order-summary">
    <h2>Order Summary</h2>
    <dl>
        <dt>Total of Items Ordered</dt>
        <dd>$<?php echo number_format($itemsTotal, 2); ?></dd>

        <dt>Tax (<?php echo number_format(TAX_RATE * 100, 0); ?>%)</dt>
        <dd>$<?php echo number_format($tax, 2); ?></dd>

        <dt>Shipping &amp; Handling (<?php echo number_format(SHIPPING_RATE * 100, 0); ?>%)</dt>
        <dd>$<?php echo number_format($shipping, 2); ?></dd>

        <dt class="grand">Order Total</dt>
        <dd class="grand">$<?php echo number_format($orderTotal, 2); ?></dd>
    </dl>
</div>

<p class="page-actions">
    <a class="btn btn-primary" href="index.php">Continue Shopping</a>
    <button type="button" class="btn btn-checkout" disabled title="Available in Week 3">Check Out</button>
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
