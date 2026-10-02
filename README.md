# ShopWave - E-Commerce Training Project

A core PHP + MySQLi e-commerce site with an admin dashboard, built for the
full-stack training assignment. Storefront design is based on the Molla
HTML template; the admin dashboard (coming in a later phase) is based on
Material Dashboard.

## Current status: Phase 4

Phase 1 delivered the project foundation and the homepage. Phase 2 added the
browsing + cart experience. Phase 3 adds customer accounts and checkout:

- **`public/login.php`** / **`public/register.php`** - converted from the
  template's tabbed `login.html` (Sign In / Register in one form-box, shared
  via `includes/auth-form.php` so both pages look identical). Real
  validation, duplicate-email checking, wrong-password handling, and a
  `?redirect=` param so checkout can send you to sign in and land you right
  back on checkout afterwards.
- **`public/logout.php`** - signs you out of the storefront and sends you
  home. The storefront and the admin panel keep independent logins (Auth
  "guards"), so signing out of one never signs you out of the other, and an
  admin and a customer can be signed in at the same time.
- **`public/account.php`** - converted from `dashboard.html`. Three tabs:
  Dashboard (welcome message), Orders (your real order history from the
  `orders` table, with a link to each order's confirmation page), and
  Account Details (a working form to update your name/email, and optionally
  your password - it checks your current password before changing it).
- **`public/checkout.php`** - converted from `checkout.html`. Real billing
  form, an order summary built from your actual cart, and two payment
  methods:
  - **Cash on Delivery** - places the order immediately with
    `payment_status = pending`.
  - **PayPal Sandbox** - renders a real PayPal Smart Payment Button (via the
    PayPal JS SDK, using the sandbox Client ID in `config/paypal.php`). The
    button creates a sandbox order for the cart total, captures it in the
    browser, and only then submits the form with the PayPal order ID and
    capture status attached - the server refuses to place a PayPal order
    unless that capture actually completed.
  - Either way: writes to `orders` and `order_items`, decrements product
    stock, and re-checks stock right before saving in case something sold
    out while you were filling in the form.
- **`public/order-confirmation.php`** - a real thank-you page: order number,
  date, payment method/status, order status, shipping address, and the
  line items, all pulled from the database.

## Phase 4 (this update): the admin dashboard

Everything under `admin/` is new this phase, converted from the Material
Dashboard template (sidebar + navbar shell, cards, tables, form controls).
Only the template files actually used - `material-dashboard.min.css`,
the nucleo icon font, Chart.js, and the Bootstrap bundle - were copied into
`admin/assets/`, not the full template package with its demo pages.

- **`admin/login.php`** - a separate sign-in screen for staff. It calls
  `Auth::authenticate()` to check the credentials *without* writing a
  session, then only calls `Auth::establish($user, 'admin')` if the account's
  role is `admin`; a customer's credentials entered here are refused with
  "This login is for administrators only." and nothing is written to the
  session, so a customer signed in on the storefront keeps their login. The
  reverse is true too: an administrator who types their admin credentials into
  `public/login.php` never gets a storefront session - they are signed into the
  admin guard and redirected straight to `admin/index.php` with a notice. To
  shop, an admin needs a customer account like everyone else.
  `admin/logout.php` signs out only the admin.
- **`admin/index.php`** - dashboard home: live counts of products,
  categories, customers, orders and total revenue, a 6-month sales chart,
  a "low stock" list (5 units or fewer), and the 8 most recent orders.
- **`admin/categories/`** - full CRUD (`index.php`, `create.php`,
  `edit.php`). Category images upload through `core/Uploader.php` (MIME-type
  check, 2MB limit, random file name) into `public/uploads/categories/`.
  Deleting a category that still has products under it is blocked with an
  explanation instead of silently cascading.
- **`admin/products/`** - full CRUD plus search-by-name and
  filter-by-category on the list page. Images are a **gallery of up to 5**:
  `create.php` stages the picked files, creates the product row to get its id,
  then moves the files into `public/uploads/products/{id}/` and saves the
  ordered filename list. `edit.php` shows the current images in a reorderable
  strip (each card has move up/down and remove), accepts ordered
  `existing:<file>` / `new:<n>` tokens, keeps and reorders what was kept, adds
  the new files, and deletes the files that were dropped from the list. The cap
  on an edit is the *remaining* room (`max - existing`), so the error reads
  "You can add 2 more (limit 5)" rather than a confusing "at most 5". Rejected
  files (wrong type, too large, over the cap) are counted and reported instead
  of being silently dropped, and empty input slots are skipped while every file
  keeps its own position. Deleting a product removes its whole
  `uploads/products/{id}/` folder so nothing is orphaned; a product that
  already appears in a placed order is blocked (hide it via the status toggle
  instead) since order history has to stay intact.
- **`admin/users/index.php`** - every registered user, with a one-click
  active/inactive toggle (an admin can't deactivate their own account by
  mistake). Deactivated customers are rejected at login by the existing
  `Auth::login()` check from Phase 3.
- **`admin/orders/`** - `index.php` lists every order with a status filter
  (Processing / Shipped / Delivered / Cancelled); `view.php` shows the full
  line items, shipping address and payment info, with a form to update the
  order status and payment status - this is what moves an order through
  Processing -> Shipped -> Delivered.
- **`admin/reports.php`** - weekly (last 7 days), monthly (this calendar
  year) and yearly sales, each as a bar chart plus the raw numbers in a
  table, using the exact grouping queries from the project brief.
- **`core/ProductImages.php`** (new) - the single place that knows about
  product images. It decodes/encodes the JSON array in `products.image`,
  returns the first image (the hero/thumbnail), builds filesystem paths and
  public URLs from the product id + the `rawurlencode()`d filename, and lists
  the files currently in a product's folder. Bundled seed images (which live
  under `public/assets/images/demos/demo-4/`) are stored as paths and are never
  deleted. Every consumer - product cards, the product page gallery, the cart
  and its dropdown, admin product list, admin order items - goes through this
  helper instead of reading the column directly.
