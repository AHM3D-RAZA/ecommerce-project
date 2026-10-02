<?php

require_once __DIR__ . '/Session.php';

/**
 * Cart storage.
 *
 * The cart lives in the `cart` table - one row per product - rather than in
 * the session. Each browser gets a random `cart_key` token stored in its
 * session, so guests and signed-in customers behave identically and the cart
 * survives signing in and out, exactly as it did before.
 */
class Cart
{
    // This session's cart token, created the first time it is needed.
    public static function key()
    {
        Session::start();

        $key = Session::get('cart_key');

        if (!is_string($key) || $key === '') {
            $key = bin2hex(random_bytes(16));
            Session::set('cart_key', $key);
        }

        return $key;
    }

    /**
     * The cart's lines joined to their products, oldest first. The quantity is
     * returned as `qty` so the templates can use it directly.
     *
     * $onlyActive drops hidden products - checkout uses it so you can't order
     * something the admin has taken off the storefront.
     */
    public static function items($db, $onlyActive = false)
    {
        $statusSql = $onlyActive ? 'AND p.status = 1' : '';

        $items = $db->select(
            "SELECT p.id, p.name, p.slug, p.price, p.image, p.stock, c.quantity AS qty
             FROM cart c JOIN products p ON p.id = c.product_id
             WHERE c.cart_key = ? $statusSql
             ORDER BY c.id ASC",
            [self::key()]
        );

        // Templates read a per-line total, so provide it here instead of making
        // every consumer (cart page, cart dropdown, ...) recompute it.
        foreach ($items as $i => $item) {
            $items[$i]['line_total'] = (float) $item['price'] * (int) $item['qty'];
        }

        return $items;
    }

    // Unit count and value for a list from items().
    public static function summarise(array $items)
    {
        $count = 0;
        $total = 0.0;

        foreach ($items as $item) {
            $count += (int) $item['qty'];
            $total += (float) $item['price'] * (int) $item['qty'];
        }

        return ['count' => $count, 'total' => $total];
    }

    /**
     * Adds a product, bumping the quantity already in the cart. Never lets the
     * cart hold more than the stock actually available.
     *
     * Returns ['success' => bool, 'message' => string].
     */
    public static function add($db, $productId, $qty = 1)
    {
        $productId = (int) $productId;
        $qty = max(1, (int) $qty);

        $product = $db->selectOne(
            "SELECT id, stock FROM products WHERE id = ? AND status = 1",
            [$productId]
        );

        if (!$product) {
            return ['success' => false, 'message' => 'Sorry, that product is no longer available.'];
        }

        $stock = (int) $product['stock'];

        if ($stock === 0) {
            return ['success' => false, 'message' => 'Sorry, that item is out of stock.'];
        }

        $key = self::key();
        $existing = $db->selectOne(
            "SELECT id, quantity FROM cart WHERE cart_key = ? AND product_id = ?",
            [$key, $productId]
        );

        $newQty = $existing
            ? min($stock, (int) $existing['quantity'] + $qty)
            : min($stock, $qty);

        if ($existing) {
            $db->run("UPDATE cart SET quantity = ? WHERE id = ?", [$newQty, $existing['id']]);
        } else {
            $db->run(
                "INSERT INTO cart (cart_key, product_id, quantity) VALUES (?, ?, ?)",
                [$key, $productId, $newQty]
            );
        }

        return ['success' => true, 'message' => 'Added to your cart.'];
    }

    /**
     * Sets the quantity for one line. Zero or less removes it, and so does a
     * product that has since sold out.
     */
    public static function setQuantity($db, $productId, $qty)
    {
        $productId = (int) $productId;
        $qty = (int) $qty;

        if ($qty <= 0) {
            return self::remove($db, $productId);
        }

        $product = $db->selectOne("SELECT stock FROM products WHERE id = ?", [$productId]);
        $stock = $product ? (int) $product['stock'] : 0;

        if ($stock <= 0) {
            // Sold out - don't let it sit in the cart at qty 1.
            return self::remove($db, $productId);
        }

        $db->run(
            "UPDATE cart SET quantity = ? WHERE cart_key = ? AND product_id = ?",
            [min($qty, $stock), self::key(), $productId]
        );

        return ['success' => true, 'message' => 'Cart updated.'];
    }

    public static function remove($db, $productId)
    {
        $db->run(
            "DELETE FROM cart WHERE cart_key = ? AND product_id = ?",
            [self::key(), (int) $productId]
        );

        return ['success' => true, 'message' => 'Item removed from your cart.'];
    }

    public static function clear($db)
    {
        $db->run("DELETE FROM cart WHERE cart_key = ?", [self::key()]);
    }
}