# ShopWave

A full-stack e-commerce web application built with core PHP and MySQL. It has a customer storefront with a database-backed cart, Stripe and Cash on Delivery checkout, and a separate admin dashboard for managing products, categories, orders, users and sales reports.

The storefront UI is based on the [Molla](https://themeforest.net/item/molla-ecommerce-html-template/) HTML template and the admin UI on [Material Dashboard](https://www.creative-tim.com/product/material-dashboard). Every page is wired to a real database; no demo content is left over from the templates.

## Features

### Storefront

- Home page with hero slider, category blocks, and product carousels
- Category and shop pages with price filtering, sorting (newest / price / name) and search
- Product detail pages with an image gallery and quantity selector
- Database-backed cart: add to cart via AJAX (with a toast and live header count), quantity clamped to real stock, and the cart survives login and logout
- Customer registration, login and logout, with redirect-back support (`?redirect=`)
- Account area with order history and a profile form (name, email, optional password change)
- Checkout with two payment methods:
  - **Cash on Delivery**
  - **Stripe Checkout** (test mode)
- Stock is re-checked and decremented when an order is placed
- Order confirmation page and transactional emails (order placed, payment received or failed, shipped, delivered, cancelled, welcome)

### Admin dashboard

- Separate admin login, independent from the storefront session (an admin and a customer can be signed in at the same time)
- Dashboard with live counts, total revenue, a 6-month sales chart, low-stock list and recent orders
- Category management (CRUD) with image upload; deleting a category that still has products is blocked
- Product management (CRUD) with search, category filter, and a reorderable gallery of up to 5 images; deleting a product that appears in an order is blocked
- Order management with status filter and status / payment status updates
- User management with active / inactive toggle
- Weekly, monthly and yearly sales reports with charts and tables

## Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP (no framework), MySQLi |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap, jQuery, Molla template (storefront), Material Dashboard (admin), Chart.js |
| Payments | Stripe Checkout (REST API via cURL) |
| Email | PHPMailer 6.9.3 (vendored in `lib/`, no Composer needed) |

## Project Structure

```
ecommerce-project/
├── admin/            Admin dashboard (login, categories, products, orders, users, reports)
├── config/           Database and Stripe configuration
├── core/             Application classes (Auth, Cart, Database, Mailer, Uploader, Validator, ...)
├── includes/         Shared header, footer and partials
├── lib/phpmailer/    Vendored PHPMailer
├── public/           Storefront pages, assets and uploads
├── storage/          Runtime logs (mail.log)
├── schema.sql        Database schema and seed data
└── .htaccess
```

## Requirements

- PHP 7.4 or newer (developed on 8.2) with the `mysqli`, `curl` and `fileinfo` extensions
- MySQL 5.7+ or MariaDB 10.3+
- Apache (WAMP, XAMPP, MAMP or LAMP all work)
- A Stripe account in test mode (only needed for card payments)

## Installation

1. **Clone the repository** into your web server's document root (for WAMP: `C:\wamp64\www\`).

   ```bash
   git clone https://github.com/AHM3D-RAZA/ecommerce-project.git
   ```

2. **Import the database.** Open phpMyAdmin, go to the **Import** tab, choose `schema.sql` and click **Go**. This creates the `ecommerce_project` database with all tables and seed data.

   Or from the command line:

   ```bash
   mysql -u root -p < schema.sql
   ```

3. **Check the database settings** in `config/database.php`. The defaults match a fresh WAMP install:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'ecommerce_project');
   ```

4. **Create a `.env` file** in the project root (see [Configuration](#configuration)).

5. **Open the site.**

   | Area | URL |
   | --- | --- |
   | Storefront | `http://localhost/ecommerce-project/public/` |
   | Admin panel | `http://localhost/ecommerce-project/admin/login.php` |

## Configuration

Secrets are read from a `.env` file in the project root. The file is git-ignored and blocked from browser access by `.htaccess`.

```ini
# Application
APP_URL=http://localhost/ecommerce-project/public
APP_DEBUG=true

# Stripe (test mode keys from Dashboard -> Developers -> API keys)
STRIPE_PUBLISHABLE_KEY=pk_test_xxxxxxxxxxxxxxxx
STRIPE_SECRET_KEY=sk_test_xxxxxxxxxxxxxxxx
STRIPE_CURRENCY=usd

# SMTP (optional - if left empty, emails are skipped)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=you@example.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=you@example.com
MAIL_FROM_NAME=ShopWave
```

| Variable | Description |
| --- | --- |
| `APP_URL` | Base URL of `public/`, used for Stripe redirect URLs. If omitted, it is worked out from the current request. |
| `APP_DEBUG` | Shows detailed errors on the checkout page. Set to `false` in production. |
| `STRIPE_PUBLISHABLE_KEY` / `STRIPE_SECRET_KEY` | Your Stripe test keys. |
| `STRIPE_CURRENCY` | Currency code for Stripe payments (default `usd`). |
| `STRIPE_CA_BUNDLE` | Optional path to a CA bundle if cURL cannot verify HTTPS on your machine. A bundle is already included at `config/cacert.pem`. |
| `MAIL_*` | SMTP settings. For Gmail, use an [App Password](https://support.google.com/accounts/answer/185833), not your account password. |

Cash on Delivery works without any `.env` setup.

## Demo Accounts

The seed data includes two accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@shop.com` | `admin123` |
| Customer | `customer@shop.com` | `customer123` |

> **Change these before deploying anywhere public.**

The admin account signs in at `admin/login.php` only. Entering it on the storefront login redirects to the admin panel and never creates a customer session. To shop, an admin needs a separate customer account.

## Testing Stripe Payments

With test keys in `.env`, choose **Stripe** at checkout and pay with Stripe's test card:

| Field | Value |
| --- | --- |
| Card number | `4242 4242 4242 4242` |
| Expiry | Any future date |
| CVC | Any 3 digits |

## Design Notes

- **Separate auth guards.** The storefront and admin keep independent sessions, so signing out of one never signs you out of the other.
- **Database-backed cart.** Each browser gets a random `cart_key` stored in its session. Guests and signed-in customers behave the same way, and the cart persists across login and logout.
- **Product images.** `core/ProductImages.php` is the single place that reads and writes the JSON image list stored in `products.image`. Uploads are validated by MIME type and capped at 2 MB, and the uploads folder blocks script execution.
- **Email never blocks an order.** Mail is sent only after the order is committed, failures are caught and logged to `storage/mail.log` (recipient masked), and missing `MAIL_*` settings simply skip sending.
- **Stripe verification.** Card orders are created as `pending` and marked paid only after the server confirms the Checkout Session with Stripe.

## Roadmap

- Pagination on product and order lists
- CSV export on the reports page

## Acknowledgements

- [Molla](https://themeforest.net/item/molla-ecommerce-html-template/) storefront template
- [Material Dashboard](https://www.creative-tim.com/product/material-dashboard) by Creative Tim
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)
- [Stripe](https://stripe.com/docs)
