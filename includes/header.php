<?php
/**
 * Shared page header.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Including pages may set two variables before the include:
 *   $pageTitle  string  Text appended to the store name in <title>.
 *   $activeNav  string  'catalog' or 'cart'; highlights the current nav link.
 *
 * Week 4 note: this file is the seam the shared view layout is extracted from
 * when the application is re-architected to MVC.
 */

$storeName = 'Summit Outfitters';
$pageTitle = $pageTitle ?? 'Online Store';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($storeName . ' — ' . $pageTitle); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php">
            <span class="brand-mark">&#9650;</span>
            <span class="brand-name"><?php echo htmlspecialchars($storeName); ?></span>
        </a>
        <nav class="site-nav">
            <a href="index.php"<?php echo $activeNav === 'catalog' ? ' class="active"' : ''; ?>>Catalog</a>
            <a href="cart.php"<?php echo $activeNav === 'cart' ? ' class="active"' : ''; ?>>View Cart</a>
        </nav>
    </div>
</header>

<main class="wrap">
