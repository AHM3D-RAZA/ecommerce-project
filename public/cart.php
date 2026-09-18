<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Database.php';
Session::start();

$db = new Database();
$cart = Session::get('cart', []);

// --- Add a product (from a product card or the product detail page) ---
if (isset($_GET['add'])) {
    $id = (int) $_GET['add'];
    $qty = isset($_GET['qty']) ? max(1, (int) $_GET['qty']) : 1;

    $product = $db->selectOne("SELECT id, stock FROM products WHERE id = ? AND status = 1", [$id]);

    if ($product) {
        $stock = (int) $product['stock'];
        $currentQty = (int) ($cart[$id] ?? 0);
        $newQty = min($currentQty + $qty, max($stock, 0));

        if ($stock === 0) {
            Session::flash('error', "Sorry, that item is out of stock.");
        } elseif ($newQty <= 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = $newQty;
            Session::flash('success', 'Product added to your cart.');
        }
        Session::set('cart', $cart);
    }

    header('Location: cart.php');
    exit;
}

// --- Remove a single product ---
if (isset($_GET['remove'])) {
    $id = (int) $_GET['remove'];
    unset($cart[$id]);
    Session::set('cart', $cart);
    header('Location: cart.php');
    exit;
}

// --- Empty the whole cart ---
if (isset($_GET['clear'])) {
    Session::set('cart', []);
    header('Location: cart.php');
    exit;
}

// --- Update quantities from the cart table form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $qtys = $_POST['qty'] ?? [];

    foreach ($qtys as $id => $qty) {
        $id = (int) $id;
        $qty = (int) $qty;

        if (!isset($cart[$id])) {
            continue;
        }

        if ($qty <= 0) {
            unset($cart[$id]);
            continue;
        }

        $product = $db->selectOne("SELECT stock FROM products WHERE id = ?", [$id]);
        $stock = $product ? (int) $product['stock'] : 0;

        if ($stock <= 0) {
            // Sold out - don't let it sit in the cart at qty 1.
            unset($cart[$id]);
        } else {
            $cart[$id] = min($qty, $stock);
        }
    }

    Session::set('cart', $cart);
    Session::flash('success', 'Cart updated.');
    header('Location: cart.php');
    exit;
}

$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
// $cartItems / $cartTotal / $cartCount are already built by header.php from the session cart
?>
            <nav aria-label="breadcrumb" class="breadcrumb-nav border-0 mb-0">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
                    </ol>
                </div>
            </nav>

            <div class="page-content">
                <div class="cart">
                    <div class="container">
                        <?php if (empty($cartItems)): ?>
                            <div class="row justify-content-center">
                                <div class="col-md-6 text-center py-5">
                                    <i class="icon-shopping-cart" style="font-size: 3rem; color: #ccc;"></i>
                                    <h3 class="mt-3">Your cart is empty</h3>
                                    <p>Looks like you haven't added anything yet.</p>
                                    <a href="category.php" class="btn btn-primary btn-round"><span>Start Shopping</span><i class="icon-long-arrow-right"></i></a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-lg-8">
                                    <form action="cart.php" method="post">
                                        <table class="table table-cart table-mobile">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Price</th>
                                                    <th>Quantity</th>
                                                    <th>Total</th>
                                                    <th></th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php foreach ($cartItems as $item): ?>
                                                    <tr>
                                                        <td class="product-col">
                                                            <div class="product">
                                                                <figure class="product-media">
                                                                    <img src="<?= htmlspecialchars(shop_image($item['image'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                                                </figure>
                                                                <h3 class="product-title">
                                                                    <a href="product.php?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                                                                </h3>
                                                            </div>
                                                        </td>

                                                        <td class="price-col">$<?= number_format($item['price'], 2) ?></td>

                                                        <td class="quantity-col">
                                                            <input type="number" name="qty[<?= (int) $item['id'] ?>]" class="form-control" value="<?= (int) $item['qty'] ?>" min="1" max="<?= (int) $item['stock'] ?>" step="1">
                                                        </td>

                                                        <td class="total-col">$<?= number_format($item['line_total'], 2) ?></td>

                                                        <td class="remove-col">
                                                            <a href="cart.php?remove=<?= (int) $item['id'] ?>" class="btn-remove" title="Remove Product"><i class="icon-close"></i></a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>

                                        <div class="cart-bottom">
                                            <a href="category.php" class="btn btn-outline-dark-2 btn-round"><span>Continue Shopping</span><i class="icon-long-arrow-right"></i></a>
                                            <button type="submit" name="update_cart" value="1" class="btn btn-outline-dark-2 btn-round"><span>Update Cart</span><i class="icon-refresh"></i></button>
                                        </div>
                                    </form>
                                </div><!-- End .col-lg-8 -->

                                <div class="col-lg-4">
                                    <div class="summary summary-cart">
                                        <h3 class="summary-title">Cart Total</h3>

                                        <table class="table table-summary">
                                            <tbody>
                                                <tr class="summary-subtotal">
                                                    <td>Subtotal:</td>
                                                    <td>$<?= number_format($cartTotal, 2) ?></td>
                                                </tr>
                                                <tr class="summary-shipping">
                                                    <td>Shipping:</td>
                                                    <td>Calculated at checkout</td>
                                                </tr>
                                                <tr class="summary-total">
                                                    <td>Total:</td>
                                                    <td>$<?= number_format($cartTotal, 2) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <a href="checkout.php" class="btn btn-primary btn-order btn-round">Proceed to Checkout</a>
                                    </div><!-- End .summary -->
                                </div><!-- End .col-lg-4 -->
                            </div><!-- End .row -->
                        <?php endif; ?>
                    </div><!-- End .container -->
                </div><!-- End .cart -->
            </div><!-- End .page-content -->
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
