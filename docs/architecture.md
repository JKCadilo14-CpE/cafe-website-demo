# JKC Cafe Architecture

## 1. Overview

JKC Cafe is a PHP/MySQL cafe ordering demo with customer storefront workflows and an admin workspace. Customers can browse products, manage a cart, place demo orders, update account settings, and send contact messages. Admin users can manage products, orders, users, messages, analytics, and settings.

The project is designed as a portfolio and educational application. Payment, password recovery, and deployment data are demo-oriented and should not be treated as production payment or identity infrastructure.

## 2. Folder Structure

- `components/` contains shared PHP application helpers, security helpers, database access, cart/order utilities, and shared customer layout partials.
- `admin pages/` contains admin routes, admin-specific components, styles, and scripts.
- `user-css/` contains customer-facing stylesheets.
- `user-js/` contains customer-facing JavaScript for navigation, filters, form behavior, and page interactions.
- `database/` contains the sanitized schema used for local setup and demo deployment.
- `docs/screenshots/` contains portfolio screenshots used by the README.
- `tests/` contains Playwright regression tests.
- `uploads/` stores runtime-uploaded product and profile images and should be writable by the web server but not committed.

## 3. Request Lifecycle

Apache routes each PHP request directly to the matching page script. Most pages load `components/app.php` first, which configures security headers, starts the hardened session, exposes shared helpers, and provides database access through `app_db()`.

Page scripts then perform request-specific work: authorization, CSRF validation for POST requests, input validation, database queries, redirects, and view rendering. Customer pages include shared layout partials such as the navbar and footer. Admin pages call the shared admin authorization helper before rendering admin shell components.

## 4. Authentication and Sessions

Signup creates a user with `password_hash()` and signs the user in after `session_regenerate_id(true)`. Login verifies credentials with `password_verify()`, refreshes session identity fields, regenerates the session id, and redirects admins or customers to the correct dashboard.

Sessions are configured before `session_start()` with strict mode, cookie-only sessions, disabled transparent session ids, HttpOnly cookies, SameSite=Lax, and HTTPS-aware Secure cookies. Logout is POST-only, requires CSRF validation, and destroys the session through the shared session cleanup helper.

Role handling uses the `users.role` value, where `1` represents an admin and `0` represents a customer. Admin authorization refreshes the current user from the database before granting access, so demoted, deleted, inactive, disabled, or suspended users lose admin access on the next request.

## 5. CSRF Protection

CSRF protection is centralized in `components/app.php`. Shared helpers generate a session token, render hidden token fields, validate submitted tokens, and reject invalid requests safely.

State-changing POST routes call `app_require_csrf()` before processing mutations. This covers login, signup, logout, customer settings, cart actions, checkout actions, contact submissions, admin product/order/user/settings mutations, and admin notification/contact-message actions.

## 6. Customer Workflow

Customers can browse public pages, search and filter the menu, add products to the cart, update quantities, and move through demo checkout flows for cash on delivery, card, and GCash. Orders can be viewed from customer profile/order pages, and pending orders can be tracked or cancelled when allowed.

Customer accounts support signup, login, profile/settings updates, password changes, recovery contact updates, profile photos, product reminders, and in-app notifications. The contact form stores guest messages for admin review.

## 7. Admin Workflow

The admin workspace includes a dashboard, product management, order management, user management, contact messages, analytics, profile, and settings. Admin pages use `app_require_admin()` for authorization.

Admins can create, edit, delete, and update product availability; review and update order statuses; read contact messages; inspect analytics; manage profile/settings; and update user roles or delete users. User role/delete actions include safeguards against self-demotion, self-delete, and removing the last admin account.

## 8. Security Features

Completed security features include:

- Prepared statements for database writes and sensitive reads
- Output escaping through the shared `e()` helper
- CSRF protection for state-changing POST actions
- Hardened session settings and HTTPS-aware cookie security
- POST-only logout with CSRF validation
- Conservative browser security headers
- Shared minimum password policy
- Session-based rate limiting for sensitive forms
- Admin role refresh from the database
- Admin user-management safeguards for role and delete actions

Deferred production hardening includes:

- Foreign keys, stronger database constraints, and a migration workflow
- Persistent database/cache-backed rate limiting
- Content Security Policy compatibility pass
- Upload validation hardening
- Admin audit logging for sensitive actions

## 9. Database Overview

- `users` stores customer/admin accounts, password hashes, roles, account status, recovery contacts, and profile image paths.
- `products` stores menu products, categories, prices, images, and availability status.
- `orders` stores order headers, customer delivery details, payment method, status, totals, and cancellation details.
- `order_items` stores the products and quantities attached to each order.
- `contact_messages` stores customer/guest contact form submissions and read status.
- `product_reminders` stores customer reminder requests for unavailable products.
- `user_notifications` stores in-app customer/admin notifications.

## 10. Testing Strategy

PHP syntax checks are run with `php -l` across application PHP files. Playwright Chromium tests cover public page smoke checks, CSRF behavior, session cookie settings, POST logout, rate limiting, shared password policy, admin authorization, and admin user-management safeguards.

Responsive regression is documented through screenshot coverage and prior viewport testing across common mobile, tablet, and desktop widths. Admin-only Playwright checks can run when `ADMIN_EMAIL` and `ADMIN_PASSWORD` are configured for a local admin account.

## 11. Deployment Notes

Local and hosted deployments use `config.local.php` for database credentials. This file should be created from `config.example.php`, kept out of Git, and never shared publicly.

Import `database/schema.sql` for the sanitized schema and safe demo product data. Ensure `uploads/` is writable by the web server and is excluded from source control. Payment flows are demo-only, so do not deploy real payment QR codes, real payment credentials, customer records, production password hashes, uploaded private images, or database dumps.

Use HTTPS for hosted deployments so Secure cookies and HSTS can take effect. Re-test customer flows, admin flows, JSON endpoints, uploads, and demo checkout behavior after deployment.
