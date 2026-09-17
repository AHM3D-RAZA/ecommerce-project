<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
Auth::requireAdmin();

$db = new Database();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$base = '';
$pageScript = 'chart'; // tells admin-footer.php to load chartjs

$totalProducts   = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM products")['c'] ?? 0);
$totalCategories = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM categories")['c'] ?? 0);
$totalCustomers  = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")['c'] ?? 0);
$totalOrders     = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM orders")['c'] ?? 0);
$totalRevenue    = (float) ($db->selectOne("SELECT COALESCE(SUM(total_amount), 0) AS s FROM orders WHERE payment_status = 'completed' OR payment_method = 'cod'")['s'] ?? 0);

$recentOrders = $db->select(
    "SELECT o.id, o.order_number, o.total_amount, o.payment_method, o.payment_status, o.order_status, o.created_at, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 8"
);

$lowStock = $db->select("SELECT id, name, stock FROM products WHERE stock <= 5 ORDER BY stock ASC LIMIT 5");

// Last 6 months of sales, for the chart. Months with no orders still show up as 0.
$monthlySales = $db->select(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, MONTHNAME(created_at) AS month_name, SUM(total_amount) AS sales
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym, month_name
     ORDER BY ym ASC"
);
$chartLabels = array_map(fn($r) => $r['month_name'], $monthlySales);
$chartValues = array_map(fn($r) => round((float) $r['sales'], 2), $monthlySales);

$statusBadge = [
    'processing' => 'bg-gradient-warning',
    'shipped'    => 'bg-gradient-info',
    'delivered'  => 'bg-gradient-success',
    'cancelled'  => 'bg-gradient-secondary',
];

require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row">
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
            <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-sm mb-0 text-capitalize">Products</p>
                        <h4 class="mb-0"><?= $totalProducts ?></h4>
                    </div>
                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                        <i class="ni ni-box-2 text-white opacity-10"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
            <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-sm mb-0 text-capitalize">Categories</p>
                        <h4 class="mb-0"><?= $totalCategories ?></h4>
                    </div>
                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                        <i class="ni ni-collection text-white opacity-10"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
            <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-sm mb-0 text-capitalize">Customers</p>
                        <h4 class="mb-0"><?= $totalCustomers ?></h4>
                    </div>
                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                        <i class="ni ni-single-02 text-white opacity-10"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card">
            <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-sm mb-0 text-capitalize">Orders / Revenue</p>
                        <h4 class="mb-0"><?= $totalOrders ?> / $<?= number_format($totalRevenue, 2) ?></h4>
                    </div>
                    <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                        <i class="ni ni-money-coins text-white opacity-10"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-0">Sales (last 6 months)</h6>
                <p class="text-sm">Total order value, month by month</p>
                <div class="chart">
                    <canvas id="salesChart" height="230"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h6>Low Stock</h6>
                <p class="text-sm mb-0">5 units or fewer left</p>
            </div>
            <div class="card-body pt-2">
                <?php if (empty($lowStock)): ?>
                    <p class="text-sm text-secondary mb-0">Nothing is running low right now.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($lowStock as $p): ?>
                            <li class="list-group-item border-0 d-flex justify-content-between ps-0">
                                <span class="text-sm"><?= htmlspecialchars($p['name']) ?></span>
                                <span class="badge bg-gradient-danger"><?= (int) $p['stock'] ?> left</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Orders</h6>
                <a href="orders/index.php" class="text-sm text-primary font-weight-bold">View all</a>
            </div>
            <div class="card-body px-0 pb-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Order #</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Customer</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Total</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Payment</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Status</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="6" class="text-center text-sm text-secondary py-3">No orders yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><a href="orders/view.php?id=<?= (int) $o['id'] ?>" class="text-sm font-weight-bold"><?= htmlspecialchars($o['order_number']) ?></a></td>
                                    <td class="text-sm"><?= htmlspecialchars($o['customer_name']) ?></td>
                                    <td class="text-sm">$<?= number_format($o['total_amount'], 2) ?></td>
                                    <td class="text-sm text-uppercase"><?= htmlspecialchars($o['payment_method']) ?></td>
                                    <td><span class="badge <?= $statusBadge[$o['order_status']] ?? 'bg-gradient-secondary' ?>"><?= ucfirst($o['order_status']) ?></span></td>
                                    <td class="text-sm"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('salesChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Sales ($)',
            data: <?= json_encode($chartValues) ?>,
            borderColor: '#344767',
            backgroundColor: 'rgba(52, 71, 103, 0.1)',
            tension: 0.3,
            fill: true,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