- **`core/Cart.php`** (new) + the **`cart`** table - the cart is stored in the
  database (one row per product) rather than in the session. Each browser gets a
  random `cart_key` kept in its session, so guests and signed-in customers
  behave identically and the cart survives signing in and out, exactly as it
  did before. `Cart` owns adding, quantity changes (always clamped to real
  stock), removing, clearing and the badge/dropdown totals; `checkout.php` only
  reads products that are still visible, and the cart is emptied once an order
  is placed.
- **`public/cart-action.php`** (new) - the add-to-cart endpoint. **Add to Cart
  never navigates away**: `assets/js/cart.js` posts the form in the background,
  shows a toast to acknowledge it and updates the header cart count live. With
  JavaScript off the same POST still works and returns you to the page you came
  from - never the cart page. The `Referer` is only honoured when it points at
  this same host, so it can't be used as an open redirect.
- **`core/Helpers.php`** - `shop_image()` still resolves category images the
  same way it always has (uploaded path vs. seed path) on both the storefront
  and the admin side.
- **`core/Uploader.php`** - shared upload validation/saving used by both the
  category form (single file) and the product forms (staged multi-file), plus
  the safe recursive folder delete.

Every flow above was tested against a live MariaDB + PHP server before
packaging: admin login (and rejection of a customer's login and of a wrong
password) -> dashboard stats -> add a category with an image -> add a
product with an image in that category -> confirm the new product actually
shows up on the live storefront category page with its uploaded image ->
place a real Cash on Delivery order as the demo customer -> see it appear
in the admin order list -> open it, change status to Shipped and payment to
Completed -> confirm the customer's Account -> Orders page immediately
shows "Shipped" -> confirm the dashboard and reports numbers update to
include that order's total -> confirm deleting a category with products, or
a product that's in an order, is blocked with a clear message -> confirm
visiting any `admin/` page while logged out redirects to `admin/login.php`.

Not built yet: nothing major - the assignment's full feature list is
covered. Possible polish for a later pass: pagination on the products/orders
lists once there are a lot more rows, and CSV export on the reports page.

### A known simplification in the PayPal integration

For a sandbox training integration, the order total is created client-side
(`actions.order.create({ purchase_units: [{ amount: ... }] })`) using the
cart total the server rendered into the page, rather than via a separate
server-to-server "create order" call to PayPal's REST API. That's the
standard "Smart Buttons only" integration and is fine for this assignment,
but in a real production system you'd create the PayPal order server-side
(with your PayPal secret) so the amount can never be tampered with by
editing the page before clicking Pay.

### Testing this phase

Every flow below was actually run against a live MySQL database and PHP's
built-in server (not just eyeballed) before this zip was made: register ->
land on the account dashboard logged in -> add a product to the cart ->
load checkout -> place a Cash on Delivery order -> confirm the order and
its line item actually landed in `orders`/`order_items` -> confirm the
product's stock dropped by the right amount -> see the order appear on the
Account -> Orders tab -> log out -> confirm `account.php` and
`checkout.php` correctly redirect to `login.php?redirect=...` when logged
out -> log back in with the seeded demo customer -> confirm a wrong
password is rejected with a clear error -> confirm registering with an
email that's already in use is rejected -> confirm submitting checkout with
PayPal selected but no completed capture is rejected with an error instead
of silently placing an unpaid order.

Two small things worth knowing when you test:
- PayPal needs your own free sandbox Client ID dropped into
  `config/paypal.php` (see the **PayPal** section below) - with the
  placeholder value still in there, the PayPal button will fail to load,
  but Cash on Delivery works with no setup at all.
