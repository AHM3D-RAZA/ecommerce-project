# ShopWave - E-Commerce Training Project

A core PHP + MySQLi e-commerce site with an admin dashboard, built for the
full-stack training assignment. Storefront design is based on the Molla
HTML template; the admin dashboard (coming in a later phase) is based on
Material Dashboard.

## Current status: Phase 3

Phase 1 delivered the project foundation and the homepage. Phase 2 added the
browsing + cart experience. Phase 3 adds customer accounts and checkout:

- **`public/login.php`** / **`public/register.php`** - converted from the
  template's tabbed `login.html` (Sign In / Register in one form-box, shared
  via `includes/auth-form.php` so both pages look identical). Real
  validation, duplicate-email checking, wrong-password handling, and a
  `?redirect=` param so checkout can send you to sign in and land you right
  back on checkout afterwards.
- **`public/logout.php`** - destroys the session and sends you home.
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

Not built yet (next phase): the admin dashboard (Material Dashboard
template) - category/product CRUD, user management, order status updates,
and sales reports. `admin/login.php` and the rest of `admin/` don't exist
yet, so there's no way to change an order's status or manage products
through the UI yet (you can still do it directly in phpMyAdmin/MySQL if you
want to see an order move through Processing -> Shipped -> Delivered).

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

- **Admin:** admin@shop.com / admin123 (admin dashboard isn't built yet -
  Phase 4 - but this account can sign in and use the storefront/account
  pages like any customer for now)
- **Customer:** customer@shop.com / customer123

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
