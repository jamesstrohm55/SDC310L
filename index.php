<?php
/**
 * Catalog page.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Week 2 scope: page framework only. The rows below come from a hardcoded
 * placeholder array that mirrors the seed data in database/onlinestore.sql.
 * Week 3 replaces $placeholderCatalog with a PDO query against the
 * onlinestore.products table, and gives the per-row controls real handlers.
 */

$pageTitle = 'Catalog';
$activeNav = 'catalog';

// --- Placeholder data (Week 3 replaces this with a database query) ---------
$placeholderCatalog = [
    [
        'product_id'          => 1,
        'product_name'        => 'Trailhead 45L Backpack',
        'product_description' => 'Lightweight 45 liter internal frame pack with a ventilated back panel, adjustable torso, and a rain cover stowed in the base.',
        'product_cost'        => 129.99,
        'cart_quantity'       => 2,
    ],
    [
        'product_id'          => 2,
        'product_name'        => 'Alpine 20-Degree Sleeping Bag',
        'product_description' => 'Mummy-style down bag rated to 20 degrees Fahrenheit. Compresses to the size of a loaf of bread and includes a cotton storage sack.',
        'product_cost'        => 184.50,
        'cart_quantity'       => 0,
    ],
    [
        'product_id'          => 3,
        'product_name'        => 'Cascade 2-Person Tent',
        'product_description' => 'Freestanding three-season tent with two doors, two vestibules, and a full-coverage fly. Packed weight just under five pounds.',
        'product_cost'        => 249.00,
        'cart_quantity'       => 1,
    ],
    [
        'product_id'          => 4,
        'product_name'        => 'Titanium Camp Stove',
        'product_description' => 'Folding titanium canister stove weighing under three ounces. Boils one liter of water in roughly three and a half minutes.',
        'product_cost'        => 74.95,
        'cart_quantity'       => 0,
    ],
    [
        'product_id'          => 5,
        'product_name'        => 'Summit LED Headlamp',
        'product_description' => 'Rechargeable 400 lumen headlamp with a red night mode, a lockout switch, and up to sixty hours of runtime on low.',
        'product_cost'        => 39.99,
        'cart_quantity'       => 0,
    ],
    [
        'product_id'          => 6,
        'product_name'        => 'Insulated 32oz Water Bottle',
        'product_description' => 'Double-wall vacuum insulated stainless steel bottle. Keeps drinks cold for twenty-four hours or hot for twelve.',
        'product_cost'        => 28.50,
        'cart_quantity'       => 0,
    ],
];

require __DIR__ . '/includes/header.php';
?>

<div class="page-intro">
    <h1>Product Catalog</h1>
    <p>Gear for the trail, the ridgeline, and everything between.</p>
</div>

<p class="notice">
    <strong>Week 2 build.</strong> The catalog framework and every required
    control are in place, populated with placeholder data. The controls become
    functional in Week 3 when the pages are wired to the
    <code>onlinestore</code> database.
</p>

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
    <?php foreach ($placeholderCatalog as $product): ?>
        <tr>
            <td class="col-id"><?php echo (int) $product['product_id']; ?></td>
            <td class="col-name"><?php echo htmlspecialchars($product['product_name']); ?></td>
            <td class="col-desc"><?php echo htmlspecialchars($product['product_description']); ?></td>
            <td class="col-cost">$<?php echo number_format($product['product_cost'], 2); ?></td>
            <td class="col-qty">
                <div class="qty-control">
                    <button type="button" class="btn btn-step" disabled title="Available in Week 3">&minus;</button>
                    <span class="qty-value"><?php echo (int) $product['cart_quantity']; ?></span>
                    <button type="button" class="btn btn-step" disabled title="Available in Week 3">+</button>
                </div>
            </td>
            <td class="col-actions">
                <button type="button" class="btn btn-add" disabled title="Available in Week 3">Add to Cart</button>
                <button type="button" class="btn btn-remove" disabled title="Available in Week 3">Remove</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p class="page-actions">
    <a class="btn btn-primary" href="cart.php">View Cart</a>
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
