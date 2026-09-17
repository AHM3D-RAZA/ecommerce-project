<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
Session::start();

$db = new Database();

// Categories for the nav menu - used on every page
$navCategories = $db->select("SELECT id, name, slug FROM categories WHERE status = 1 ORDER BY name ASC");

// Cart is kept in the session as [product_id => quantity]
$cart = Session::get('cart', []);
$cartItems = [];
$cartTotal = 0;
$cartCount = 0;

if (!empty($cart)) {
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id, name, slug, price, image, stock FROM products WHERE id IN ($placeholders)");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $qty = (int) $cart[$row['id']];
        $lineTotal = $row['price'] * $qty;
        $cartTotal += $lineTotal;
        $cartCount += $qty;
        $cartItems[] = array_merge($row, ['qty' => $qty, 'line_total' => $lineTotal]);
    }
    $stmt->close();
}

$isLoggedIn = Auth::isLoggedIn();
$userName = Session::get('user_name');

$pageTitle = isset($pageTitle) ? $pageTitle . ' - ShopWave' : 'ShopWave - Online Electronics Store';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="shortcut icon" href="assets/images/icons/favicon.ico">
    <link rel="stylesheet" href="assets/vendor/line-awesome/line-awesome/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/plugins/owl-carousel/owl.carousel.css">
    <link rel="stylesheet" href="assets/css/plugins/magnific-popup/magnific-popup.css">
    <link rel="stylesheet" href="assets/css/plugins/jquery.countdown.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/skins/skin-demo-4.css">
    <link rel="stylesheet" href="assets/css/demos/demo-4.css">
</head>

