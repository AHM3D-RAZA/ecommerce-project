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
$id = (int) ($_GET['id'] ?? 0);
$product = $db->selectOne("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    header('Location: index.php');
    exit;
}

$categories = $db->select("SELECT id, name FROM categories ORDER BY name ASC");

$errors = [];
$uploadErrors = [];
$form = [
    'name'        => $product['name'],
    'category_id' => $product['category_id'],
    'price'       => $product['price'],
    'stock'       => $product['stock'],
    'description' => $product['description'],
    'status'      => (int) $product['status'] === 1,
];

// Images already stored for this product, in gallery order.
$existingTokens = ProductImages::decode($product['image']);
$remaining = ProductImages::availableSlots($product['image']);

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
      ->numeric($form['stock'], 'stock')
      ->required($form['stock'], 'stock');

    if ($form['category_id'] <= 0) {
        $errors['category_id'] = 'Please choose a category.';
    }
    if ($v->fails()) {
        $errors = array_merge($errors, $v->errors());
    }

    // The gallery order arrives as tokens: existing:<file> / new:<inputIndex>.
    $orderTokens = array_map('strval', (array) ($_POST['image_order'] ?? []));

    // Pass 1: decide which existing images survive - this drives how many new
    // files we may accept (removing one frees a slot).
    $keptExisting = [];
    foreach ($orderTokens as $token) {
        if (strpos($token, 'existing:') === 0) {
            $existing = substr($token, 9);
            if (in_array($existing, $existingTokens, true) && !in_array($existing, $keptExisting, true)) {
                $keptExisting[] = $existing;
            }
        }
    }

    // Cap new uploads at the room left, so the message can say
    // "You can add 2 more (limit 5)" instead of a blanket "max 5".
    $maxNew = max(0, ProductImages::MAX_IMAGES - count($keptExisting));
    $stage = ProductImages::stageUploads($_FILES['images'] ?? [], $maxNew);
    $uploadErrors = $stage['errors'];

    // Pass 2: build the final gallery order, keeping existing and new interleaved.
    $usedExisting = [];
    $usedNew = [];
    $orderedSpecs = [];

    foreach ($orderTokens as $token) {
        if (strpos($token, 'existing:') === 0) {
            $existing = substr($token, 9);
            if (in_array($existing, $keptExisting, true) && !in_array($existing, $usedExisting, true)) {
                $usedExisting[] = $existing;
                $orderedSpecs[] = ['type' => 'existing', 'token' => $existing];
            }
        } elseif (strpos($token, 'new:') === 0) {
            $index = (int) substr($token, 4);
            if (isset($stage['staged'][$index]) && !in_array($index, $usedNew, true)) {
                $usedNew[] = $index;
                $orderedSpecs[] = ['type' => 'new', 'index' => $index];
            }
        }
    }

    // Anything uploaded but not referenced (e.g. with JS disabled) is kept too.
    foreach ($stage['staged'] as $index => $stagedName) {
        if (!in_array($index, $usedNew, true)) {
            $usedNew[] = $index;
            $orderedSpecs[] = ['type' => 'new', 'index' => $index];
        }
    }

    // A rejected file must not block the save as long as an image survives.
    if (!empty($errors) || (count($keptExisting) + count($usedNew)) === 0) {
        // Nothing saved - throw away anything we staged.
        ProductImages::discardStaged($stage);
        $messages = $uploadErrors;
        if (count($keptExisting) + count($usedNew) === 0) {
            $messages[] = 'A product needs at least one image.';
        }
        $errors['image'] = $messages;
    } else {
        $conn = $db->getConnection();
        $conn->begin_transaction();

        $moved = [];

        try {
            // Move the new files into the product folder, building the final
            // gallery order (existing and new tokens) as we go.
            $finalTokens = [];
            foreach ($orderedSpecs as $spec) {
                if ($spec['type'] === 'existing') {
                    $finalTokens[] = $spec['token'];
                    continue;
                }

                $index = $spec['index'];
                $final = ProductImages::moveStagedIntoProduct($stage['staged'][$index], $id, $stage['originals'][$index] ?? null);
                if ($final === null) {
                    throw new RuntimeException('Could not move an uploaded image into place.');
                }
                $moved[] = $final;
                $finalTokens[] = $final;
            }

            $db->run(
                "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ?, status = ? WHERE id = ?",
                [
                    $form['category_id'],
                    $form['name'],
                    $form['description'],
                    (float) $form['price'],
                    (int) $form['stock'],
                    ProductImages::encode($finalTokens),
                    $form['status'] ? 1 : 0,
                    $id,
                ]
            );

            $conn->commit();

            // The row is saved, so it is safe to delete files the admin dropped.
            foreach ($existingTokens as $token) {
                if (!in_array($token, $keptExisting, true)) {
                    ProductImages::deleteTokenFile($id, $token);
                }
            }

            $message = 'Product updated.';
            if ($stage['skipped'] > 0) {
                $message .= ' ' . $stage['skipped'] . ' file(s) skipped: ' . implode(' ', $uploadErrors);
            }
            Session::flash('success', $message);
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            ProductImages::discardStaged($stage);
            foreach ($moved as $filename) {
                ProductImages::deleteTokenFile($id, $filename);
            }
            $errors['image'] = ['Could not save the product images. Please try again.'];
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
                <?php render_error_summary($errors); ?>

                <form method="post" enctype="multipart/form-data" novalidate>
                    <div class="input-group input-group-outline mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($form['name']) ?>">
                    </div>

                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control mb-3">
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

                    <label class="form-label d-flex justify-content-between align-items-center">
                        <span>Images (the first one is the thumbnail)</span>
                        <span class="text-xs text-secondary"><?= ProductImages::count($product['image']) ?> of <?= ProductImages::MAX_IMAGES ?> used</span>
                    </label>

                    <div id="image-gallery" class="image-gallery mb-2">
                        <?php foreach ($existingTokens as $token): ?>
                            <div class="image-gallery-item" data-token="existing:<?= htmlspecialchars($token) ?>">
                                <img src="../../public/<?= htmlspecialchars(ProductImages::url($product['id'], $token)) ?>" alt="">
                                <input type="hidden" name="image_order[]" value="existing:<?= htmlspecialchars($token) ?>">
                                <div class="image-gallery-actions">
                                    <button type="button" class="btn btn-xs btn-outline-dark" data-move="-1" title="Move earlier">&larr;</button>
                                    <button type="button" class="btn btn-xs btn-outline-dark" data-move="1" title="Move later">&rarr;</button>
                                    <button type="button" class="btn btn-xs btn-outline-danger" data-remove="1" title="Remove">&times;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="text-xs text-secondary">
                        Reorder with &larr; / &rarr; and remove with &times; (a removed image is deleted when you save).
                        You can add up to <strong><?= (int) $remaining ?></strong> more (limit <?= ProductImages::MAX_IMAGES ?>).
                    </p>

                    <label class="form-label">Add Images</label>
                    <input type="file" id="new-images" name="images[]" class="form-control mb-1" accept="image/*" multiple>

                    <style>
                        .image-gallery { display: flex; flex-wrap: wrap; gap: .5rem; }
                        .image-gallery-item { position: relative; width: 96px; }
                        .image-gallery-item img { width: 96px; height: 96px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e5e5; }
                        .image-gallery-item:first-child img { border-color: #e91e63; box-shadow: 0 0 0 1px #e91e63; }
                        .image-gallery-actions { display: flex; justify-content: center; gap: .25rem; margin-top: .25rem; }
                        .image-gallery-actions .btn { padding: .1rem .4rem; font-size: .75rem; line-height: 1; }
                    </style>

                    <script>
                    (function () {
                        var gallery = document.getElementById('image-gallery');
                        var input = document.getElementById('new-images');
                        if (!gallery || !input) { return; }

                        var MAX = <?= (int) ProductImages::MAX_IMAGES ?>;
                        var REMAINING = <?= (int) $remaining ?>;

                        function items() { return gallery.querySelectorAll('.image-gallery-item'); }

                        function refresh() {
                            var all = items();
                            all.forEach(function (item, i) {
                                var prev = item.querySelector('[data-move="-1"]');
                                var next = item.querySelector('[data-move="1"]');
                                if (prev) { prev.disabled = (i === 0); }
                                if (next) { next.disabled = (i === all.length - 1); }
                            });
                        }

                        function actionsHtml() {
                            return '<button type="button" class="btn btn-xs btn-outline-dark" data-move="-1" title="Move earlier">&larr;</button>'
                                 + '<button type="button" class="btn btn-xs btn-outline-dark" data-move="1" title="Move later">&rarr;</button>'
                                 + '<button type="button" class="btn btn-xs btn-outline-danger" data-remove="1" title="Remove">&times;</button>';
                        }

                        gallery.addEventListener('click', function (e) {
                            var btn = e.target.closest('[data-move], [data-remove]');
                            if (!btn) { return; }
                            var item = btn.closest('.image-gallery-item');
                            if (!item) { return; }

                            if (btn.hasAttribute('data-remove')) {
                                item.parentNode.removeChild(item);
                            } else if (parseInt(btn.getAttribute('data-move'), 10) < 0) {
                                if (item.previousElementSibling) { gallery.insertBefore(item, item.previousElementSibling); }
                            } else if (item.nextElementSibling) {
                                gallery.insertBefore(item.nextElementSibling, item);
                            }
                            refresh();
                        });

                        input.addEventListener('change', function () {
                            var picked = input.files ? Array.prototype.slice.call(input.files) : [];

                            // Remove old previews, then rebuild in the picked order.
                            gallery.querySelectorAll('.image-gallery-item[data-new]').forEach(function (n) {
                                n.parentNode.removeChild(n);
                            });

                            if (picked.length > REMAINING) {
                                alert('You can add ' + REMAINING + ' more (limit ' + MAX + '). Only ' + REMAINING + ' will be used.');
                            }

                            picked.forEach(function (file, index) {
                                if (index >= REMAINING) { return; }

                                var card = document.createElement('div');
                                card.className = 'image-gallery-item';
                                card.setAttribute('data-new', '1');

                                var img = document.createElement('img');
                                img.src = URL.createObjectURL(file);
                                img.alt = '';

                                var hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = 'image_order[]';
                                hidden.value = 'new:' + index;

                                var tag = document.createElement('div');
                                tag.className = 'text-xs text-secondary text-center';
                                tag.textContent = 'new';

                                var actions = document.createElement('div');
                                actions.className = 'image-gallery-actions';
                                actions.innerHTML = actionsHtml();

                                card.appendChild(img);
                                card.appendChild(hidden);
                                card.appendChild(tag);
                                card.appendChild(actions);
                                gallery.appendChild(card);
                            });

                            refresh();
                        });

                        refresh();
                    })();
                    </script>

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
