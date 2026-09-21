<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../config/stripe.php';

Session::start();
Auth::requireLogin('login.php?redirect=checkout.php');

$db = new Database();
$userId = Session::get('user_id');
$cart = Session::get('cart', []);

$cartItems = [];
$cartTotal = 0.0;

if (!empty($cart)) {
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id, name, slug, price, stock FROM products WHERE id IN ($placeholders) AND status = 1");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $qty = min((int) $cart[$row['id']], (int) $row['stock']);
        if ($qty <= 0) {
            continue;
        }
        $lineTotal = (float) $row['price'] * $qty;
        $cartTotal += $lineTotal;
        $cartItems[] = array_merge($row, ['qty' => $qty, 'line_total' => $lineTotal]);
    }
    $stmt->close();
}

if (empty($cartItems)) {
    Session::flash('error', 'Your cart is empty - add something before checking out.');
    header('Location: cart.php');
    exit;
}

$user = $db->selectOne('SELECT name, email FROM users WHERE id = ?', [$userId]);
$nameParts = explode(' ', $user['name'], 2);

$checkoutError = null;

// Coming back from Stripe's page via "Back"/"Cancel": the order was saved before
// redirecting, so release its stock and mark it failed. The cart is untouched.
if (isset($_GET['cancel'], $_GET['order'])) {
    $cancelled = $db->selectOne(
        "SELECT id FROM orders WHERE order_number = ? AND user_id = ? AND payment_method = 'stripe' AND payment_status = 'pending'",
        [trim($_GET['order']), $userId]
    );

    if ($cancelled) {
        $conn = $db->getConnection();
        $conn->begin_transaction();
        try {
            $db->run("UPDATE orders SET payment_status = 'failed', order_status = 'cancelled' WHERE id = ?", [$cancelled['id']]);
            foreach ($db->select('SELECT product_id, quantity FROM order_items WHERE order_id = ?', [$cancelled['id']]) as $row) {
                $db->run('UPDATE products SET stock = stock + ? WHERE id = ?', [(int) $row['quantity'], (int) $row['product_id']]);
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Stripe cancel cleanup failed: ' . $e->getMessage());
        }
    }

    $checkoutError = 'Payment was cancelled, so your order was not placed. Your cart is still here if you want to try again.';
}

$old = [
    'first_name' => $nameParts[0] ?? '',
    'last_name' => $nameParts[1] ?? '',
    'address' => '',
    'city' => '',
    'state' => '',
    'zip' => '',
    'phone' => '',
    'email' => $user['email'],
    'notes' => '',
];
$paymentMethod = 'cod';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    foreach ($old as $key => $default) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    $paymentMethod = $_POST['payment_method'] ?? 'cod';
    $v = new Validator();
    $v->required($old['first_name'], 'first name')
      ->required($old['last_name'], 'last name')
      ->required($old['address'], 'address')
      ->required($old['city'], 'city')
      ->required($old['state'], 'state')
      ->required($old['zip'], 'zip code')
      ->required($old['phone'], 'phone number')
      ->required($old['email'], 'email address')->email($old['email'], 'email address');

    if (!in_array($paymentMethod, ['cod', 'stripe'], true)) {
        $checkoutError = 'Please choose a payment method.';
    } elseif ($v->fails()) {
        $checkoutError = $v->first();
    } else {
        $stockOk = true;
        foreach ($cartItems as $item) {
            $fresh = $db->selectOne('SELECT stock FROM products WHERE id = ?', [$item['id']]);
            if (!$fresh || (int) $fresh['stock'] < $item['qty']) {
                $stockOk = false;
                break;
            }
        }

        if (!$stockOk) {
            $checkoutError = 'Sorry, one of the items in your cart just sold out. Please review your cart and try again.';
        } else {
            $conn = $db->getConnection();
            $conn->begin_transaction();
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            try {
                $shippingAddress = sprintf(
                    "%s %s\n%s\n%s, %s %s\nPhone: %s",
                    $old['first_name'],
                    $old['last_name'],
                    $old['address'],
                    $old['city'],
                    $old['state'],
                    $old['zip'],
                    $old['phone']
                );
                if ($old['notes'] !== '') {
                    $shippingAddress .= "\nNotes: " . $old['notes'];
                }

                $orderId = $db->insert(
                    "INSERT INTO orders (user_id, order_number, total_amount, payment_method, payment_status, order_status, transaction_id, shipping_address)
                     VALUES (?, ?, ?, ?, 'pending', 'processing', NULL, ?)",
                    [$userId, $orderNumber, number_format($cartTotal, 2, '.', ''), $paymentMethod, $shippingAddress]
                );

                foreach ($cartItems as $item) {
                    $db->insert(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)",
                        [$orderId, $item['id'], $item['qty'], $item['price'], $item['line_total']]
                    );

                    $decremented = $db->run(
                        "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?",
                        [$item['qty'], $item['id'], $item['qty']]
                    );
                    if ($decremented === 0) {
                        throw new RuntimeException('Insufficient stock for product ' . $item['id']);
                    }
                }

                if ($paymentMethod === 'stripe') {
                    $checkoutSession = stripe_api('POST', '/checkout/sessions', [
                        'mode' => 'payment',
                        // {CHECKOUT_SESSION_ID} must stay literal - Stripe swaps in the real id
                        'success_url' => stripe_return_url('order-confirmation.php', ['order' => $orderNumber]) . '&session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url' => stripe_return_url('checkout.php', ['cancel' => 1, 'order' => $orderNumber]),
                        'customer_email' => $old['email'],
                        'payment_method_types' => ['card'],
                        'line_items' => [[
                            'price_data' => [
                                'currency' => STRIPE_CURRENCY,
                                'unit_amount' => (int) round($cartTotal * 100),
                                'product_data' => [
                                    'name' => 'Order ' . $orderNumber,
                                ],
                            ],
                            'quantity' => 1,
                        ]],
                        'metadata' => [
                            'order_number' => $orderNumber,
                            'order_id' => (string) $orderId,
                            'user_id' => (string) $userId,
                        ],
                    ]);

                    $db->run(
                        "UPDATE orders SET transaction_id = ? WHERE id = ?",
                        [$checkoutSession['id'] ?? null, $orderId]
                    );

                    $conn->commit();
                    header('Location: ' . $checkoutSession['url']);
                    exit;
                }

                $conn->commit();
                Session::set('cart', []);
                Session::flash('success', 'Your order has been placed - thank you!');
                header('Location: order-confirmation.php?order=' . urlencode($orderNumber));
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Checkout failed: ' . $e->getMessage());
                $checkoutError = 'Sorry, we could not complete your checkout. Please try again.';
                if (Env::bool('APP_DEBUG')) {
                    $checkoutError .= ' (' . $e->getMessage() . ')';
                }
            }
        }
    }
}