<body>
    <div class="page-wrapper">
        <header class="header header-intro-clearance header-4">
            <div class="header-top">
                <div class="container">
                    <div class="header-left">
                        <a href="tel:#"><i class="icon-phone"></i>Call: +0123 456 789</a>
                    </div><!-- End .header-left -->

                    <div class="header-right">
                        <ul class="top-menu">
                            <li>
                                <a href="#">Links</a>
                                <ul>
                                    <?php if ($isLoggedIn): ?>
                                        <li><a href="account.php">Hi, <?= htmlspecialchars($userName) ?></a></li>
                                        <li><a href="logout.php">Sign Out</a></li>
                                    <?php else: ?>
                                        <li><a href="login.php">Sign in</a></li>
                                        <li><a href="register.php">Register</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        </ul><!-- End .top-menu -->
                    </div><!-- End .header-right -->
                </div><!-- End .container -->
            </div><!-- End .header-top -->

            <div class="header-middle">
                <div class="container">
                    <div class="header-left">
                        <button class="mobile-menu-toggler">
                            <span class="sr-only">Toggle mobile menu</span>
                            <i class="icon-bars"></i>
                        </button>

                        <a href="index.php" class="logo">
                            <img src="assets/images/demos/demo-4/logo.png" alt="ShopWave Logo" width="105" height="25">
                        </a>
                    </div><!-- End .header-left -->

                    <div class="header-center">
                        <div class="header-search header-search-extended header-search-visible d-none d-lg-block">
                            <a href="#" class="search-toggle" role="button"><i class="icon-search"></i></a>
                            <form action="category.php" method="get">
                                <div class="header-search-wrapper search-wrapper-wide">
                                    <label for="q" class="sr-only">Search</label>
                                    <button class="btn btn-primary" type="submit"><i class="icon-search"></i></button>
                                    <input type="search" class="form-control" name="q" id="q" placeholder="Search product ..." required>
                                </div><!-- End .header-search-wrapper -->
                            </form>
                        </div><!-- End .header-search -->
                    </div>

                    <div class="header-right">
                        <div class="dropdown cart-dropdown">
                            <a href="#" class="dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-display="static">
                                <div class="icon">
                                    <i class="icon-shopping-cart"></i>
                                    <span class="cart-count"><?= (int) $cartCount ?></span>
                                </div>
                                <p>Cart</p>
                            </a>

                            <div class="dropdown-menu dropdown-menu-right">
                                <?php if (empty($cartItems)): ?>
                                    <div class="dropdown-cart-products">
                                        <p class="text-center mb-0 py-2">Your cart is empty.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="dropdown-cart-products">
                                        <?php foreach ($cartItems as $item): ?>
                                            <div class="product">
                                                <div class="product-cart-details">
                                                    <h4 class="product-title">
                                                        <a href="product.php?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                                                    </h4>

                                                    <span class="cart-product-info">
                                                        <span class="cart-product-qty"><?= (int) $item['qty'] ?></span>
                                                        x $<?= number_format($item['price'], 2) ?>
                                                    </span>
                                                </div><!-- End .product-cart-details -->

                                                <figure class="product-image-container">
                                                    <a href="product.php?slug=<?= urlencode($item['slug']) ?>" class="product-image">
                                                        <img src="assets/images/demos/demo-4/<?= htmlspecialchars($item['image']) ?>" alt="product">
                                                    </a>
                                                </figure>
                                                <a href="cart.php?remove=<?= (int) $item['id'] ?>" class="btn-remove" title="Remove Product"><i class="icon-close"></i></a>
                                            </div><!-- End .product -->
                                        <?php endforeach; ?>
                                    </div><!-- End .cart-product -->

                                    <div class="dropdown-cart-total">
                                        <span>Total</span>
                                        <span class="cart-total-price">$<?= number_format($cartTotal, 2) ?></span>
                                    </div><!-- End .dropdown-cart-total -->

                                    <div class="dropdown-cart-action">
                                        <a href="cart.php" class="btn btn-primary">View Cart</a>
                                        <a href="checkout.php" class="btn btn-outline-primary-2"><span>Checkout</span><i class="icon-long-arrow-right"></i></a>
                                    </div><!-- End .dropdown-cart-total -->
                                <?php endif; ?>
                            </div><!-- End .dropdown-menu -->
                        </div><!-- End .cart-dropdown -->
                    </div><!-- End .header-right -->
                </div><!-- End .container -->
            </div><!-- End .header-middle -->

            <div class="header-bottom sticky-header">
                <div class="container">
                    <div class="header-left">
                        <div class="dropdown category-dropdown">
                            <a href="#" class="dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-display="static" title="Browse Categories">
                                Browse Categories <i class="icon-angle-down"></i>
                            </a>

                            <div class="dropdown-menu">
                                <nav class="side-nav">
                                    <ul class="menu-vertical sf-arrows">
                                        <?php foreach ($navCategories as $cat): ?>
                                            <li><a href="category.php?slug=<?= urlencode($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul><!-- End .menu-vertical -->
                                </nav><!-- End .side-nav -->
                            </div><!-- End .dropdown-menu -->
                        </div><!-- End .category-dropdown -->
                    </div><!-- End .header-left -->

                    <div class="header-center">
                        <nav class="main-nav">
                            <ul class="menu sf-arrows">
                                <li><a href="index.php">Home</a></li>

                                <li class="megamenu-container">
                                    <a href="category.php" class="sf-with-ul">Shop</a>
                                    <div class="megamenu">
                                        <div class="menu-col">
                                            <div class="menu-title">Shop by Category</div>
                                            <ul>
                                                <?php foreach ($navCategories as $cat): ?>
                                                    <li><a href="category.php?slug=<?= urlencode($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </li>

                                <li><a href="about.php">About</a></li>
                                <li><a href="contact.php">Contact</a></li>
                            </ul><!-- End .menu -->
                        </nav><!-- End .main-nav -->
                    </div><!-- End .header-center -->

                    <div class="header-right">
                        <i class="la la-lightbulb-o"></i><p>Clearance<span class="highlight">&nbsp;Up to 30% Off</span></p>
                    </div>
                </div><!-- End .container -->
            </div><!-- End .header-bottom -->
        </header><!-- End .header -->

        <main class="main">
            <?php $flashSuccess = Session::flash('success'); ?>
            <?php $flashError = Session::flash('error'); ?>
            <?php if ($flashSuccess || $flashError): ?>
                <div class="container mt-3">
                    <?php if ($flashSuccess): ?>
                        <div class="alert alert-primary alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($flashSuccess) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($flashError): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($flashError) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
