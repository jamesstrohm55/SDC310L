<?php
/**
 * Catalog view.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Receives from CatalogController::index:
 *   $products   array   Catalog rows.
 *   $cart       Cart    Used only to read each row's current quantity.
 *   $cartCount  int
 *   $csrfToken  string  Echoed into every form; verified by the front controller.
 *
 * Week 5: the catalog moved from a table to a card grid. A table was the
 * wrong element here — the catalog is a set of products being browsed, not a
 * grid of values being compared down columns, and on a phone the table could
 * only be made to fit by hiding the descriptions. Every attribute the course
 * project requires is still shown on each card: product id, name,
 * description, cost, and quantity ordered.
 *
 * This template renders and nothing else: no query, no session, no state
 * change.
 */
?>
<div class="page-intro">
    <h1>Product Catalog</h1>
    <p>Gear for the trail, the ridgeline, and everything between.</p>
</div>

<?php if ($products === []): ?>

    <p class="empty-state">
        The catalog is empty. If you are running this locally, import
        <code>database/onlinestore.sql</code> to load the products.
    </p>

<?php else: ?>

    <ul class="product-grid">
    <?php foreach ($products as $product): ?>
        <?php
        $id       = (int) $product['product_id'];
        $quantity = $cart->quantity($id);
        // Every control names its product, so a screen-reader user can tell
        // six identical "Add to Cart" buttons apart.
        $label    = e($product['product_name']);
        ?>
        <li class="product-card<?php echo $quantity > 0 ? ' is-in-cart' : ''; ?>">

            <div class="product-head">
                <h2 class="product-name"><?php echo $label; ?></h2>
                <p class="product-sku">Product ID <span><?php echo $id; ?></span></p>
            </div>

            <p class="product-desc"><?php echo e($product['product_description']); ?></p>

            <div class="product-meta">
                <p class="product-price">
                    <span class="meta-label">Cost</span>
                    <span class="price-value">$<?php
                        echo Money::format(Money::toCents((string) $product['product_cost'])); ?></span>
                </p>

                <div class="product-qty">
                    <span class="meta-label" aria-hidden="true">Quantity ordered</span>
                    <div class="qty-control">
                        <form method="post" action="<?php echo url('cart.decrease'); ?>">
                            <?php echo csrf_input($csrfToken); ?>
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="return" value="catalog">
                            <button type="submit" class="btn btn-step"
                                    aria-label="Decrease quantity of <?php echo $label; ?>"
                                    <?php echo $quantity < 1 ? 'disabled' : ''; ?>>&minus;</button>
                        </form>
                        <?php /* The label is a real text node immediately before the
                                 number, not an aria-labelledby reference: a bare <span>
                                 has no role, so most screen readers compute no accessible
                                 name for one and the association is silently dropped.
                                 This is visually hidden and read in document order with
                                 the number, so the visible label above is aria-hidden to
                                 avoid announcing the same thing twice. */ ?>
                        <span class="visually-hidden">Quantity ordered for <?php
                            echo $label; ?>: </span>
                        <span class="qty-value" data-qty="<?php echo $id; ?>"><?php
                            echo $quantity; ?></span>
                        <form method="post" action="<?php echo url('cart.increase'); ?>">
                            <?php echo csrf_input($csrfToken); ?>
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="return" value="catalog">
                            <button type="submit" class="btn btn-step"
                                    aria-label="Increase quantity of <?php echo $label; ?>">+</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="product-actions">
                <form method="post" action="<?php echo url('cart.add'); ?>">
                    <?php echo csrf_input($csrfToken); ?>
                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="return" value="catalog">
                    <button type="submit" class="btn btn-add"
                            aria-label="Add <?php echo $label; ?> to cart">Add to Cart</button>
                </form>
                <form method="post" action="<?php echo url('cart.remove'); ?>">
                    <?php echo csrf_input($csrfToken); ?>
                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="return" value="catalog">
                    <button type="submit" class="btn btn-remove"
                            aria-label="Remove <?php echo $label; ?> from cart"
                            <?php echo $quantity < 1 ? 'disabled' : ''; ?>>Remove</button>
                </form>
            </div>

        </li>
    <?php endforeach; ?>
    </ul>

<?php endif; ?>

<p class="page-actions">
    <a class="btn btn-primary" href="<?php echo url('cart'); ?>">View Cart<?php
        echo $cartCount > 0 ? ' (' . (int) $cartCount . ')' : ''; ?></a>
</p>
