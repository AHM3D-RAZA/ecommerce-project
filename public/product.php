<?php
require_once __DIR__ . '/../core/Database.php';

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
                                        <img src="<?= htmlspecialchars(shop_image($product['image'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    </figure>
                                </div><!-- End .product-gallery -->
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
                                        <form action="cart.php" method="get">
                                            <input type="hidden" name="add" value="<?= (int) $product['id'] ?>">

                                            <div class="details-filter-row details-row-size">
                                                <label for="qty">Qty:</label>
                                                <div class="product-details-quantity">
                                                    <input type="number" name="qty" id="qty" class="form-control" value="1" min="1" max="<?= (int) $product['stock'] ?>" step="1" required>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
