<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/Session.php';
Auth::requireAdmin('../login.php');

$db = new Database();

if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $db->run("UPDATE products SET status = 1 - status WHERE id = ?", [$id]);
    header('Location: index.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $ordered = $db->selectOne("SELECT COUNT(*) AS c FROM order_items WHERE product_id = ?", [$id]);

    if ((int) $ordered['c'] > 0) {
        Session::flash('error', 'This product appears in existing orders and cannot be deleted. Hide it instead.');
    } else {
        $product = $db->selectOne("SELECT image FROM products WHERE id = ?", [$id]);
        $db->run("DELETE FROM products WHERE id = ?", [$id]);
        if ($product) {
            Uploader::delete($product['image']);
        }
        Session::flash('success', 'Product deleted.');
    }
    header('Location: index.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);

$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'p.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$products = $db->select(
    "SELECT p.*, c.name AS category_name FROM products p
     JOIN categories c ON c.id = p.category_id
     $whereSql ORDER BY p.id DESC",
    $params
);

$categories = $db->select("SELECT id, name FROM categories ORDER BY name ASC");

$pageTitle = 'Products';
$activeNav = 'products';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="card">
    <div class="card-header pb-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0">All Products</h6>
            <a href="create.php" class="btn btn-sm bg-gradient-dark mb-0">+ Add Product</a>
        </div>
        <form method="get" class="row g-2 mt-3">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by name..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select form-select-sm">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-dark w-100">Filter</button>
            </div>
        </form>
    </div>
    <div class="card-body px-0 pb-2">
        <div class="table-responsive">
            <table class="table align-items-center mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Product</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Category</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Price</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Stock</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="text-center text-sm text-secondary py-3">No products found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="d-flex px-2 py-1 align-items-center">
                                    <img src="<?= htmlspecialchars('../../public/' . shop_image($p['image'])) ?>" width="40" height="40" style="object-fit: cover; border-radius: 6px;" class="me-2" alt="">
                                    <span class="text-sm font-weight-bold"><?= htmlspecialchars($p['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-sm"><?= htmlspecialchars($p['category_name']) ?></td>
                            <td class="text-sm text-center">$<?= number_format($p['price'], 2) ?></td>
                            <td class="text-sm text-center"><?= (int) $p['stock'] ?></td>
                            <td class="text-center">
                                <a href="index.php?toggle=<?= (int) $p['id'] ?><?= $search ? '&q=' . urlencode($search) : '' ?>" class="badge <?= $p['status'] ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                    <?= $p['status'] ? 'Active' : 'Hidden' ?>
                                </a>
                            </td>
                            <td class="text-end pe-3">
                                <a href="edit.php?id=<?= (int) $p['id'] ?>" class="text-secondary font-weight-bold text-xs">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="index.php?delete=<?= (int) $p['id'] ?>" class="text-danger font-weight-bold text-xs" onclick="return confirm('Delete this product?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
