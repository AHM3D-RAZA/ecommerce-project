# ShopWave - E-Commerce Training Project

A core PHP + MySQLi e-commerce site with an admin dashboard, built for the
full-stack training assignment. Storefront design is based on the Molla
HTML template; the admin dashboard (coming in a later phase) is based on
Material Dashboard.

## Current status: Phase 2

Phase 1 delivered the project foundation and the homepage. Phase 2 adds the
rest of the browsing + cart experience:

- **`public/category.php`** - the shop/listing page, converted from the
  template's `category.html`. Filter by category (sidebar, with live product
  counts pulled from the database) or by price range, search by name (wired
  to the header search box), sort by newest/price/name, and paginate (9
  products per page).
- **`public/product.php`** - the product detail page, converted from
  `product.html`. Shows real price, description, stock ("In stock (N
  available)" / "Out of stock"), a quantity-aware add-to-cart form, and a
  "You May Also Like" strip of related products from the same category.
- **`public/cart.php`** - a real, working cart page: add a product (from any
  product card or the product page), remove a line, or update quantities,
  all stored in the session and validated against actual stock. The header's
  mini-cart dropdown and this page share the same cart-building code, so
  they always agree.
- Two small Phase 1 bugs fixed along the way: `includes/header.php` was
  missing the `<main class="main">` tag that `footer.php` closes (harmless
  in a browser, but not valid HTML), and the mini-cart dropdown was linking
  to `product.php?slug=<id>` (a number) instead of the product's real slug.
- Pulled the repeated product-card markup out of `index.php` into
  `includes/product-card.php` so `index.php`, `category.php` and
  `product.php` all render products the same way from one place.

Every page above was smoke-tested against a real MySQL database (the actual
`schema.sql` in this repo) before being handed off - filtering, search,
sorting, pagination, add/remove/update-quantity, and stock clamping (you
can't add more of something than is actually in stock) all behave correctly.

Not built yet (next phases): checkout + PayPal Sandbox, login/register
pages, and the admin dashboard. The header's Login/Register/Checkout links
still point to pages that don't exist yet - that's expected until Phase 3.

## Setup on WAMP

1. Copy the whole `ecommerce-project` folder into `C:\wamp64\www\`
   (or wherever your WAMP `www` folder is).
2. Start WAMP, make sure Apache and MySQL are both running (green icon).
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), go to the **Import**
   tab, choose `schema.sql` from the project folder, and click **Go**.
   This creates the `ecommerce_project` database with all tables and seed data.
4. Check `config/database.php` - the defaults (`root` user, empty password)
   match WAMP's defaults, so you shouldn't need to change anything unless
   your MySQL root user has a password set.
5. Visit `http://localhost/ecommerce-project/public/` in your browser.
   You should see the homepage with real categories and products.

## Things to try in this phase

Once it's running at `http://localhost/ecommerce-project/public/`:

- Click **Browse Categories** or **Shop** in the header, or any category
  block on the homepage - lands you on `category.php` with that category
  pre-filtered.
- On the shop page, try the price filter in the sidebar, or the sort
  dropdown (Newest / Price / Name).
- Use the search box in the header (desktop or mobile menu) - it searches
  product names and reuses the same `category.php` page.
- Click any product to land on its detail page, change the quantity, and
  hit **Add to Cart**.
- Click the cart icon (top right) to see the dropdown, or **View Cart** for
  the full cart page - change a quantity and click **Update Cart**, or
  remove an item with the X.
- Try adding more of a product than its stock allows (e.g. the Bose
  SoundLink has 30 in stock) - it clamps at the real stock number instead
  of going over.

## Test logins (already seeded)

- **Admin:** admin@shop.com / admin123
- **Customer:** customer@shop.com / customer123

(Login page itself is built in a later phase - these accounts are ready
in the database for when it lands.)

## PayPal

`config/paypal.php` has a placeholder sandbox Client ID. Get your own free
one at https://developer.paypal.com under My Apps & Credentials -> Sandbox,
and drop it in before the checkout phase.

## Notes on the template conversion

- The Molla template's top navigation is actually its own "demo picker"
  (links to 24 homepage variants, an Elements showcase, etc.) - that's
  template-authoring scaffolding, not real site content, so it was replaced
  with a clean, real menu (Home / Shop by category / About / Contact /
  Account) using the same Molla styling. Everything else on the homepage
  (hero slider, category blocks, banners, product carousels, deals,
  brand strip, trust badges) follows the template's layout and classes.
- Only the template asset files actually used by the pages we've built so
  far were copied into `public/assets/` - not the entire template package.
