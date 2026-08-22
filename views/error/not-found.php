<?php
/**
 * 404 view.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Rendered by the front controller when an ?action= value names no route.
 */
?>
<div class="page-intro">
    <h1>Page Not Found</h1>
    <p>That address does not match anything in this store.</p>
</div>

<p class="empty-state">
    The link may be out of date. The catalog and the cart are both reachable
    from the navigation above.
</p>

<p class="page-actions">
    <a class="btn btn-primary" href="<?php echo url('catalog'); ?>">Back to the Catalog</a>
</p>
