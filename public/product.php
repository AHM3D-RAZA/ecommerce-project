<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ProductImages.php';

$db = new Database();

$slug = trim($_GET['slug'] ?? '');
$product = $slug !== '' ? $db->selectOne(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.status = 1",
    [$slug]
) : null;

if (!$product) {
    header('Location: category.php');
    exit;
}

$related = $db->select(
    "SELECT * FROM products WHERE category_id = ? AND id != ? AND status = 1 ORDER BY RAND() LIMIT 4",
    [$product['category_id'], $product['id']]
);

$pageTitle = $product['name'];
$pageScript = 'demo-4.js';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/product-card.php';

$inStock = (int) $product['stock'] > 0;

// Gallery images in stored order; the first one is the hero/thumbnail.
$imageUrls = ProductImages::urls($product);
if (empty($imageUrls)) {
    $imageUrls = [ProductImages::heroUrl($product)];
}
$heroUrl = $imageUrls[0];
?>
            <nav aria-label="breadcrumb" class="breadcrumb-nav border-0 mb-0">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="category.php?slug=<?= urlencode($product['category_slug']) ?>"><?= htmlspecialchars($product['category_name']) ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
                    </ol>
                </div>
            </nav><!-- End .breadcrumb-nav -->

            <div class="page-content">
                <div class="container">
                    <div class="product-details-top">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="product-gallery">
                                    <figure class="product-main-image">
                                        <img id="product-main-image" src="<?= htmlspecialchars($heroUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    </figure>

                                    <?php if (count($imageUrls) > 1): ?>
                                        <div class="product-image-gallery">
                                            <?php foreach ($imageUrls as $i => $url): ?>
                                                <a href="#" class="product-gallery-item<?= $i === 0 ? ' active' : '' ?>" data-image="<?= htmlspecialchars($url) ?>">
                                                    <img src="<?= htmlspecialchars($url) ?>" alt="">
                                                </a>
                                            <?php endforeach; ?>
                                        </div><!-- End .product-image-gallery -->
                                    <?php endif; ?>
                                </div><!-- End .product-gallery -->

                                <style>
                                    .product-image-gallery { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1rem; }
                                    .product-image-gallery .product-gallery-item { width: 72px; border: 1px solid #e5e5e5; border-radius: 6px; overflow: hidden; cursor: pointer; opacity: .75; transition: opacity .2s ease, border-color .2s ease; }
                                    .product-image-gallery .product-gallery-item:hover,
                                    .product-image-gallery .product-gallery-item.active { opacity: 1; border-color: #c96; }
                                    .product-image-gallery .product-gallery-item img { width: 100%; height: 72px; object-fit: cover; display: block; }
                                </style>

                                <script>
                                    (function () {
                                        var main = document.getElementById('product-main-image');
                                        var thumbs = document.querySelectorAll('.product-image-gallery .product-gallery-item');
                                        if (!main || !thumbs.length) { return; }

                                        thumbs.forEach(function (thumb) {
                                            thumb.addEventListener('click', function (e) {
                                                e.preventDefault();
                                                var url = thumb.getAttribute('data-image');
                                                if (!url) { return; }
                                                main.src = url;
                                                thumbs.forEach(function (t) { t.classList.remove('active'); });
                                                thumb.classList.add('active');
                                            });
                                        });
                                    })();
                                </script>
                            </div><!-- End .col-md-6 -->

                            <div class="col-md-6">
                                <div class="product-details">
                                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

                                    <div class="product-price">
                                        $<?= number_format($product['price'], 2) ?>
                                    </div>

                                    <div class="product-content">
                                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                                    </div>

                                    <div class="details-filter-row" style="display: flex; align-items: center; flex-wrap: wrap; gap: 2rem; row-gap: 0.25rem; line-height: 1.4;">
                                        <label style="margin-bottom: 0; white-space: nowrap;">Availability:</label>
                                        <?php if ($inStock): ?>
                                            <span class="text-primary" style="display: inline-block;">In stock (<?= (int) $product['stock'] ?> available)</span>
                                        <?php else: ?>
                                            <span class="text-danger" style="display: inline-block;">Out of stock</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($inStock): ?>
                                        <form action="cart-action.php" method="post" data-cart-add novalidate>
                                            <input type="hidden" name="op" value="add">
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                                            <div class="details-filter-row details-row-size">
                                                <label for="qty">Qty:</label>
                                                <div class="product-details-quantity">
                                                    <input type="number" name="qty" id="qty" class="form-control" value="1" min="1" max="<?= (int) $product['stock'] ?>" step="1">
                                                </div>
                                            </div>

                                            <div class="product-details-action">
                                                <button type="submit" class="btn-product btn-cart"><span>add to cart</span></button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="product-details-action">
                                            <span class="btn-product btn-cart disabled"><span>out of stock</span></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="product-details-footer">
                                        <div class="product-cat">
                                            <span>Category:</span>
                                            <a href="category.php?slug=<?= urlencode($product['category_slug']) ?>"><?= htmlspecialchars($product['category_name']) ?></a>
                                        </div>
                                    </div>
                                </div><!-- End .product-details -->
                            </div><!-- End .col-md-6 -->
                        </div><!-- End .row -->
                    </div><!-- End .product-details-top -->

                    <div class="product-details-tab">
                        <ul class="nav nav-pills justify-content-center" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="product-desc-link" data-toggle="tab" href="#product-desc-tab" role="tab" aria-controls="product-desc-tab" aria-selected="true">Description</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="product-shipping-link" data-toggle="tab" href="#product-shipping-tab" role="tab" aria-controls="product-shipping-tab" aria-selected="false">Shipping & Returns</a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="product-desc-tab" role="tabpanel" aria-labelledby="product-desc-link">
                                <div class="product-desc-content">
                                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="product-shipping-tab" role="tabpanel" aria-labelledby="product-shipping-link">
                                <div class="product-desc-content">
                                    <p>Orders are shipped within 2 business days. Free shipping on orders over $99. Returns are accepted within 30 days of delivery, in original condition.</p>
                                </div>
                            </div>
                        </div>
                    </div><!-- End .product-details-tab -->

                    <?php if (!empty($related)): ?>
                        <h2 class="title text-center mb-4">You May Also Like</h2>

                        <div class="owl-carousel owl-simple carousel-equal-height carousel-with-shadow" data-toggle="owl"
                            data-owl-options='{"nav": false, "dots": true, "margin": 20, "loop": false,
                                "responsive": {"0": {"items":2}, "480": {"items":2}, "768": {"items":3}, "992": {"items":4}}}'>
                            <?php foreach ($related as $p): render_product_card($p); endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div><!-- End .container -->
            </div><!-- End .page-content -->
        </main><!-- End .main -->

        <style>
            /* The demo-4 skin fills the add-to-cart button with its blue hover
               background but the label keeps the skin's blue text colour too, so
               the text disappears into the button. Force a light label and drop
               the underline shadow on hover/focus. :not(.disabled) leaves the
               "out of stock" state untouched. */
            .product-details-action .btn-cart:not(.disabled):hover,
            .product-details-action .btn-cart:not(.disabled):focus {
                color: #fff;
            }

            .product-details-action .btn-cart:not(.disabled):hover span,
            .product-details-action .btn-cart:not(.disabled):focus span {
                color: #fff;
                box-shadow: none;
            }
        </style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
