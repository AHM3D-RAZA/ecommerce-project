<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
Auth::requireAdmin('../login.php');

$db = new Database();
$currentUserId = (int) Session::get('user_id');

if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];

    if ($id === $currentUserId) {
        Session::flash('error', "You can't deactivate your own account.");
    } else {
        $db->run("UPDATE users SET is_active = 1 - is_active WHERE id = ?", [$id]);
        Session::flash('success', 'User status updated.');
    }
    header('Location: index.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE name LIKE ? OR email LIKE ?';
    $params = ['%' . $search . '%', '%' . $search . '%'];
}

$users = $db->select(
    "SELECT id, name, email, role, is_active, created_at,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = users.id) AS order_count
     FROM users $where ORDER BY id DESC",
    $params
);

$pageTitle = 'Users';
$activeNav = 'users';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="card">
    <div class="card-header pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">Registered Users</h6>
        <form method="get" class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width: 280px; min-width: 180px;" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-sm btn-outline-dark text-nowrap px-3">Search</button>
        </form>
    </div>
    <div class="card-body px-0 pb-2">
        <div class="table-responsive">
            <table class="table align-items-center mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Name</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Email</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Role</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Orders</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Joined</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center text-sm text-secondary py-3">No users found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-sm font-weight-bold"><?= htmlspecialchars($u['name']) ?></td>
                            <td class="text-sm"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $u['role'] === 'admin' ? 'bg-gradient-dark' : 'bg-gradient-info' ?>"><?= ucfirst($u['role']) ?></span>
                            </td>
                            <td class="text-sm text-center"><?= (int) $u['order_count'] ?></td>
                            <td class="text-sm text-center"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td class="text-center">
                                <?php if ((int) $u['id'] === $currentUserId): ?>
                                    <span class="badge bg-gradient-success">Active (you)</span>
                                <?php else: ?>
                                    <a href="index.php?toggle=<?= (int) $u['id'] ?>" class="badge <?= $u['is_active'] ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
