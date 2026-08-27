<?php
/**
 * Site layout.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Wraps every rendered view. Receives from the controller:
 *   $content    string   The rendered view, already escaped by its template.
 *   $pageTitle  string   Text appended to the store name in <title>.
 *   $activeNav  string   'catalog' or 'cart'; highlights the current nav link.
 *   $cartCount  int      Items in the cart; shown as a badge on the cart link.
 *   $flash      ?array   ['message' => string, 'type' => 'success'|'warning'].
 *
 * Week 5: the flash message moved here from the two page templates. It was
 * rendered by identical blocks in both, which meant the 404 page could not
 * show one at all — so a request rejected for a bad CSRF token had nowhere to
 * report itself if it landed anywhere else. One block, every page.
 */

$storeName = 'Summit Outfitters';
$pageTitle = $pageTitle ?? 'Online Store';
$activeNav = $activeNav ?? '';
$cartCount = (int) ($cartCount ?? 0);
$content   = $content ?? '';
$flash     = $flash ?? null;
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

<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?php echo url(); ?>">
            <span class="brand-mark" aria-hidden="true">&#9650;</span>
            <span class="brand-text">
                <span class="brand-name"><?php echo e($storeName); ?></span>
                <span class="brand-tag">Outdoor gear, honestly priced</span>
            </span>
        </a>
        <nav class="site-nav" aria-label="Primary">
            <a href="<?php echo url('catalog'); ?>"<?php
                echo $activeNav === 'catalog' ? ' class="active" aria-current="page"' : ''; ?>>Catalog</a>
            <a href="<?php echo url('cart'); ?>"<?php
                echo $activeNav === 'cart' ? ' class="active" aria-current="page"' : ''; ?>>View Cart<?php
                if ($cartCount > 0): ?><span class="nav-badge"><?php echo $cartCount; ?><span
                    class="visually-hidden"> items in cart</span></span><?php
                endif; ?></a>
        </nav>
    </div>
</header>

<main class="wrap" id="main">
<?php if ($flash !== null): ?>
    <p class="notice notice-<?php echo e($flash['type']); ?>"
       role="<?php echo $flash['type'] === 'warning' ? 'alert' : 'status'; ?>"><?php
        echo e($flash['message']); ?></p>
<?php endif; ?>
<?php echo $content; ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <p>SDC310L Course Project — James Strohm (jamstr441)</p>
        <p class="build-note">Week 5 build: final application. Model-View-Controller throughout, every
            state-changing request verified by a CSRF token, and the whole store covered by an automated
            test suite.</p>
    </div>
</footer>

</body>
</html>
