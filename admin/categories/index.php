<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/Session.php';
Auth::requireAdmin('../login.php');

$db = new Database();

// Toggle a category active/inactive right from the list.
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $db->run("UPDATE categories SET status = 1 - status WHERE id = ?", [$id]);
    header('Location: index.php');
    exit;
}

// Delete - but only if nothing is selling under this category.
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $inUse = $db->selectOne("SELECT COUNT(*) AS c FROM products WHERE category_id = ?", [$id]);

    if ((int) $inUse['c'] > 0) {
        Session::flash('error', 'This category still has products in it. Move or delete those products first.');
    } else {
        $cat = $db->selectOne("SELECT image FROM categories WHERE id = ?", [$id]);
        $db->run("DELETE FROM categories WHERE id = ?", [$id]);
        if ($cat) {
            Uploader::delete($cat['image']);
        }
        Session::flash('success', 'Category deleted.');
    }
    header('Location: index.php');
    exit;
}

$categories = $db->select(
    "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.id DESC"
);

$pageTitle = 'Categories';
$activeNav = 'categories';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="card">
    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0">All Categories</h6>
        <a href="create.php" class="btn btn-sm bg-gradient-dark mb-0">+ Add Category</a>
    </div>
    <div class="card-body px-0 pb-2">
        <div class="table-responsive">
            <table class="table align-items-center mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Image</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Name</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Slug</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Products</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                        <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="6" class="text-center text-sm text-secondary py-3">No categories yet. Add the first one.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars('../../public/' . shop_image($c['image'])) ?>" width="45" height="45" style="object-fit: cover; border-radius: 8px;" alt=""></td>
                            <td class="text-sm font-weight-bold"><?= htmlspecialchars($c['name']) ?></td>
                            <td class="text-sm text-secondary"><?= htmlspecialchars($c['slug']) ?></td>
                            <td class="text-sm text-center"><?= (int) $c['product_count'] ?></td>
                            <td class="text-center">
                                <a href="index.php?toggle=<?= (int) $c['id'] ?>" class="badge <?= $c['status'] ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                    <?= $c['status'] ? 'Active' : 'Hidden' ?>
                                </a>
                            </td>
                            <td class="text-end pe-3">
                                <a href="edit.php?id=<?= (int) $c['id'] ?>" class="text-secondary font-weight-bold text-xs">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="index.php?delete=<?= (int) $c['id'] ?>" class="text-danger font-weight-bold text-xs" onclick="return confirm('Delete this category?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
