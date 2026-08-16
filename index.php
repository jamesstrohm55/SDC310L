<?php
/**
 * Catalog page.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Week 3: products are read from the onlinestore database and the per-row
 * controls post to cart-action.php, which updates the session cart and
 * redirects back here.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/session.php';

session_begin();

$pdo      = require __DIR__ . '/config/database.php';
$products = products_all($pdo);
$cart     = session_cart();
$flash    = session_flash_take();

$pageTitle = 'Catalog';
$activeNav = 'catalog';
$cartCount = cart_item_count($cart);

require __DIR__ . '/includes/header.php';
?>

<div class="page-intro">
    <h1>Product Catalog</h1>
    <p>Gear for the trail, the ridgeline, and everything between.</p>
</div>

<?php if ($flash !== null): ?>
    <p class="notice notice-success" role="status"><?php echo htmlspecialchars($flash); ?></p>
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
                    <th scope="col" class="col-qty">Qty in Cart</th>
                    <th scope="col" class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <?php
                $id       = $product['product_id'];
                $quantity = cart_quantity($cart, $id);
                // Every control names its product, so a screen-reader user can
                // tell six identical "Add to Cart" buttons apart.
                $label    = htmlspecialchars($product['product_name']);
                ?>
                <tr>
                    <td class="col-id"><?php echo $id; ?></td>
                    <td class="col-name"><?php echo $label; ?></td>
                    <td class="col-desc"><?php echo htmlspecialchars((string) $product['product_description']); ?></td>
                    <td class="col-cost">$<?php echo money(money_to_cents($product['product_cost'])); ?></td>

                    <td class="col-qty">
                        <form method="post" action="cart-action.php" class="qty-control">
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="return" value="index.php">
                            <button type="submit" name="action" value="decrease"
                                    class="btn btn-step"
                                    aria-label="Decrease quantity of <?php echo $label; ?>"
                                    <?php echo $quantity < 1 ? 'disabled' : ''; ?>>&minus;</button>
                            <span class="qty-value"><?php echo $quantity; ?></span>
                            <button type="submit" name="action" value="increase"
                                    class="btn btn-step"
                                    aria-label="Increase quantity of <?php echo $label; ?>">+</button>
                        </form>
                    </td>

                    <td class="col-actions">
                        <form method="post" action="cart-action.php" class="action-control">
                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="return" value="index.php">
                            <button type="submit" name="action" value="add"
                                    class="btn btn-add"
                                    aria-label="Add <?php echo $label; ?> to cart">Add to Cart</button>
                            <button type="submit" name="action" value="remove"
                                    class="btn btn-remove"
                                    aria-label="Remove <?php echo $label; ?> from cart"
                                    <?php echo $quantity < 1 ? 'disabled' : ''; ?>>Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<p class="page-actions">
    <a class="btn btn-primary" href="cart.php">View Cart<?php
        echo $cartCount > 0 ? ' (' . $cartCount . ')' : ''; ?></a>
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
