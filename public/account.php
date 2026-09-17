<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Validator.php';
Session::start();
Auth::requireLogin('login.php?redirect=account.php');

$db = new Database();
$userId = Session::get('user_id');

// --- Update account details (name / email / optional password change) ---
$accountError = null;
$accountSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

    $v = new Validator();
    $v->required($name, 'name')->required($email, 'email')->email($email);

    if ($v->fails()) {
        $accountError = $v->first();
    } elseif ($db->selectOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId])) {
        $accountError = 'That email address is already in use by another account.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
        $accountError = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== '' && $newPassword !== $newPasswordConfirm) {
        $accountError = 'New passwords do not match.';
    } else {
        $currentUser = $db->selectOne("SELECT password FROM users WHERE id = ?", [$userId]);

        if ($newPassword !== '' && !password_verify($currentPassword, $currentUser['password'])) {
            $accountError = 'Current password is incorrect.';
        } else {
            if ($newPassword !== '') {
                $db->run(
                    "UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?",
                    [$name, $email, password_hash($newPassword, PASSWORD_BCRYPT), $userId]
                );
            } else {
                $db->run("UPDATE users SET name = ?, email = ? WHERE id = ?", [$name, $email, $userId]);
            }

            Session::set('user_name', $name);
            $accountSuccess = 'Your account details have been updated.';
        }
    }
}

$user = $db->selectOne("SELECT name, email FROM users WHERE id = ?", [$userId]);
$orders = $db->select("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC", [$userId]);

$orderStatusLabels = [
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];

$pageTitle = 'My Account';
require_once __DIR__ . '/../includes/header.php';
?>
            <div class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')">
                <div class="container">
                    <h1 class="page-title">My Account<span>Shop</span></h1>
                </div><!-- End .container -->
            </div><!-- End .page-header -->

            <nav aria-label="breadcrumb" class="breadcrumb-nav mb-3">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">My Account</li>
                    </ol>
                </div><!-- End .container -->
            </nav><!-- End .breadcrumb-nav -->

            <div class="page-content">
                <div class="dashboard">
                    <div class="container">
                        <div class="row">
                            <aside class="col-md-4 col-lg-3">
                                <ul class="nav nav-dashboard flex-column mb-3 mb-md-0" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="tab-dashboard-link" data-toggle="tab" href="#tab-dashboard" role="tab" aria-controls="tab-dashboard" aria-selected="true">Dashboard</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tab-orders-link" data-toggle="tab" href="#tab-orders" role="tab" aria-controls="tab-orders" aria-selected="false">Orders</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tab-account-link" data-toggle="tab" href="#tab-account" role="tab" aria-controls="tab-account" aria-selected="false">Account Details</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="logout.php">Sign Out</a>
                                    </li>
                                </ul>
                            </aside><!-- End .col-lg-3 -->

                            <div class="col-md-8 col-lg-9">
                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="tab-dashboard" role="tabpanel" aria-labelledby="tab-dashboard-link">
                                        <p>Hello <span class="font-weight-normal text-dark"><?= htmlspecialchars($user['name']) ?></span> (not <span class="font-weight-normal text-dark"><?= htmlspecialchars($user['name']) ?></span>? <a href="logout.php">Log out</a>)
                                        <br>
                                        From your account dashboard you can view your <a href="#tab-orders" class="tab-trigger-link link-underline">recent orders</a> and <a href="#tab-account" class="tab-trigger-link">edit your password and account details</a>.</p>
                                    </div><!-- .End .tab-pane -->

                                    <div class="tab-pane fade" id="tab-orders" role="tabpanel" aria-labelledby="tab-orders-link">
                                        <?php if (empty($orders)): ?>
                                            <p>No order has been made yet.</p>
                                            <a href="category.php" class="btn btn-outline-primary-2"><span>GO SHOP</span><i class="icon-long-arrow-right"></i></a>
                                        <?php else: ?>
                                            <table class="table table-order">
                                                <thead>
                                                    <tr>
                                                        <th>Order</th>
                                                        <th>Date</th>
                                                        <th>Status</th>
                                                        <th>Total</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($orders as $order): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($order['order_number']) ?></td>
                                                            <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                                            <td><?= htmlspecialchars($orderStatusLabels[$order['order_status']] ?? ucfirst($order['order_status'])) ?></td>
                                                            <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                                            <td><a href="order-confirmation.php?order=<?= urlencode($order['order_number']) ?>">View</a></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div><!-- .End .tab-pane -->

                                    <div class="tab-pane fade" id="tab-account" role="tabpanel" aria-labelledby="tab-account-link">
                                        <?php if ($accountError): ?>
                                            <div class="alert alert-danger"><?= htmlspecialchars($accountError) ?></div>
                                        <?php endif; ?>
                                        <?php if ($accountSuccess): ?>
                                            <div class="alert alert-primary"><?= htmlspecialchars($accountSuccess) ?></div>
                                        <?php endif; ?>
                                        <form action="account.php#tab-account" method="post">
                                            <label>Full name *</label>
                                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>

                                            <label>Email address *</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

                                            <label>Current password (leave blank to leave unchanged)</label>
                                            <input type="password" class="form-control" name="current_password">

                                            <label>New password (leave blank to leave unchanged)</label>
                                            <input type="password" class="form-control" name="new_password">

                                            <label>Confirm new password</label>
                                            <input type="password" class="form-control mb-2" name="new_password_confirm">

                                            <button type="submit" name="update_account" value="1" class="btn btn-outline-primary-2">
                                                <span>SAVE CHANGES</span>
                                                <i class="icon-long-arrow-right"></i>
                                            </button>
                                        </form>
                                    </div><!-- .End .tab-pane -->
                                </div>
                            </div><!-- End .col-lg-9 -->
                        </div><!-- End .row -->
                    </div><!-- End .container -->
                </div><!-- End .dashboard -->
            </div><!-- End .page-content -->
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
