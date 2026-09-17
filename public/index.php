<?php
require_once __DIR__ . '/../core/Database.php';

$pageTitle = 'Home';
$pageScript = 'demo-4.js';

$db = new Database();

// Categories (also used by header.php, but we need it here before include for the "Explore Categories" block)
$categories = $db->select("SELECT * FROM categories WHERE status = 1 ORDER BY id ASC");

// Products grouped by category, for the "New Arrivals" tabs
$productsByCategory = [];
foreach ($categories as $cat) {
    $productsByCategory[$cat['id']] = $db->select(
        "SELECT * FROM products WHERE category_id = ? AND status = 1 ORDER BY id DESC LIMIT 8",
        [$cat['id']]
    );
}
$allNewArrivals = $db->select("SELECT * FROM products WHERE status = 1 ORDER BY id DESC LIMIT 8");

// Two products for the "Deals & Outlet" strip
$deals = $db->select("SELECT * FROM products WHERE status = 1 ORDER BY price DESC LIMIT 2");

// A flat grid of featured products for the bottom of the page
$featured = $db->select("SELECT * FROM products WHERE status = 1 ORDER BY RAND() LIMIT 8");

require_once __DIR__ . '/../includes/header.php';

// Small helper so we don't repeat this product-card markup five times
function render_product_card($p)
{
    $img = 'assets/images/demos/demo-4/' . htmlspecialchars($p['image']);
    $name = htmlspecialchars($p['name']);
    $link = 'product.php?slug=' . urlencode($p['slug']);
    ?>
    <div class="product product-2">
        <figure class="product-media">
            <?php if ((int) $p['stock'] === 0): ?>
                <span class="product-label label-out">Out of stock</span>
            <?php endif; ?>
            <a href="<?= $link ?>">
                <img src="<?= $img ?>" alt="<?= $name ?>" class="product-image">
            </a>

            <div class="product-action">
                <a href="cart.php?add=<?= (int) $p['id'] ?>" class="btn-product btn-cart"><span>add to cart</span></a>
            </div>
        </figure>

        <div class="product-body">
            <h3 class="product-title"><a href="<?= $link ?>"><?= $name ?></a></h3>
            <div class="product-price">
                $<?= number_format($p['price'], 2) ?>
            </div>
        </div>
    </div>
    <?php
}
?>
            <div class="intro-slider-container mb-5">
                <div class="intro-slider owl-carousel owl-theme owl-nav-inside owl-light" data-toggle="owl"
                    data-owl-options='{
                        "dots": true,
                        "nav": false,
                        "responsive": {
                            "1200": {
                                "nav": true,
                                "dots": false
                            }
                        }
                    }'>
                    <div class="intro-slide" style="background-image: url(assets/images/demos/demo-4/slider/slide-1.png);">
                        <div class="container intro-content">
                            <div class="row justify-content-end">
                                <div class="col-auto col-sm-7 col-md-6 col-lg-5">
                                    <h3 class="intro-subtitle text-third">Deals and Promotions</h3>
                                    <h1 class="intro-title">MacBook Pro</h1>
                                    <h1 class="intro-title">13" Display, i5</h1>

                                    <div class="intro-price">
                                        <span class="text-third">
                                            $1,199<sup>.99</sup>
                                        </span>
                                    </div>

                                    <a href="product.php?slug=macbook-pro-13-i5" class="btn btn-primary btn-round">
                                        <span>Shop Now</span>
                                        <i class="icon-long-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="intro-slide" style="background-image: url(assets/images/demos/demo-4/slider/slide-2.png);">
                        <div class="container intro-content">
                            <div class="row justify-content-end">
                                <div class="col-auto col-sm-7 col-md-6 col-lg-5">
                                    <h3 class="intro-subtitle text-primary">New Arrival</h3>
                                    <h1 class="intro-title">Apple iPad Pro <br>11 Inch, 256GB </h1>

                                    <div class="intro-price">
                                        <sup>Today:</sup>
                                        <span class="text-primary">
                                            $899<sup>.99</sup>
                                        </span>
                                    </div>

                                    <a href="product.php?slug=ipad-pro-11-256gb" class="btn btn-primary btn-round">
                                        <span>Shop Now</span>
                                        <i class="icon-long-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <span class="slider-loader"></span>
            </div><!-- End .intro-slider-container -->

            <div class="container">
                <h2 class="title text-center mb-4">Explore Popular Categories</h2>

                <div class="cat-blocks-container">
                    <div class="row">
                        <?php foreach ($categories as $cat): ?>
                            <div class="col-6 col-sm-4 col-lg-2">
                                <a href="category.php?slug=<?= urlencode($cat['slug']) ?>" class="cat-block">
                                    <figure>
                                        <span>
                                            <img src="assets/images/demos/demo-4/<?= htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>">
                                        </span>
                                    </figure>
                                    <h3 class="cat-block-title"><?= htmlspecialchars($cat['name']) ?></h3>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div><!-- End .container -->

            <div class="mb-4"></div>

            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-6 col-lg-4">
                        <div class="banner banner-overlay banner-overlay-light">
                            <a href="category.php?slug=smart-phones">
                                <img src="assets/images/demos/demo-4/banners/banner-1.png" alt="Banner">
                            </a>
                            <div class="banner-content">
                                <h4 class="banner-subtitle"><a href="category.php?slug=smart-phones">Smart Offer</a></h4>
                                <h3 class="banner-title"><a href="category.php?slug=smart-phones">Save on <strong>the latest <br>phones & tablets</strong></a></h3>
                                <a href="category.php?slug=smart-phones" class="banner-link">Shop Now<i class="icon-long-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="banner banner-overlay banner-overlay-light">
                            <a href="category.php?slug=audio">
                                <img src="assets/images/demos/demo-4/banners/banner-2.jpg" alt="Banner">
                            </a>
                            <div class="banner-content">
                                <h4 class="banner-subtitle"><a href="category.php?slug=audio">Time Deals</a></h4>
                                <h3 class="banner-title"><a href="category.php?slug=audio"><strong>Bose Speakers</strong> <br>& Headphones</a></h3>
                                <a href="category.php?slug=audio" class="banner-link">Shop Now<i class="icon-long-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="banner banner-overlay banner-overlay-light">
                            <a href="category.php?slug=digital-cameras">
                                <img src="assets/images/demos/demo-4/banners/banner-3.png" alt="Banner">
                            </a>
                            <div class="banner-content">
                                <h4 class="banner-subtitle"><a href="category.php?slug=digital-cameras">Clearance</a></h4>
                                <h3 class="banner-title"><a href="category.php?slug=digital-cameras"><strong>GoPro & Sony</strong> <br>Cameras</a></h3>
                                <a href="category.php?slug=digital-cameras" class="banner-link">Shop Now<i class="icon-long-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- End .container -->

            <div class="mb-3"></div>

            <div class="container new-arrivals">
                <div class="heading heading-flex mb-3">
                    <div class="heading-left">
                        <h2 class="title">New Arrivals</h2>
                    </div>

                    <div class="heading-right">
                        <ul class="nav nav-pills nav-border-anim justify-content-center" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="new-all-link" data-toggle="tab" href="#new-all-tab" role="tab">All</a>
                            </li>
                            <?php foreach ($categories as $cat): ?>
                                <li class="nav-item">
                                    <a class="nav-link" id="new-cat-<?= $cat['id'] ?>-link" data-toggle="tab" href="#new-cat-<?= $cat['id'] ?>-tab" role="tab"><?= htmlspecialchars($cat['name']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div><!-- End .heading -->

                <div class="tab-content tab-content-carousel just-action-icons-sm">
                    <div class="tab-pane p-0 fade show active" id="new-all-tab" role="tabpanel">
                        <div class="owl-carousel owl-full carousel-equal-height carousel-with-shadow" data-toggle="owl"
                            data-owl-options='{"nav": true, "dots": true, "margin": 20, "loop": false,
                                "responsive": {"0": {"items":2}, "480": {"items":2}, "768": {"items":3}, "992": {"items":4}, "1200": {"items":5}}}'>
                            <?php foreach ($allNewArrivals as $p): render_product_card($p); endforeach; ?>
                        </div>
                    </div><!-- End #new-all-tab -->

                    <?php foreach ($categories as $cat): ?>
                        <div class="tab-pane p-0 fade" id="new-cat-<?= $cat['id'] ?>-tab" role="tabpanel">
                            <div class="owl-carousel owl-full carousel-equal-height carousel-with-shadow" data-toggle="owl"
                                data-owl-options='{"nav": true, "dots": true, "margin": 20, "loop": false,
                                    "responsive": {"0": {"items":2}, "480": {"items":2}, "768": {"items":3}, "992": {"items":4}, "1200": {"items":5}}}'>
                                <?php if (empty($productsByCategory[$cat['id']])): ?>
                                    <p class="p-3">No products in this category yet.</p>
                                <?php else: ?>
                                    <?php foreach ($productsByCategory[$cat['id']] as $p): render_product_card($p); endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div><!-- End tab -->
                    <?php endforeach; ?>
                </div>
            </div><!-- End .container -->

            <div class="mb-5"></div>

            <div class="container">
                <div class="cta cta-border mb-5" style="background-image: url(assets/images/demos/demo-4/bg-1.jpg);">
                    <img src="assets/images/demos/demo-4/camera.png" alt="camera" class="cta-img">
                    <div class="row justify-content-center">
                        <div class="col-md-12">
                            <div class="cta-content">
                                <div class="cta-text text-right text-white">
                                    <p>Shop Today's Deals <br><strong>Great Cameras, Great Prices</strong></p>
                                </div>
                                <a href="category.php?slug=digital-cameras" class="btn btn-primary btn-round"><span>Shop Cameras</span><i class="icon-long-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- End .container -->

            <div class="container">
                <div class="heading text-center mb-3">
                    <h2 class="title">Deals & Outlet</h2>
                    <p class="title-desc">Today's picks, hand-chosen from our catalog</p>
                </div>

                <div class="row">
                    <?php foreach ($deals as $i => $p): ?>
                        <div class="col-lg-6 deal-col">
                            <div class="deal" style="background-image: url('assets/images/demos/demo-4/deal/bg-<?= $i + 1 ?>.jpg');">
                                <div class="deal-top">
                                    <h2>Featured Pick</h2>
                                    <h4>While stocks last.</h4>
                                </div>

                                <div class="deal-content">
                                    <h3 class="product-title"><a href="product.php?slug=<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['name']) ?></a></h3>
                                    <div class="product-price">
                                        <span class="new-price">$<?= number_format($p['price'], 2) ?></span>
                                    </div>
                                    <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-link"><span>Shop Now</span><i class="icon-long-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="more-container text-center mt-1 mb-5">
                    <a href="category.php" class="btn btn-outline-dark-2 btn-round btn-more"><span>Shop all products</span><i class="icon-long-arrow-right"></i></a>
                </div>
            </div><!-- End .container -->

            <div class="container">
                <hr class="mb-0">
                <div class="owl-carousel mt-5 mb-5 owl-simple" data-toggle="owl"
                    data-owl-options='{"nav": false, "dots": false, "margin": 30, "loop": false,
                        "responsive": {"0": {"items":2}, "420": {"items":3}, "600": {"items":4}, "900": {"items":5}, "1024": {"items":6}}}'>
                    <a href="#" class="brand"><img src="assets/images/brands/1.png" alt="Brand Name"></a>
                    <a href="#" class="brand"><img src="assets/images/brands/2.png" alt="Brand Name"></a>
                    <a href="#" class="brand"><img src="assets/images/brands/3.png" alt="Brand Name"></a>
                    <a href="#" class="brand"><img src="assets/images/brands/4.png" alt="Brand Name"></a>
                    <a href="#" class="brand"><img src="assets/images/brands/5.png" alt="Brand Name"></a>
                    <a href="#" class="brand"><img src="assets/images/brands/6.png" alt="Brand Name"></a>
                </div>
            </div><!-- End .container -->

            <div class="mb-2"></div>

            <div class="container">
                <div class="heading heading-flex mb-3">
                    <div class="heading-left">
                        <h2 class="title">Featured Products</h2>
                    </div>
                </div>

                <div class="products">
                    <div class="row justify-content-center">
                        <?php foreach ($featured as $p): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <?php render_product_card($p); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div><!-- End .container -->

            <div class="mb-5"></div>

            <div class="container icon-boxes-container">
                <div class="row">
                    <div class="col-sm-6 col-lg-3">
                        <div class="icon-box icon-box-side">
                            <span class="icon-box-icon text-dark"><i class="icon-shipping"></i></span>
                            <div class="icon-box-content">
                                <h3 class="icon-box-title">Free Shipping</h3>
                                <p>On orders over $99</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="icon-box icon-box-side">
                            <span class="icon-box-icon text-dark"><i class="icon-rotate-left"></i></span>
                            <div class="icon-box-content">
                                <h3 class="icon-box-title">Free Returns</h3>
                                <p>Within 30 days</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="icon-box icon-box-side">
                            <span class="icon-box-icon text-dark"><i class="icon-info-circle"></i></span>
                            <div class="icon-box-content">
                                <h3 class="icon-box-title">Secure Checkout</h3>
                                <p>COD & PayPal</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="icon-box icon-box-side">
                            <span class="icon-box-icon text-dark"><i class="icon-life-ring"></i></span>
                            <div class="icon-box-content">
                                <h3 class="icon-box-title">We Support</h3>
                                <p>24/7 amazing service</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- End .icon-boxes-container -->
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
