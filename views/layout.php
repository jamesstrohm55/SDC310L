<?php
/**
 * Site layout.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Wraps every rendered view. Receives from the controller:
 *   $content    string  The rendered view, already escaped by its template.
 *   $pageTitle  string  Text appended to the store name in <title>.
 *   $activeNav  string  'catalog' or 'cart'; highlights the current nav link.
 *   $cartCount  int     Items in the cart; shown as a badge on the cart link.
 */

$storeName = 'Summit Outfitters';
$pageTitle = $pageTitle ?? 'Online Store';
$activeNav = $activeNav ?? '';
$cartCount = (int) ($cartCount ?? 0);
$content   = $content ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($storeName . ' — ' . $pageTitle); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?php echo url(); ?>">
            <span class="brand-mark">&#9650;</span>
            <span class="brand-name"><?php echo e($storeName); ?></span>
        </a>
        <nav class="site-nav">
            <a href="<?php echo url('catalog'); ?>"<?php echo $activeNav === 'catalog' ? ' class="active"' : ''; ?>>Catalog</a>
            <a href="<?php echo url('cart'); ?>"<?php echo $activeNav === 'cart' ? ' class="active"' : ''; ?>>View Cart<?php
                if ($cartCount > 0): ?><span class="nav-badge"><?php echo $cartCount; ?></span><?php
                endif; ?></a>
        </nav>
    </div>
</header>

<main class="wrap">
<?php echo $content; ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <p>SDC310L Course Project — James Strohm (jamstr441)</p>
        <p class="build-note">Week 4 build: the application is re-architected to Model-View-Controller. Every request enters through a single front controller.</p>
    </div>
</footer>

</body>
</html>
