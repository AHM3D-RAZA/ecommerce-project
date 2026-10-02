<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/ProductImages.php';
require_once __DIR__ . '/../../core/Errors.php';
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
      ->numeric($form['stock'], 'stock')
      ->required($form['stock'], 'stock');

    if ($form['category_id'] <= 0) {
        $errors['category_id'] = 'Please choose a category.';
    }

    // Stage the multi-file upload first: original input order is preserved,
    // empty slots are skipped and rejected files are reported (not dropped).
    $stage = ProductImages::stageUploads($_FILES['images'] ?? [], ProductImages::MAX_IMAGES);
    $imageMessages = $stage['errors'];

    if ($stage['accepted'] === 0) {
        $imageMessages[] = 'Please add at least one product image (JPG, PNG, WEBP or GIF, max 2MB each).';
    }

    if ($v->fails()) {
        $errors = array_merge($errors, $v->errors());
    }

    // A rejected file must not block the save as long as at least one image was
    // accepted - the rejects are reported on the success message instead.
    if (!empty($errors) || $stage['accepted'] === 0) {
        // Nothing saved - throw away anything we staged.
        ProductImages::discardStaged($stage);
        $errors['image'] = $imageMessages;
    } else {
        $slug = make_slug($form['name']);
        if ($db->selectOne("SELECT id FROM products WHERE slug = ?", [$slug])) {
            $slug .= '-' . time();
        }

        // Create the row, then move the staged files into uploads/products/{id}/.
        // All of it runs in one transaction so a failure leaves no half-saved
        // product and no orphaned files behind.
        $conn = $db->getConnection();
        $conn->begin_transaction();
        $productId = null;

        try {
            $productId = $db->insert(
                "INSERT INTO products (category_id, name, slug, description, price, stock, image, status)
                 VALUES (?, ?, ?, ?, ?, ?, '[]', 1)",
                [
                    $form['category_id'],
                    $form['name'],
                    $slug,
                    $form['description'],
                    (float) $form['price'],
                    (int) ($form['stock'] !== '' ? $form['stock'] : 0),
                ]
            );

            $stored = [];
            foreach ($stage['staged'] as $index => $stagedName) {
                $final = ProductImages::moveStagedIntoProduct($stagedName, $productId, $stage['originals'][$index] ?? null);
                if ($final === null) {
                    throw new RuntimeException('Could not move an uploaded image into place.');
                }
                $stored[] = $final;
            }

            if (empty($stored)) {
                throw new RuntimeException('No images were stored.');
            }

            $db->run("UPDATE products SET image = ? WHERE id = ?", [ProductImages::encode($stored), $productId]);

            $conn->commit();

            $message = 'Product added.';
            if ($stage['skipped'] > 0) {
                $message .= ' ' . $stage['skipped'] . ' file(s) skipped: ' . implode(' ', $imageMessages);
            }
            Session::flash('success', $message);
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            ProductImages::discardStaged($stage);
            if ($productId !== null) {
                ProductImages::deleteProductFolder($productId);
            }
            $errors['image'] = ['Could not save the product images. Please try again.'];
        }
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
                <?php render_error_summary($errors); ?>

                <form method="post" enctype="multipart/form-data" novalidate>
                    <div class="input-group input-group-outline mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($form['name']) ?>">
                    </div>

                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control mb-3">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $form['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group input-group-outline mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="text" name="price" class="form-control" value="<?= htmlspecialchars($form['price']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-outline mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="text" name="stock" class="form-control" value="<?= htmlspecialchars($form['stock']) ?>">
                            </div>
                        </div>
                    </div>

                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control mb-3" rows="4"><?= htmlspecialchars($form['description']) ?></textarea>

                    <label class="form-label">Product Images (up to <?= ProductImages::MAX_IMAGES ?>)</label>
                    <input type="file" name="images[]" class="form-control mb-1" accept="image/*" multiple>
                    <p class="text-xs text-secondary">JPG, PNG, WEBP or GIF. Max 2MB each. The first image is the thumbnail.</p>

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
