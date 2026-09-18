<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../config/paypal.php';
Session::start();
Auth::requireLogin('login.php?redirect=checkout.php');

$db = new Database();
$userId = Session::get('user_id');
$cart = Session::get('cart', []);

// Build the cart the same way header.php does, but re-check stock fresh -
// someone else may have bought the last one since the cart page was loaded.
$cartItems = [];
$cartTotal = 0;

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
        $lineTotal = $row['price'] * $qty;
        $cartTotal += $lineTotal;
        $cartItems[] = array_merge($row, ['qty' => $qty, 'line_total' => $lineTotal]);
    }
    $stmt->close();
}

if (empty($cartItems)) {
    Session::flash('error', "Your cart is empty - add something before checking out.");
    header('Location: cart.php');
    exit;
}

$user = $db->selectOne("SELECT name, email FROM users WHERE id = ?", [$userId]);
$nameParts = explode(' ', $user['name'], 2);

$checkoutError = null;
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
    $paypalOrderId = trim($_POST['paypal_order_id'] ?? '');
    $paypalStatus = trim($_POST['paypal_status'] ?? '');

    $v = new Validator();
    $v->required($old['first_name'], 'first name')
      ->required($old['last_name'], 'last name')
      ->required($old['address'], 'address')
      ->required($old['city'], 'city')
      ->required($old['state'], 'state')
      ->required($old['zip'], 'zip code')
      ->required($old['phone'], 'phone number')
      ->required($old['email'], 'email address')->email($old['email'], 'email address');

    if (!in_array($paymentMethod, ['cod', 'paypal'], true)) {
        $checkoutError = 'Please choose a payment method.';
    } elseif ($paymentMethod === 'paypal' && ($paypalOrderId === '' || $paypalStatus !== 'COMPLETED')) {
        $checkoutError = "PayPal payment wasn't completed. Please try again or choose Cash on Delivery.";
    } elseif ($v->fails()) {
        $checkoutError = $v->first();
    } else {
        // Re-check stock one more time right before writing the order
        $stockOk = true;
        foreach ($cartItems as $item) {
            $fresh = $db->selectOne("SELECT stock FROM products WHERE id = ?", [$item['id']]);
            if (!$fresh || (int) $fresh['stock'] < $item['qty']) {
                $stockOk = false;
                break;
            }
        }

        if (!$stockOk) {
            $checkoutError = 'Sorry, one of the items in your cart just sold out. Please review your cart and try again.';
        } else {
            // Write the order, its items and the stock decrements atomically, and
            // never decrement below zero: if anything fails or another buyer took
            // the last unit mid-checkout, everything rolls back.
            $conn = $db->getConnection();
            $conn->begin_transaction();

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

                $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $paymentStatus = $paymentMethod === 'paypal' ? 'completed' : 'pending';
                $transactionId = $paymentMethod === 'paypal' ? $paypalOrderId : null;

                $orderId = $db->insert(
                    "INSERT INTO orders (user_id, order_number, total_amount, payment_method, payment_status, order_status, transaction_id, shipping_address)
                     VALUES (?, ?, ?, ?, ?, 'processing', ?, ?)",
                    [$userId, $orderNumber, $cartTotal, $paymentMethod, $paymentStatus, $transactionId, $shippingAddress]
                );

                foreach ($cartItems as $item) {
                    $db->insert(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)",
                        [$orderId, $item['id'], $item['qty'], $item['price'], $item['line_total']]
                    );

                    // Atomic guard: the decrement only lands if there is still enough stock.
                    $decremented = $db->run(
                        "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?",
                        [$item['qty'], $item['id'], $item['qty']]
                    );
                    if ($decremented === 0) {
                        throw new RuntimeException('Insufficient stock for product ' . $item['id']);
                    }
                }

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                $checkoutError = 'Sorry, one of the items in your cart just sold out. Please review your cart and try again.';
            }

            if (empty($checkoutError)) {
                Session::set('cart', []);
                Session::flash('success', 'Your order has been placed - thank you!');
                header('Location: order-confirmation.php?order=' . urlencode($orderNumber));
                exit;
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
                                    <h2 class="checkout-title">Billing Details</h2><!-- End .checkout-title -->
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label>First Name *</label>
                                            <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($old['first_name']) ?>" required>
                                        </div><!-- End .col-sm-6 -->

                                        <div class="col-sm-6">
                                            <label>Last Name *</label>
                                            <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($old['last_name']) ?>" required>
                                        </div><!-- End .col-sm-6 -->
                                    </div><!-- End .row -->

                                    <label>Street address *</label>
                                    <input type="text" class="form-control" name="address" placeholder="House number and street name" value="<?= htmlspecialchars($old['address']) ?>" required>

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label>Town / City *</label>
                                            <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($old['city']) ?>" required>
                                        </div><!-- End .col-sm-6 -->

                                        <div class="col-sm-6">
                                            <label>State / County *</label>
                                            <input type="text" class="form-control" name="state" value="<?= htmlspecialchars($old['state']) ?>" required>
                                        </div><!-- End .col-sm-6 -->
                                    </div><!-- End .row -->

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label>Postcode / ZIP *</label>
                                            <input type="text" class="form-control" name="zip" value="<?= htmlspecialchars($old['zip']) ?>" required>
                                        </div><!-- End .col-sm-6 -->

                                        <div class="col-sm-6">
                                            <label>Phone *</label>
                                            <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required>
                                        </div><!-- End .col-sm-6 -->
                                    </div><!-- End .row -->

                                    <label>Email address *</label>
                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>

                                    <label>Order notes (optional)</label>
                                    <textarea class="form-control" name="notes" cols="30" rows="4" placeholder="Notes about your order, e.g. special notes for delivery"><?= htmlspecialchars($old['notes']) ?></textarea>
                                </div><!-- End .col-lg-9 -->

                                <aside class="col-lg-3">
                                    <div class="summary">
                                        <h3 class="summary-title">Your Order</h3><!-- End .summary-title -->

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
                                                </tr><!-- End .summary-subtotal -->
                                                <tr>
                                                    <td>Shipping:</td>
                                                    <td>Free shipping</td>
                                                </tr>
                                                <tr class="summary-total">
                                                    <td>Total:</td>
                                                    <td>$<?= number_format($cartTotal, 2) ?></td>
                                                </tr><!-- End .summary-total -->
                                            </tbody>
                                        </table><!-- End .table table-summary -->

                                        <div class="accordion-summary" id="accordion-payment">
                                            <div class="card">
                                                <div class="card-header" id="heading-cod">
                                                    <h2 class="card-title">
                                                        <label class="custom-control custom-radio mb-0">
                                                            <input type="radio" class="custom-control-input" name="payment_method" id="pay-cod" value="cod" <?= $paymentMethod === 'paypal' ? '' : 'checked' ?>>
                                                            <span class="custom-control-label">Cash on Delivery</span>
                                                        </label>
                                                    </h2>
                                                </div><!-- End .card-header -->
                                                <div class="card-body">
                                                    Pay with cash when your order is delivered to your address.
                                                </div><!-- End .card-body -->
                                            </div><!-- End .card -->

                                            <div class="card">
                                                <div class="card-header" id="heading-paypal">
                                                    <h2 class="card-title">
                                                        <label class="custom-control custom-radio mb-0">
                                                            <input type="radio" class="custom-control-input" name="payment_method" id="pay-paypal" value="paypal" <?= $paymentMethod === 'paypal' ? 'checked' : '' ?>>
                                                            <span class="custom-control-label">PayPal</span>
                                                        </label>
                                                    </h2>
                                                </div><!-- End .card-header -->
                                                <div class="card-body">
                                                    Pay securely via PayPal Sandbox. You'll be asked to confirm the payment before the order is placed.
                                                </div><!-- End .card-body -->
                                            </div><!-- End .card -->
                                        </div><!-- End .accordion-summary -->

                                        <input type="hidden" name="paypal_order_id" id="paypal_order_id" value="">
                                        <input type="hidden" name="paypal_status" id="paypal_status" value="">

                                        <button type="submit" name="place_order" value="1" id="place-order-btn" class="btn btn-outline-primary-2 btn-order btn-block">
                                            <span class="btn-text">Place Order</span>
                                        </button>

                                        <div id="paypal-button-container" style="display: none;"></div>
                                    </div><!-- End .summary -->
                                </aside><!-- End .col-lg-3 -->
                            </div><!-- End .row -->
                        </form>
                    </div><!-- End .container -->
                </div><!-- End .checkout -->
            </div><!-- End .page-content -->
        </main><!-- End .main -->

        <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(PAYPAL_CLIENT_ID) ?>&currency=<?= urlencode(PAYPAL_CURRENCY) ?>"></script>
        <script>
        (function () {
            var codRadio = document.getElementById('pay-cod');
            var paypalRadio = document.getElementById('pay-paypal');
            var placeOrderBtn = document.getElementById('place-order-btn');
            var paypalContainer = document.getElementById('paypal-button-container');
            var form = document.getElementById('checkout-form');

            function togglePaymentUI() {
                var usingPaypal = paypalRadio.checked;
                placeOrderBtn.style.display = usingPaypal ? 'none' : '';
                paypalContainer.style.display = usingPaypal ? 'block' : 'none';
            }

            codRadio.addEventListener('change', togglePaymentUI);
            paypalRadio.addEventListener('change', togglePaymentUI);
            togglePaymentUI();

            if (window.paypal) {
                paypal.Buttons({
                    createOrder: function (data, actions) {
                        return actions.order.create({
                            purchase_units: [{
                                amount: { value: '<?= number_format($cartTotal, 2, '.', '') ?>' }
                            }]
                        });
                    },
                    onApprove: function (data, actions) {
                        if (!form.reportValidity()) {
                            return Promise.resolve();
                        }
                        return actions.order.capture().then(function (details) {
                            document.getElementById('paypal_order_id').value = details.id;
                            document.getElementById('paypal_status').value = details.status;
                            form.submit();
                        });
                    },
                    onError: function () {
                        alert('There was a problem with the PayPal payment. Please try again, or choose Cash on Delivery instead.');
                    }
                }).render('#paypal-button-container');
            }
        })();
        </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
