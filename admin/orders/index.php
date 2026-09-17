<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
Auth::requireAdmin('../login.php');

$db = new Database();

$status = trim($_GET['status'] ?? '');
$where = '';
$params = [];
if (in_array($status, ['processing', 'shipped', 'delivered', 'cancelled'], true)) {
    $where = 'WHERE o.order_status = ?';
    $params[] = $status;
}

$orders = $db->select(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o JOIN users u ON u.id = o.user_id
     $where ORDER BY o.created_at DESC",
    $params
);

$statusBadge = [
    'processing' => 'bg-gradient-warning',
    'shipped'    => 'bg-gradient-info',
    'delivered'  => 'bg-gradient-success',
    'cancelled'  => 'bg-gradient-secondary',
];

$pageTitle = 'Orders';
$activeNav = 'orders';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="card">
    <div class="card-header pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">All Orders</h6>
        <div class="btn-group btn-group-sm" role="group">
            <a href="index.php" class="btn btn-outline-dark <?= $status === '' ? 'active' : '' ?>">All</a>
            <a href="index.php?status=processing" class="btn btn-outline-dark <?= $status === 'processing' ? 'active' : '' ?>">Processing</a>
            <a href="index.php?status=shipped" class="btn btn-outline-dark <?= $status === 'shipped' ? 'active' : '' ?>">Shipped</a>
            <a href="index.php?status=delivered" class="btn btn-outline-dark <?= $status === 'delivered' ? 'active' : '' ?>">Delivered</a>
            <a href="index.php?status=cancelled" class="btn btn-outline-dark <?= $status === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
        </div>
    </div>
    <div class="card-body px-0 pb-2">
        <div class="table-responsive">
            <table class="table align-items-center mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Order #</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Customer</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Total</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Payment</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Date</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="7" class="text-center text-sm text-secondary py-3">No orders in this view.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="text-sm font-weight-bold"><?= htmlspecialchars($o['order_number']) ?></td>
                            <td class="text-sm"><?= htmlspecialchars($o['customer_name']) ?><br><span class="text-xs text-secondary"><?= htmlspecialchars($o['customer_email']) ?></span></td>
                            <td class="text-sm text-center">$<?= number_format($o['total_amount'], 2) ?></td>
                            <td class="text-sm text-center text-uppercase">
                                <?= htmlspecialchars($o['payment_method']) ?><br>
                                <span class="badge <?= $o['payment_status'] === 'completed' ? 'bg-gradient-success' : ($o['payment_status'] === 'failed' ? 'bg-gradient-danger' : 'bg-gradient-warning') ?>"><?= ucfirst($o['payment_status']) ?></span>
                            </td>
                            <td class="text-center"><span class="badge <?= $statusBadge[$o['order_status']] ?? 'bg-gradient-secondary' ?>"><?= ucfirst($o['order_status']) ?></span></td>
                            <td class="text-sm text-center"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                            <td class="text-end pe-3">
                                <a href="view.php?id=<?= (int) $o['id'] ?>" class="text-primary font-weight-bold text-xs">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
