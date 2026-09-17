<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validator.php';
Auth::requireAdmin('../login.php');

$db = new Database();
$categories = $db->select("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");

$errors = [];
$form = ['name' => '', 'category_id' => '', 'price' => '', 'stock' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim($_POST['name'] ?? '');
    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $form['price'] = trim($_POST['price'] ?? '');
    $form['stock'] = trim($_POST['stock'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');

    $v = new Validator();
    $v->required($form['name'], 'name')
      ->numeric($form['price'], 'price')
      ->required($form['price'], 'price')
      ->numeric($form['stock'], 'stock');

    if ($form['category_id'] <= 0) {
        $errors['category_id'] = 'Please choose a category.';
    }

    $upload = Uploader::save($_FILES['image'] ?? [], 'products');
    if (!$upload['success'] && $upload['message']) {
        $errors['image'] = $upload['message'];
    } elseif (!$upload['success']) {
        $errors['image'] = 'A product image is required.';
    }

    if ($v->fails()) {
        $errors = array_merge($errors, $v->errors());
    }

    if (empty($errors)) {
        $slug = make_slug($form['name']);
        $existing = $db->selectOne("SELECT id FROM products WHERE slug = ?", [$slug]);
        if ($existing) {
            $slug .= '-' . time();
        }

        $db->insert(
            "INSERT INTO products (category_id, name, slug, description, price, stock, image, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $form['category_id'],
                $form['name'],
                $slug,
                $form['description'],
                (float) $form['price'],
                (int) ($form['stock'] !== '' ? $form['stock'] : 0),
                $upload['path'],
            ]
        );

        Session::flash('success', 'Product added.');
        header('Location: index.php');
        exit;
    }
}

$pageTitle = 'Add Product';
$activeNav = 'products';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-7 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Add Product</h6>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="input-group input-group-outline mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($form['name']) ?>" required>
                    </div>
                    <?php if (isset($errors['name'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['name']) ?></p><?php endif; ?>

                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control mb-3">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $form['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['category_id']) ?></p><?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group input-group-outline mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="text" name="price" class="form-control" value="<?= htmlspecialchars($form['price']) ?>" required>
                            </div>
                            <?php if (isset($errors['price'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['price']) ?></p><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-outline mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="text" name="stock" class="form-control" value="<?= htmlspecialchars($form['stock']) ?>" required>
                            </div>
                            <?php if (isset($errors['stock'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['stock']) ?></p><?php endif; ?>
                        </div>
                    </div>

                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control mb-3" rows="4"><?= htmlspecialchars($form['description']) ?></textarea>

                    <label class="form-label">Product Image</label>
                    <input type="file" name="image" class="form-control mb-1" accept="image/*">
                    <p class="text-xs text-secondary">JPG, PNG, WEBP or GIF. Max 2MB.</p>
                    <?php if (isset($errors['image'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['image']) ?></p><?php endif; ?>

                    <div class="mt-4">
                        <button type="submit" class="btn bg-gradient-dark">Save Product</button>
                        <a href="index.php" class="btn btn-outline-dark">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
