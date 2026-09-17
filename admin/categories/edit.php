<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validator.php';
Auth::requireAdmin('../login.php');

$db = new Database();
$id = (int) ($_GET['id'] ?? 0);
$category = $db->selectOne("SELECT * FROM categories WHERE id = ?", [$id]);

if (!$category) {
    header('Location: index.php');
    exit;
}

$errors = [];
$name = $category['name'];
$statusChecked = (int) $category['status'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $statusChecked = isset($_POST['status']);

    $v = new Validator();
    $v->required($name, 'name');

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $imagePath = $category['image'];

        $upload = Uploader::save($_FILES['image'] ?? [], 'categories');
        if (!$upload['success'] && $upload['message']) {
            $errors['image'] = $upload['message'];
        } else {
            if ($upload['success']) {
                Uploader::delete($category['image']);
                $imagePath = $upload['path'];
            }

            $db->run(
                "UPDATE categories SET name = ?, image = ?, status = ? WHERE id = ?",
                [$name, $imagePath, $statusChecked ? 1 : 0, $id]
            );

            Session::flash('success', 'Category updated.');
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Edit Category';
$activeNav = 'categories';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Edit Category</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <img src="<?= htmlspecialchars('../../public/' . shop_image($category['image'])) ?>" width="80" height="80" style="object-fit: cover; border-radius: 8px;" alt="">
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="input-group input-group-outline mb-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
                    </div>
                    <?php if (isset($errors['name'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['name']) ?></p><?php endif; ?>

                    <label class="form-label mt-2">Replace Image</label>
                    <input type="file" name="image" class="form-control mb-1" accept="image/*">
                    <p class="text-xs text-secondary">Leave empty to keep the current image.</p>
                    <?php if (isset($errors['image'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['image']) ?></p><?php endif; ?>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?= $statusChecked ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status">Active (visible in the storefront)</label>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn bg-gradient-dark">Save Changes</button>
                        <a href="index.php" class="btn btn-outline-dark">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
