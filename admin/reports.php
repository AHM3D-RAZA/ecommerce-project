<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
Auth::requireAdmin();

$db = new Database();

// Weekly: each of the last 7 days.
$weekly = $db->select(
    "SELECT DATE(created_at) AS day, SUM(total_amount) AS sales, COUNT(*) AS orders
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);

// Monthly: this calendar year, by month.
$monthly = $db->select(
    "SELECT MONTH(created_at) AS m, MONTHNAME(created_at) AS month_name, SUM(total_amount) AS sales, COUNT(*) AS orders
     FROM orders
     WHERE YEAR(created_at) = YEAR(CURDATE())
     GROUP BY MONTH(created_at), MONTHNAME(created_at)
     ORDER BY m ASC"
);

// Yearly: every year that has orders.
$yearly = $db->select(
    "SELECT YEAR(created_at) AS year, SUM(total_amount) AS sales, COUNT(*) AS orders
     FROM orders
     GROUP BY YEAR(created_at)
     ORDER BY year ASC"
);

// Fill in the missing days of the week with zero so the chart doesn't look broken.
$weeklyByDay = [];
foreach ($weekly as $row) {
    $weeklyByDay[$row['day']] = $row;
}
$weekLabels = [];
$weekValues = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $weekLabels[] = date('D j M', strtotime($day));
    $weekValues[] = isset($weeklyByDay[$day]) ? round((float) $weeklyByDay[$day]['sales'], 2) : 0;
}

$monthLabels = array_map(fn($r) => $r['month_name'], $monthly);
$monthValues = array_map(fn($r) => round((float) $r['sales'], 2), $monthly);

$yearLabels = array_map(fn($r) => (string) $r['year'], $yearly);
$yearValues = array_map(fn($r) => round((float) $r['sales'], 2), $yearly);

$pageTitle = 'Sales Reports';
$activeNav = 'reports';
$base = '';
$pageScript = 'chart';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Weekly Sales</h6>
                <p class="text-sm mb-0">Last 7 days</p>
            </div>
            <div class="card-body">
                <canvas id="weeklyChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Monthly Sales</h6>
                <p class="text-sm mb-0"><?= date('Y') ?>, by month</p>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Yearly Sales</h6>
                <p class="text-sm mb-0">All years with orders</p>
            </div>
            <div class="card-body">
                <canvas id="yearlyChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header pb-0"><h6>Raw Numbers</h6></div>
            <div class="card-body">
                <p class="text-sm font-weight-bold">This week</p>
                <table class="table table-sm">
                    <?php foreach ($weekLabels as $i => $label): ?>
                        <tr><td class="text-sm"><?= $label ?></td><td class="text-sm text-end">$<?= number_format($weekValues[$i], 2) ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <p class="text-sm font-weight-bold mt-3">Yearly totals</p>
                <?php if (empty($yearly)): ?>
                    <p class="text-sm text-secondary">No orders recorded yet.</p>
                <?php endif; ?>
                <table class="table table-sm">
                    <?php foreach ($yearly as $row): ?>
                        <tr><td class="text-sm"><?= (int) $row['year'] ?></td><td class="text-sm">$<?= number_format($row['sales'], 2) ?> (<?= (int) $row['orders'] ?> orders)</td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function drawBarChart(canvasId, labels, values, color) {
    new Chart(document.getElementById(canvasId).getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sales ($)',
                data: values,
                backgroundColor: color,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

drawBarChart('weeklyChart', <?= json_encode($weekLabels) ?>, <?= json_encode($weekValues) ?>, '#344767');
drawBarChart('monthlyChart', <?= json_encode($monthLabels) ?>, <?= json_encode($monthValues) ?>, '#2dce89');
drawBarChart('yearlyChart', <?= json_encode($yearLabels) ?>, <?= json_encode($yearValues) ?>, '#5e72e4');
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
