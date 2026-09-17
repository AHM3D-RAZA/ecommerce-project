# ShopWave - E-Commerce Training Project

A core PHP + MySQLi e-commerce site with an admin dashboard, built for the
full-stack training assignment. Storefront design is based on the Molla
HTML template; the admin dashboard (coming in a later phase) is based on
Material Dashboard.

## Current status: Phase 1

This phase delivers the project foundation and a fully working, database-driven
homepage:

- Full folder structure (config / core / public / admin / includes)
- Database schema with seed data (6 categories, 16 products, 1 admin + 1 demo customer)
- Core classes: Database (MySQLi + prepared statements), Auth, Session, Validator
- Shared header/footer used on every page, with a real category menu and a
  working session-based cart indicator
- Homepage (`public/index.php`) - converted from the Molla template's
  `index-4.html`, pulling categories and products live from the database

Not built yet (next phases): category listing page, product detail page,
cart page, checkout + PayPal, login/register pages, and the admin dashboard.
Right now several header links (Shop, Product, Cart, Checkout, Login) point
to pages that don't exist yet - that's expected at this stage.

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
