        <footer class="footer">
            <div class="cta bg-image bg-dark pt-4 pb-5 mb-0" style="background-image: url(assets/images/demos/demo-4/bg-5.jpg);">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-sm-10 col-md-8 col-lg-6">
                            <div class="cta-heading text-center">
                                <h3 class="cta-title text-white">Get The Latest Deals</h3>
                                <p class="cta-desc text-white">and receive <span class="font-weight-normal">$20 coupon</span> for first shopping</p>
                            </div>

                            <form action="#" method="post">
                                <div class="input-group input-group-round">
                                    <input type="email" class="form-control form-control-white" placeholder="Enter your Email Address" aria-label="Email Adress" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="submit"><span>Subscribe</span><i class="icon-long-arrow-right"></i></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div><!-- End .cta -->

            <div class="footer-middle">
                <div class="container">
                    <div class="row">
                        <div class="col-sm-6 col-lg-3">
                            <div class="widget widget-about">
                                <img src="assets/images/demos/demo-4/logo-footer.png" class="footer-logo" alt="ShopWave Logo" width="105" height="25">
                                <p>Your one-stop shop for laptops, phones, cameras, TVs, audio gear and smart watches.</p>

                                <div class="widget-call">
                                    <i class="icon-phone"></i>
                                    Got a question? Call us 24/7
                                    <a href="tel:#">+0123 456 789</a>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="widget">
                                <h4 class="widget-title">Useful Links</h4>
                                <ul class="widget-list">
                                    <li><a href="about.php">About Us</a></li>
                                    <li><a href="contact.php">Contact Us</a></li>
                                    <li><a href="category.php">Shop All Products</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="widget">
                                <h4 class="widget-title">Customer Service</h4>
                                <ul class="widget-list">
                                    <li><a href="contact.php">Payment Methods</a></li>
                                    <li><a href="contact.php">Returns</a></li>
                                    <li><a href="contact.php">Shipping</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <div class="widget">
                                <h4 class="widget-title">My Account</h4>
                                <ul class="widget-list">
                                    <?php if (Auth::isLoggedIn()): ?>
                                        <li><a href="account.php">My Account</a></li>
                                        <li><a href="account.php#orders">My Orders</a></li>
                                        <li><a href="logout.php">Sign Out</a></li>
                                    <?php else: ?>
                                        <li><a href="login.php">Sign In</a></li>
                                        <li><a href="register.php">Create Account</a></li>
                                    <?php endif; ?>
                                    <li><a href="cart.php">View Cart</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- End .footer-middle -->

            <div class="footer-bottom">
                <div class="container">
                    <p class="footer-copyright">Copyright &copy; <?= date('Y') ?> ShopWave. All Rights Reserved.</p>
                    <figure class="footer-payments">
                        <img src="assets/images/payments.png" alt="Payment methods" width="272" height="20">
                    </figure>
                </div>
            </div><!-- End .footer-bottom -->
        </footer><!-- End .footer -->
    </div><!-- End .page-wrapper -->

    <button id="scroll-top" title="Back to Top"><i class="icon-arrow-up"></i></button>

    <!-- Mobile Menu -->
    <div class="mobile-menu-overlay"></div>

    <div class="mobile-menu-container mobile-menu-light">
        <div class="mobile-menu-wrapper">
            <span class="mobile-menu-close"><i class="icon-close"></i></span>

            <form action="category.php" method="get" class="mobile-search">
                <label for="mobile-search" class="sr-only">Search</label>
                <input type="search" class="form-control" name="q" id="mobile-search" placeholder="Search product ..." required>
                <button class="btn btn-primary" type="submit"><i class="icon-search"></i></button>
            </form>

            <ul class="nav nav-pills-mobile nav-border-anim" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="mobile-menu-link" data-toggle="tab" href="#mobile-menu-tab" role="tab" aria-controls="mobile-menu-tab" aria-selected="true">Menu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="mobile-cats-link" data-toggle="tab" href="#mobile-cats-tab" role="tab" aria-controls="mobile-cats-tab" aria-selected="false">Categories</a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="mobile-menu-tab" role="tabpanel" aria-labelledby="mobile-menu-link">
                    <nav class="mobile-nav">
                        <ul class="mobile-menu">
                            <li class="active"><a href="index.php">Home</a></li>
                            <li>
                                <a href="category.php">Shop</a>
                                <ul>
                                    <?php foreach ($navCategories as $cat): ?>
                                        <li><a href="category.php?slug=<?= urlencode($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                            <li><a href="about.php">About</a></li>
                            <li><a href="contact.php">Contact</a></li>
                            <?php if (Auth::isLoggedIn()): ?>
                                <li><a href="account.php">My Account</a></li>
                                <li><a href="logout.php">Sign Out</a></li>
                            <?php else: ?>
                                <li><a href="login.php">Sign In</a></li>
                                <li><a href="register.php">Register</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav><!-- End .mobile-nav -->
                </div>
                <div class="tab-pane fade" id="mobile-cats-tab" role="tabpanel" aria-labelledby="mobile-cats-link">
                    <nav class="mobile-cats-nav">
                        <ul class="mobile-cats-menu">
                            <?php foreach ($navCategories as $cat): ?>
                                <li><a href="category.php?slug=<?= urlencode($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav><!-- End .mobile-cats-nav -->
                </div>
            </div><!-- End .tab-content -->

            <div class="social-icons">
                <a href="#" class="social-icon" target="_blank" title="Facebook"><i class="icon-facebook-f"></i></a>
                <a href="#" class="social-icon" target="_blank" title="Twitter"><i class="icon-twitter"></i></a>
                <a href="#" class="social-icon" target="_blank" title="Instagram"><i class="icon-instagram"></i></a>
                <a href="#" class="social-icon" target="_blank" title="Youtube"><i class="icon-youtube"></i></a>
            </div>
        </div><!-- End .mobile-menu-wrapper -->
    </div><!-- End .mobile-menu-container -->

    <!-- Plugins JS File -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.hoverIntent.min.js"></script>
    <script src="assets/js/jquery.waypoints.min.js"></script>
    <script src="assets/js/superfish.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>
    <script src="assets/js/bootstrap-input-spinner.js"></script>
    <script src="assets/js/jquery.plugin.min.js"></script>
    <script src="assets/js/jquery.magnific-popup.min.js"></script>
    <script src="assets/js/jquery.countdown.min.js"></script>
    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
    <?php if (!empty($pageScript)): ?>
        <script src="assets/js/demos/<?= htmlspecialchars($pageScript) ?>"></script>
    <?php endif; ?>
</body>
</html>
