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
$product = $db->selectOne("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    header('Location: index.php');
    exit;
}

$categories = $db->select("SELECT id, name FROM categories ORDER BY name ASC");

$errors = [];
$form = [
    'name'        => $product['name'],
    'category_id' => $product['category_id'],
    'price'       => $product['price'],
    'stock'       => $product['stock'],
    'description' => $product['description'],
    'status'      => (int) $product['status'] === 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim($_POST['name'] ?? '');
    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $form['price'] = trim($_POST['price'] ?? '');
    $form['stock'] = trim($_POST['stock'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['status'] = isset($_POST['status']);

    $v = new Validator();
    $v->required($form['name'], 'name')
      ->numeric($form['price'], 'price')
      ->required($form['price'], 'price')
      ->numeric($form['stock'], 'stock');

    if ($form['category_id'] <= 0) {
        $errors['category_id'] = 'Please choose a category.';
    }

    if ($v->fails()) {
        $errors = array_merge($errors, $v->errors());
    }

    if (empty($errors)) {
        $imagePath = $product['image'];
        $upload = Uploader::save($_FILES['image'] ?? [], 'products');

        if (!$upload['success'] && $upload['message']) {
            $errors['image'] = $upload['message'];
        } else {
            if ($upload['success']) {
                Uploader::delete($product['image']);
                $imagePath = $upload['path'];
            }

            $db->run(
                "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ?, status = ? WHERE id = ?",
                [
                    $form['category_id'],
                    $form['name'],
                    $form['description'],
                    (float) $form['price'],
                    (int) $form['stock'],
                    $imagePath,
                    $form['status'] ? 1 : 0,
                    $id,
                ]
            );

            Session::flash('success', 'Product updated.');
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Edit Product';
$activeNav = 'products';
$base = '../';
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-7 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Edit Product</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <img src="<?= htmlspecialchars('../../public/' . shop_image($product['image'])) ?>" width="90" height="90" style="object-fit: cover; border-radius: 8px;" alt="">
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="input-group input-group-outline mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($form['name']) ?>" required>
                    </div>
                    <?php if (isset($errors['name'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['name']) ?></p><?php endif; ?>

                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control mb-3">
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

                    <label class="form-label">Replace Image</label>
                    <input type="file" name="image" class="form-control mb-1" accept="image/*">
                    <p class="text-xs text-secondary">Leave empty to keep the current image.</p>
                    <?php if (isset($errors['image'])): ?><p class="text-danger text-xs"><?= htmlspecialchars($errors['image']) ?></p><?php endif; ?>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?= $form['status'] ? 'checked' : '' ?>>
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