$pageTitle = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
?>
            <div class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')">
                <div class="container">
                    <h1 class="page-title">Checkout<span>Shop</span></h1>
                </div><!-- End .container -->
            </div><!-- End .page-header -->

            <nav aria-label="breadcrumb" class="breadcrumb-nav">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="category.php">Shop</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Checkout</li>
                    </ol>
                </div><!-- End .container -->
            </nav><!-- End .breadcrumb-nav -->

            <div class="page-content">
                <div class="checkout">
                    <div class="container">
                        <?php if ($checkoutError): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($checkoutError) ?></div>
                        <?php endif; ?>

                        <form action="checkout.php" method="post" id="checkout-form">
                            <div class="row">
                                <div class="col-lg-9">
                                    <h2 class="checkout-title">Billing Details</h2>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label>First Name *</label>
                                            <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($old['first_name']) ?>" required>
                                        </div>

                                        <div class="col-sm-6">
                                            <label>Last Name *</label>
                                            <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($old['last_name']) ?>" required>
                                        </div>
                                    </div>

                                    <label>Street address *</label>
                                    <input type="text" class="form-control" name="address" placeholder="House number and street name" value="<?= htmlspecialchars($old['address']) ?>" required>

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label>Town / City *</label>
                                            <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($old['city']) ?>" required>
                                        </div>

                                        <div class="col-sm-6">
                                            <label>State / County *</label>
                                            <input type="text" class="form-control" name="state" value="<?= htmlspecialchars($old['state']) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label>Postcode / ZIP *</label>
                                            <input type="text" class="form-control" name="zip" value="<?= htmlspecialchars($old['zip']) ?>" required>
                                        </div>

                                        <div class="col-sm-6">
                                            <label>Phone *</label>
                                            <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required>
                                        </div>
                                    </div>

                                    <label>Email address *</label>
                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>

                                    <label>Order notes (optional)</label>
                                    <textarea class="form-control" name="notes" cols="30" rows="4" placeholder="Notes about your order, e.g. special notes for delivery"><?= htmlspecialchars($old['notes']) ?></textarea>
                                </div>

                                <aside class="col-lg-3">
                                    <div class="summary">
                                        <h3 class="summary-title">Your Order</h3>

                                        <table class="table table-summary">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cartItems as $item): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($item['name']) ?> <strong>&times; <?= (int) $item['qty'] ?></strong></td>
                                                        <td>$<?= number_format($item['line_total'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="summary-subtotal">
                                                    <td>Subtotal:</td>
                                                    <td>$<?= number_format($cartTotal, 2) ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Shipping:</td>
                                                    <td>Free shipping</td>
                                                </tr>
                                                <tr class="summary-total">
                                                    <td>Total:</td>
                                                    <td>$<?= number_format($cartTotal, 2) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <div class="accordion-summary" id="accordion-payment">
                                            <div class="card">
                                                <div class="card-header" id="heading-cod">
                                                    <h2 class="card-title">
                                                        <label class="custom-control custom-radio mb-0">
                                                            <input type="radio" class="custom-control-input" name="payment_method" id="pay-cod" value="cod" <?= $paymentMethod === 'stripe' ? '' : 'checked' ?>>
                                                            <span class="custom-control-label">Cash on Delivery</span>
                                                        </label>
                                                    </h2>
                                                </div>
                                                <div class="card-body">Pay with cash when your order is delivered to your address.</div>
                                            </div>

                                            <div class="card">
                                                <div class="card-header" id="heading-stripe">
                                                    <h2 class="card-title">
                                                        <label class="custom-control custom-radio mb-0">
                                                            <input type="radio" class="custom-control-input" name="payment_method" id="pay-stripe" value="stripe" <?= $paymentMethod === 'stripe' ? 'checked' : '' ?>>
                                                            <span class="custom-control-label">Stripe</span>
                                                        </label>
                                                    </h2>
                                                </div>
                                                <div class="card-body">Pay securely with Stripe Sandbox using a test card.</div>
                                            </div>
                                        </div>

                                        <button type="submit" name="place_order" value="1" class="btn btn-outline-primary-2 btn-order btn-block checkout-place-order">
                                            <span class="btn-text">Place Order</span>
                                        </button>
                                    </div>
                                </aside>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <style>
            .checkout-place-order {
                min-height: 48px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                transition: all 0.2s ease;
            }

            .checkout-place-order .btn-text {
                color: inherit !important;
            }

            .checkout-place-order:hover,
            .checkout-place-order:focus,
            .checkout-place-order:active {
                background-color: #c96 !important;
                border-color: #c96 !important;
                color: #fff !important;
                box-shadow: none !important;
            }

            .checkout-place-order:hover .btn-text,
            .checkout-place-order:focus .btn-text,
            .checkout-place-order:active .btn-text {
                color: #fff !important;
            }
        </style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
