<?php
/**
 * Catalog view.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Receives from CatalogController::index:
 *   $products  array  Catalog rows.
 *   $cart      Cart   Used only to read each row's current quantity.
 *   $flash     ?string
 *
 * This template renders and nothing else: no query, no session, no state
 * change.
 */
?>
<div class="page-intro">
    <h1>Product Catalog</h1>
    <p>Gear for the trail, the ridgeline, and everything between.</p>
</div>

<?php if ($flash !== null): ?>
    <p class="notice notice-success" role="status"><?php echo e($flash); ?></p>
<?php endif; ?>

<?php if ($products === []): ?>

    <p class="empty-state">
        The catalog is empty. If you are running this locally, import
        <code>database/onlinestore.sql</code> to load the products.
    </p>

<?php else: ?>

    <div class="table-scroll">
        <table class="catalog-table">
            <thead>
                <tr>
                    <th scope="col" class="col-id">Product ID</th>
                    <th scope="col" class="col-name">Product Name</th>
                    <th scope="col" class="col-desc">Product Description</th>
                    <th scope="col" class="col-cost">Product Cost</th>
                    <th scope="col" class="col-qty">Quantity Ordered</th>
                    <th scope="col" class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <?php
                $id       = (int) $product['product_id'];
                $quantity = $cart->quantity($id);
                // Every control names its product, so a screen-reader user can
                // tell six identical "Add to Cart" buttons apart.
                $label    = e($product['product_name']);
                ?>
                <tr>
                    <td class="col-id"><?php echo $id; ?></td>
                    <td class="col-name"><?php echo $label; ?></td>
                    <td class="col-desc"><?php echo e($product['product_description']); ?></td>
                    <td class="col-cost">$<?php echo Money::format(Money::toCents((string) $product['product_cost'])); ?></td>

                    <td class="col-qty">
                        <div class="qty-control">
                            <form method="post" action="<?php echo url('cart.decrease'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Decrease quantity of <?php echo $label; ?>"
                                        <?php echo $quantity < 1 ? 'disabled' : ''; ?>>&minus;</button>
                            </form>
                            <span class="qty-value"><?php echo $quantity; ?></span>
                            <form method="post" action="<?php echo url('cart.increase'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-step"
                                        aria-label="Increase quantity of <?php echo $label; ?>">+</button>
                            </form>
                        </div>
                    </td>

                    <td class="col-actions">
                        <div class="action-control">
                            <form method="post" action="<?php echo url('cart.add'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-add"
                                        aria-label="Add <?php echo $label; ?> to cart">Add to Cart</button>
                            </form>
                            <form method="post" action="<?php echo url('cart.remove'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="return" value="catalog">
                                <button type="submit" class="btn btn-remove"
                                        aria-label="Remove <?php echo $label; ?> from cart"
                                        <?php echo $quantity < 1 ? 'disabled' : ''; ?>>Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<p class="page-actions">
    <a class="btn btn-primary" href="<?php echo url('cart'); ?>">View Cart<?php
        echo $cartCount > 0 ? ' (' . (int) $cartCount . ')' : ''; ?></a>
</p>
