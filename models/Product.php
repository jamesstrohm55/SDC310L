<?php
/**
 * Product catalog model.
 *
 * SDC310L Online Store — James Strohm (jamstr441)
 *
 * Every SQL statement in the application lives here. Each one is a prepared
 * statement with bound values. Costs are returned as the exact DECIMAL
 * strings MySQL sends ('129.99'), not floats, so no precision is lost before
 * Money::toCents() converts them.
 */

declare(strict_types=1);

final class Product
{
    private const COLUMNS = 'product_id, product_name, product_description, product_cost';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Every product in the catalog, ordered by id.
     *
     * @return list<array{product_id:int, product_name:string,
     *                    product_description:?string, product_cost:string}>
     */
    public function all(): array
    {
        $sql  = 'SELECT ' . self::COLUMNS . ' FROM products ORDER BY product_id';
        $rows = $this->pdo->query($sql)->fetchAll();

        return array_map([self::class, 'normalize'], $rows);
    }

    /**
     * One product, or null if no such product exists.
     *
     * Null rather than an empty array so a caller cannot mistake "no such
     * product" for "a product with no fields" — the add-to-cart path depends
     * on telling those apart.
     *
     * @return array{product_id:int, product_name:string,
     *               product_description:?string, product_cost:string}|null
     */
    public function byId(int $productId): ?array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM products WHERE product_id = ? LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([$productId]);
        $row = $statement->fetch();

        return $row === false ? null : self::normalize($row);
    }

    /**
     * The requested products, keyed by product id for direct lookup.
     *
     * Unknown ids are simply absent from the result; they are not an error,
     * since a cart can outlive a product being deleted from the catalog.
     *
     * @param  array<int|string> $ids
     * @return array<int, array{product_id:int, product_name:string,
     *                          product_description:?string, product_cost:string}>
     */
    public function byIds(array $ids): array
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
        $sql = 'SELECT ' . self::COLUMNS . ' FROM products'
             . ' WHERE product_id IN (' . $placeholders . ')'
             . ' ORDER BY product_id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($ids);

        $byId = [];
        foreach ($statement->fetchAll() as $row) {
            $row = self::normalize($row);
            $byId[$row['product_id']] = $row;
        }

        return $byId;
    }

    /**
     * Give a raw database row consistent PHP types.
     *
     * MySQL hands back every column as a string over this driver; product_id
     * is an integer everywhere else in the application, and product_cost stays
     * a string so its decimal precision survives.
     */
    private static function normalize(array $row): array
    {
        $row['product_id'] = (int) $row['product_id'];

        return $row;
    }
}
