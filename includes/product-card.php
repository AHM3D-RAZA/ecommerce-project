<?php
// Same product-card markup gets reused on the homepage, category page
// and the "You may also like" strip on the product page, so it lives
// here once instead of being copy-pasted everywhere.
require_once __DIR__ . '/../core/ProductImages.php';

function render_product_card($p)
{
    $img = htmlspecialchars(ProductImages::heroUrl($p));
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
                <?php if ((int) $p['stock'] === 0): ?>
                    <span class="btn-product btn-cart disabled"><span>out of stock</span></span>
                <?php else: ?>
                    <a href="cart-action.php?op=add&amp;product_id=<?= (int) $p['id'] ?>&amp;qty=1"
                       class="btn-product btn-cart" data-cart-add><span>add to cart</span></a>
                <?php endif; ?>
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
