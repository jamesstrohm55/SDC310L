<?php
/**
 * Shopping cart view.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Receives from CartController::index:
 *   $lines   array  Line items, money already in whole cents.
 *   $totals  array  items_total_cents, tax_cents, shipping_cents, order_total_cents.
 *   $flash   ?string
 */
?>
<div class="page-intro">
    <h1>Shopping Cart</h1>
    <p>Review your order before checking out.</p>
</div>

<?php if ($flash !== null): ?>
    <p class="notice notice-success" role="status"><?php echo e($flash); ?></p>
<?php endif; ?>

<?php if ($lines === []): ?>

    <p class="empty-state">
        Your cart is empty. Head back to the catalog to add something to it.
    </p>

    <p class="page-actions">
        <a class="btn btn-primary" href="<?php echo url('catalog'); ?>">Continue Shopping</a>
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
                <?php
                $id    = (int) $line['product_id'];
                $label = e($line['product_name']);
                ?>
                <tr>
                    <td class="col-id"><?php echo $id; ?></td>
                    <td class="col-name"><?php echo $label; ?></td>
                    <td class="col-qty">
                        <div class="qty-control">
                            <form method="post" action="<?php echo url('cart.decrease'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="cart">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Decrease quantity of <?php echo $label; ?>">&minus;</button>
                            </form>
                            <span class="qty-value"><?php echo (int) $line['quantity']; ?></span>
                            <form method="post" action="<?php echo url('cart.increase'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="cart">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Increase quantity of <?php echo $label; ?>">+</button>
                            </form>
                        </div>
                    </td>
                    <td class="col-cost">$<?php echo Money::format((int) $line['cost_cents']); ?></td>
                    <td class="col-total">$<?php echo Money::format((int) $line['line_total_cents']); ?></td>
                    <td class="col-actions">
                        <div class="action-control">
                            <form method="post" action="<?php echo url('cart.remove'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="cart">
                                <button type="submit" class="btn btn-remove"
                                        aria-label="Remove <?php echo $label; ?> from cart">Remove</button>
                            </form>
                        </div>
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
            <dd>$<?php echo Money::format((int) $totals['items_total_cents']); ?></dd>

            <dt>Tax (<?php echo (int) round(Cart::TAX_RATE * 100); ?>%)</dt>
            <dd>$<?php echo Money::format((int) $totals['tax_cents']); ?></dd>

            <dt>Shipping &amp; Handling (<?php echo (int) round(Cart::SHIPPING_RATE * 100); ?>%)</dt>
            <dd>$<?php echo Money::format((int) $totals['shipping_cents']); ?></dd>

            <dt class="grand">Order Total</dt>
            <dd class="grand">$<?php echo Money::format((int) $totals['order_total_cents']); ?></dd>
        </dl>
    </div>

    <div class="page-actions">
        <a class="btn btn-primary" href="<?php echo url('catalog'); ?>">Continue Shopping</a>
        <form method="post" action="<?php echo url('cart.checkout'); ?>">
            <button type="submit" class="btn btn-checkout">Check Out</button>
        </form>
    </div>

<?php endif; ?>
