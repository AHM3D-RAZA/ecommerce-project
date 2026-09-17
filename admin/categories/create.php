<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validator.php';
Auth::requireAdmin('../login.php');

$db = new Database();
$errors = [];
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    $v = new Validator();
    $v->required($name, 'name');

    if ($v->fails()) {
        $errors = $v->errors();
    } else {
        $slug = make_slug($name);
        $existing = $db->selectOne("SELECT id FROM categories WHERE slug = ?", [$slug]);
        if ($existing) {
            $slug .= '-' . time();
        }

        $upload = Uploader::save($_FILES['image'] ?? [], 'categories');
        if (!$upload['success'] && $upload['message']) {
            $errors['image'] = $upload['message'];
        } else {
            $imagePath = $upload['success'] ? $upload['path'] : null;

            $db->insert(
                "INSERT INTO categories (name, slug, image, status) VALUES (?, ?, ?, 1)",
                [$name, $slug, $imagePath]
            );

            Session::flash('success', 'Category added.');
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Add Category';
$activeNav = 'categories';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Add Category</h6>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="input-group input-group-outline mb-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
                    </div>
                    <?php if (isset($errors['name'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['name']) ?></p><?php endif; ?>

                    <label class="form-label mt-2">Category Image</label>
                    <input type="file" name="image" class="form-control mb-1" accept="image/*">
                    <p class="text-xs text-secondary">JPG, PNG, WEBP or GIF. Max 2MB. Optional.</p>
                    <?php if (isset($errors['image'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['image']) ?></p><?php endif; ?>

                    <div class="mt-4">
                        <button type="submit" class="btn bg-gradient-dark">Save Category</button>
                        <a href="index.php" class="btn btn-outline-dark">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
