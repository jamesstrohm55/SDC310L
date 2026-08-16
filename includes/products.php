<?php
/**
 * Product catalog data access.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Every query is a prepared statement. Costs are returned as the exact
 * DECIMAL strings MySQL sends ('129.99'), not floats, so no precision is lost
 * before the cart converts them to whole cents.
 *
 * Week 4 note: these two functions become the Product model when the
 * application is re-architected to MVC.
 */

declare(strict_types=1);

const PRODUCT_COLUMNS = 'product_id, product_name, product_description, product_cost';

/**
 * Every product in the catalog, ordered by id.
 *
 * @return array<int, array{product_id:int, product_name:string,
 *                          product_description:?string, product_cost:string}>
 */
function products_all(PDO $pdo): array
{
    $sql = 'SELECT ' . PRODUCT_COLUMNS . ' FROM products ORDER BY product_id';
    $rows = $pdo->query($sql)->fetchAll();

    return array_map('product_normalize', $rows);
}

/**
 * The requested products, keyed by product id for direct lookup.
 *
 * Unknown ids are simply absent from the result; they are not an error, since
 * a cart can outlive a product being deleted from the catalog.
 *
 * @param  array<int|string> $ids
 * @return array<int, array{product_id:int, product_name:string,
 *                          product_description:?string, product_cost:string}>
 */
function products_by_ids(PDO $pdo, array $ids): array
{
    // Ids arrive from the session or a request, so they may be strings.
    // Casting first means a non-numeric id becomes 0 and matches nothing,
    // rather than reaching the query as text.
    $ids = array_values(array_unique(array_map('intval', $ids)));

    if ($ids === []) {
        return [];
    }

    // One placeholder per id: the values are bound, never interpolated.
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT ' . PRODUCT_COLUMNS . ' FROM products'
         . ' WHERE product_id IN (' . $placeholders . ')'
         . ' ORDER BY product_id';

    $statement = $pdo->prepare($sql);
    $statement->execute($ids);

    $byId = [];
    foreach ($statement->fetchAll() as $row) {
        $row = product_normalize($row);
        $byId[$row['product_id']] = $row;
    }

    return $byId;
}

/**
 * Give a raw database row consistent PHP types.
 *
 * MySQL hands back every column as a string over this driver; product_id is
 * an integer everywhere else in the application, and product_cost stays a
 * string so its decimal precision survives.
 */
function product_normalize(array $row): array
{
    $row['product_id'] = (int) $row['product_id'];

    return $row;
}
