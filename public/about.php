<?php
require_once __DIR__ . '/../core/Database.php';

$pageTitle = 'About Us';

require_once __DIR__ . '/../includes/header.php';
?>
            <div class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')">
                <div class="container">
                    <h1 class="page-title">About Us<span>Shop</span></h1>
                </div><!-- End .container -->
            </div><!-- End .page-header -->

            <nav aria-label="breadcrumb" class="breadcrumb-nav mb-3">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">About Us</li>
                    </ol>
                </div><!-- End .container -->
            </nav><!-- End .breadcrumb-nav -->

            <div class="page-content pb-5">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <h2 class="title mb-3">Welcome to ShopWave</h2>
                            <p class="lead">
                                ShopWave is your one-stop shop for laptops, phones, cameras, TVs, audio gear
                                and smart watches. We hand-pick the best electronics from brands you trust -
                                Apple, Sony, Samsung, Bose, Google and more - and get them to your door fast.
                            </p>
                            <p>
                                What started as a small electronics counter has grown into a full online store
                                serving customers nationwide. Our promise is simple: genuine products, honest
                                prices, and friendly support before and after every sale.
                            </p>

                            <div class="icon-boxes-container mt-5">
                                <div class="row">
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="icon-box icon-box-side">
                                            <span class="icon-box-icon text-primary"><i class="icon-shipping"></i></span>
                                            <div class="icon-box-content">
                                                <h3 class="icon-box-title">Fast Shipping</h3>
                                                <p>Orders ship within 2 business days, free over $99.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="icon-box icon-box-side">
                                            <span class="icon-box-icon text-primary"><i class="icon-rotate-left"></i></span>
                                            <div class="icon-box-content">
                                                <h3 class="icon-box-title">Easy Returns</h3>
                                                <p>30 days to return anything in its original condition.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="icon-box icon-box-side">
                                            <span class="icon-box-icon text-primary"><i class="icon-us-dollar"></i></span>
                                            <div class="icon-box-content">
                                                <h3 class="icon-box-title">Best Prices</h3>
                                                <p>We match the market so you never overpay.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- End .icon-boxes-container -->

                            <div class="text-center mt-5">
                                <a href="category.php" class="btn btn-primary btn-round">
                                    <span>Start Shopping</span><i class="icon-long-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div><!-- End .container -->
            </div><!-- End .page-content -->
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>