- Checkout requires being signed in (the `orders` table needs a real
  `user_id`), so it will send you to `login.php` first if you're not -
  that's expected, not a bug.

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
6. Visit `http://localhost/ecommerce-project/admin/login.php` for the admin
   dashboard, and sign in with the seeded admin account below.

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
  hit **Add to Cart** - you stay on the page, a toast confirms it and the cart
  badge in the header ticks up.
- Click the cart icon (top right) to see the dropdown, or **View Cart** for
  the full cart page - change a quantity and click **Update Cart**, or
  remove an item with the X.
- Try adding more of a product than its stock allows (e.g. the Bose
  SoundLink has 30 in stock) - it clamps at the real stock number instead
  of going over.
- Click **Register** in the header top bar, create an account, and notice
  you're immediately signed in and dropped on **My Account**.
- Try registering again with the same email - you'll get a clear
  "account already exists" message instead of a database error.
- Sign out, then try visiting `account.php` or `checkout.php` directly -
  you'll be bounced to the sign-in page instead of getting an error.
- Sign back in with a wrong password on purpose - you'll get
  "Incorrect email or password." instead of a blank page.
- Add something to your cart, go to **Checkout**, fill in the billing
  form, choose **Cash on Delivery**, and place the order - you'll land on
  a real order confirmation page, and the order will show up under
  **My Account -> Orders**.
- Do the same again but choose **PayPal** (after adding your sandbox
  Client ID - see below) - you'll get a real PayPal sandbox popup; pay
  with a PayPal sandbox test buyer account and the order will be placed
  automatically once the payment completes.
- On **My Account -> Account Details**, change your name or email, or set
  a new password (it'll ask for your current password first) - and see
  the change reflected immediately in the header.

## Test logins (already seeded)

- **Admin:** admin@shop.com / admin123 - sign in at `admin/login.php` for
  the dashboard. This account cannot shop on the storefront: typing it into
  `public/login.php` never creates a customer session, it just redirects you
  to the admin dashboard.
- **Customer:** customer@shop.com / customer123

## Things to try in the admin dashboard

Once you're signed in at `admin/login.php`:

- Look over the dashboard home - the numbers and the "last 6 months" chart
  are real, pulled straight from your `orders`/`products`/`categories`
  tables (they'll look empty/flat on a freshly imported database until you
  place a few test orders from the storefront).
- **Categories** - add a new category with an image, edit an existing one
  and swap its image, toggle one to "Hidden" and confirm it disappears from
  the storefront's category menu, then try deleting "Computer & Laptop"
  (blocked, since it still has products) versus your new empty test
  category (works fine).
- **Products** - add a product under your new category with its own image,
  edit its price/stock, search for a product by name, filter by category,
  then check it immediately on the storefront.
- **Orders** - place an order from the storefront (as the demo customer),
  then find it under Orders in the admin, open it, and move it from
  Processing -> Shipped -> Delivered - sign back in as the customer and
  confirm Account -> Orders shows the new status right away.
- **Users** - see the demo customer listed, toggle them to Inactive, then
  try logging in as them on the storefront (rejected with "This account has
  been deactivated") - toggle them back to Active afterwards so you can
  keep testing checkout as that customer.
- **Sales Reports** - after placing a couple of orders, check the weekly,
  monthly and yearly charts and tables reflect them correctly.

## PayPal

`config/paypal.php` has a placeholder sandbox Client ID. Get your own free
one at https://developer.paypal.com under My Apps & Credentials -> Sandbox,
and drop it in before the checkout phase.

## Stripe and the `.env` file

Card payments go through Stripe Checkout (sandbox/test mode). Secrets are
kept out of the code in a `.env` file in the project root:

1. Copy `.env.example` to `.env`.
2. Fill in `STRIPE_PUBLISHABLE_KEY` and `STRIPE_SECRET_KEY` with your test
   keys from the Stripe Dashboard (Developers -> API keys).
3. `APP_DEBUG=true` shows the real error on the checkout page while
   developing; set it to `false` before going live.

`.env` is git-ignored and blocked from the browser by `.htaccess`. Use
Stripe's test card `4242 4242 4242 4242` with any future expiry and any CVC.

If you set up the database before Stripe replaced PayPal, the `orders` table
still has the old payment-method list and Stripe orders will fail to save.
Update it once with:

    ALTER TABLE orders MODIFY payment_method ENUM('cod', 'stripe') NOT NULL;

(Any existing rows with `paypal` need to be deleted or changed first.)

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
- Same approach for the admin side: Material Dashboard's demo pages (its
  own `dashboard.html`, `tables.html`, `sign-in.html`, etc., all branded as
  "Creative Tim" with placeholder charts/tables/notifications) were used as
  the source for the sidebar/navbar/card/table markup and CSS classes, but
  every admin screen was written from scratch against the real database
  rather than copying a demo page file-for-file - there's no fake
  "Creative Tim" branding, dummy notification dropdowns, or placeholder
  numbers left in.
