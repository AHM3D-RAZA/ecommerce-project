<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/stripe.php';

Session::start();
Auth::requireLogin('login.php');

$db = new Database();
$userId = Session::get('user_id');
$orderNumber = trim($_GET['order'] ?? '');

$order = $orderNumber !== '' ? $db->selectOne(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ?',
    [$orderNumber, $userId]
) : null;

if (!$order) {
    header('Location: account.php');
    exit;
}

$items = $db->select(
    'SELECT oi.*, p.name, p.slug FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?',
    [$order['id']]
);

$paymentMethodLabels = ['cod' => 'Cash on Delivery', 'stripe' => 'Stripe'];
$paymentStatusLabels = ['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed'];
$orderStatusLabels = ['processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

$sessionId = trim($_GET['session_id'] ?? '');
if ($order['payment_method'] === 'stripe' && $order['payment_status'] === 'pending' && $sessionId !== '') {
    try {
        $checkout = stripe_api('GET', '/checkout/sessions/' . urlencode($sessionId));
        $paid = ($checkout['payment_status'] ?? '') === 'paid' || ($checkout['status'] ?? '') === 'complete';
        if ($paid) {
            $db->run(
                "UPDATE orders SET payment_status = 'completed', transaction_id = ? WHERE id = ?",
                [$checkout['payment_intent'] ?? $checkout['id'], $order['id']]
            );
            $order['payment_status'] = 'completed';
            $order['transaction_id'] = $checkout['payment_intent'] ?? $checkout['id'];
        } else {
            $db->run("UPDATE orders SET payment_status = 'failed' WHERE id = ?", [$order['id']]);
            $order['payment_status'] = 'failed';
        }
    } catch (Throwable $e) {
        // Leave it pending for manual review if Stripe cannot be validated.
    }
}

$pageTitle = 'Order Confirmation';
require_once __DIR__ . '/../includes/header.php';
?>
            <div class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')">
                <div class="container">
                    <h1 class="page-title">Order Confirmed<span>Shop</span></h1>
                </div><!-- End .container -->
            </div><!-- End .page-header -->

            <nav aria-label="breadcrumb" class="breadcrumb-nav">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Order Confirmation</li>
                    </ol>
                </div><!-- End .container -->
            </nav><!-- End .breadcrumb-nav -->

            <div class="page-content pt-7 pb-7">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="text-center mb-4">
                                <i class="icon-check-circle" style="font-size: 3rem; color: #6bc042;"></i>
                                <h3 class="mt-3">Thank you, your order has been placed!</h3>
                                <p>A confirmation has been recorded against your account. Order number <strong><?= htmlspecialchars($order['order_number']) ?></strong>.</p>
                            </div>

                            <table class="table table-summary mb-4">
                                <tbody>
                                    <tr>
                                        <td>Order Date:</td>
                                        <td><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Payment Method:</td>
                                        <td><?= htmlspecialchars($paymentMethodLabels[$order['payment_method']] ?? ucfirst($order['payment_method'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Payment Status:</td>
                                        <td><?= htmlspecialchars($paymentStatusLabels[$order['payment_status']] ?? ucfirst($order['payment_status'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Order Status:</td>
                                        <td><?= htmlspecialchars($orderStatusLabels[$order['order_status']] ?? ucfirst($order['order_status'])) ?></td>
                                    </tr>
                                    <?php if ($order['transaction_id']): ?>
                                        <tr>
                                            <td>Transaction ID:</td>
                                            <td><?= htmlspecialchars($order['transaction_id']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>Shipping To:</td>
                                        <td><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <table class="table table-summary">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><a href="product.php?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a></td>
                                            <td><?= (int) $item['quantity'] ?></td>
                                            <td>$<?= number_format($item['subtotal'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="summary-total">
                                        <td colspan="2">Total:</td>
                                        <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="text-center mt-4">
                                <a href="account.php" class="btn btn-outline-dark-2 btn-round"><span>View My Orders</span><i class="icon-long-arrow-right"></i></a>
                                <a href="category.php" class="btn btn-primary btn-round"><span>Continue Shopping</span><i class="icon-long-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
