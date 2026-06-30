# JKC Cafe

[![Live Demo](https://img.shields.io/badge/Live-Demo-success?logo=googlechrome&logoColor=white)](http://cafedemotempsite.infinityfree.io/)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)
![Playwright](https://img.shields.io/badge/Tested_with-Playwright-2EAD33?logo=playwright&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![Status](https://img.shields.io/badge/Status-Active-success)
![Responsive](https://img.shields.io/badge/Responsive-320px--1440px-0b7a5a)
![Portfolio Project](https://img.shields.io/badge/Portfolio-Project-8a5a2b)
![GitHub release](https://img.shields.io/github/v/release/JKCadilo14-CpE/cafe-website-demo)

JKC Cafe is a full-stack cafe ordering portfolio project built with PHP and MySQL/MariaDB. It includes a customer-facing storefront for browsing products and placing demo orders, plus an admin dashboard for managing menu items, orders, users, and customer messages.

> **Portfolio Project:** This application is intended for demonstration and educational purposes. Payment and recovery workflows are simulated.

## ✨ Highlights

- Full-stack PHP and MySQL/MariaDB cafe ordering application
- Responsive customer storefront and admin dashboard interfaces
- Responsive UI verified across common viewport widths from 320px to 1440px
- Admin tools for products, orders, users, customer messages, and analytics
- Playwright browser smoke testing for core customer and admin flows
- Portfolio/demo-friendly payment and recovery workflows

## 📑 Table of Contents

- [Live Demo](#live-demo)
- [Highlights](#-highlights)
- [Screenshot Gallery](#screenshot-gallery)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Key Learnings](#-key-learnings)
- [Local Setup](#local-setup)
- [Testing](#testing)
- [Deployment Notes](#deployment-notes)
- [Repository Structure](#repository-structure)
- [Application Architecture](#-application-architecture)
- [Roadmap](#-roadmap)
- [Author](#author)

## Live Demo

Visit the public demo: [cafedemotempsite.infinityfree.io](http://cafedemotempsite.infinityfree.io/)

> The payment and password-recovery experiences are presentation-only demos. Do not enter real payment details or rely on this site for real transactions.

## Screenshot Gallery

All screenshots below were captured at a 1440×900 desktop layout using isolated demo data.

### Customer Experience

| Page | Preview |
| --- | --- |
| Homepage | ![JKC Cafe homepage](docs/screenshots/home.png) |
| Menu | ![JKC Cafe menu](docs/screenshots/menu.png) |
| Cart | ![JKC Cafe cart](docs/screenshots/cart.png) |
| Demo GCash checkout | ![JKC Cafe demo GCash checkout](docs/screenshots/checkout-gcash.png) |
| Rewards | ![JKC Cafe rewards](docs/screenshots/rewards.png) |
| Contact | ![JKC Cafe contact page](docs/screenshots/contact.png) |
| User profile | ![JKC Cafe demo user profile](docs/screenshots/profile.png) |

### Admin Workspace

| Page | Preview |
| --- | --- |
| Dashboard | ![JKC Cafe admin dashboard](docs/screenshots/admin-dashboard.png) |
| Manage products | ![JKC Cafe manage products](docs/screenshots/admin-products.png) |
| Manage orders | ![JKC Cafe manage orders](docs/screenshots/admin-orders.png) |
| Users | ![JKC Cafe admin users](docs/screenshots/admin-users.png) |
| Analytics | ![JKC Cafe admin analytics](docs/screenshots/admin-analytics.png) |
| Contact messages | ![JKC Cafe admin contact messages](docs/screenshots/admin-contact-messages.png) |
| Settings | ![JKC Cafe admin settings](docs/screenshots/admin-settings.png) |

## Features

### Customer experience

- Responsive homepage, menu, about, rewards, and contact pages
- Mobile-responsive customer experience across storefront, account, cart, and checkout pages
- Responsive UI verified across common viewport sizes from 320px to 1440px
- Product search, category filtering, cart management, and order summary
- Account registration, login, profile, settings, and order tracking
- Demo checkout flows for cash on delivery, card, and GCash
- Product availability reminders and in-app notifications

### Admin experience

- Dashboard metrics and recent activity overview
- Responsive admin dashboard for desktop, tablet, and mobile workflows
- Mobile sidebar navigation for touch devices
- Responsive admin tables and forms for products, orders, users, and analytics
- Product creation, editing, availability updates, and image uploads
- Order status management and customer-message review
- User administration, admin profile, and account settings

## Tech Stack

- PHP 8.2+
- MySQL 8+ or MariaDB 10.4+
- HTML, CSS, and vanilla JavaScript
- Font Awesome 6 for admin interface icons
- XAMPP for local Apache/MySQL development
- Playwright for browser smoke tests
- InfinityFree for live demo hosting

## 📚 Key Learnings

- Structuring PHP CRUD flows for products, orders, users, profiles, and messages
- Designing responsive customer and admin interfaces with shared and page-specific CSS
- Organizing modular CSS and vanilla JavaScript for navigation, filters, uploads, settings, and admin interactions
- Modeling relational data for users, products, orders, order items, notifications, and contact messages
- Using Playwright to smoke test browser flows and catch responsive regressions
- Debugging UI state issues around sticky navigation, drawer overlays, touch targets, and table/card layouts

## Local Setup

### Prerequisites

- XAMPP with Apache, PHP, and MySQL/MariaDB enabled
- Node.js 20+ and npm for Playwright tests

### 1. Clone and configure

```powershell
git clone https://github.com/JKCadilo14-CpE/cafe-website-demo.git
cd cafe-website-demo
Copy-Item config.example.php config.local.php
```

Update `config.local.php` with your local database name and credentials. This file is intentionally ignored by Git and must never be committed.

### 2. Create and import the database

Create an empty database named `jkc_cafe` (or choose another name and use it in `config.local.php`):

```sql
CREATE DATABASE jkc_cafe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the sanitized schema and safe catalogue seed data:

```powershell
mysql -u root -p jkc_cafe < database/schema.sql
```

### 3. Start the application

Place the project under XAMPP's `htdocs` directory, start Apache and MySQL from the XAMPP Control Panel, then open:

```text
http://localhost/Project/
```

The application creates ignored upload directories under `uploads/` when an image is uploaded. Ensure the web server can write to that directory in your local environment.

### 4. Create a local admin account

1. Create a regular account through the sign-up page.
2. In your local database, promote that account deliberately:

```sql
UPDATE users
SET role = 1
WHERE email = 'your-email@example.com';
```

Sign out and back in to load the admin role.

## Testing

Install the JavaScript test dependencies:

```powershell
npm ci
npx playwright install
```

With Apache, MySQL, and the local database configured, run the Chromium smoke tests:

```powershell
$env:BASE_URL = 'http://localhost/Project/'
npx playwright test --project=chromium
```

Run PHP syntax checks for all application files:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Deployment Notes

The live demo is hosted on InfinityFree. For a comparable deployment:

1. Create a MySQL/MariaDB database in the hosting dashboard.
2. Import `database/schema.sql` into that database.
3. Upload the application files, excluding `config.local.php`, `uploads/`, `node_modules/`, test reports, archives, and local backups.
4. Create `config.local.php` on the server from `config.example.php` and enter the host-provided credentials.
5. Confirm the server can write to `uploads/`.
6. Verify the customer pages, login/sign-up, demo checkout, and admin dashboard over HTTPS.

Never deploy real payment QR codes, credentials, customer records, password hashes, uploaded images, or database dumps.

## Repository Structure

```text
components/        Shared PHP application helpers and customer layout partials
admin pages/       Admin dashboard routes, styles, scripts, and partials
user-css/          Customer-facing stylesheets
user-js/           Customer-facing JavaScript
images/            Public visual assets
database/schema.sql Sanitized schema and safe product seed data
tests/             Playwright smoke tests
```

## 🏗️ Application Architecture

```mermaid
flowchart TD

Customer["Customer Browser"]
Admin["Admin Browser"]

Customer --> PHP["Apache + PHP"]
Admin --> PHP

PHP --> Auth["Authentication"]
PHP --> Store["Storefront"]
PHP --> Dashboard["Admin Dashboard"]
PHP --> Orders["Order Processing"]

Auth --> DB[(MySQL Database)]
Store --> DB
Dashboard --> DB
Orders --> DB

Dashboard --> Uploads[(Uploads Directory)]
```

## 🗺️ Roadmap

### ✅ Version 1.0.0 — First Public Portfolio Release

- [x] Customer-facing cafe website
- [x] User authentication and profile management
- [x] Shopping cart and demo checkout
- [x] Order tracking
- [x] Admin dashboard
- [x] Product management
- [x] Customer contact management
- [x] Desktop-responsive interface
- [x] Live demo deployment
- [x] Playwright smoke tests
- [x] Portfolio-ready documentation
- [x] MIT License
- [x] Sanitized database schema

---

### ✅ Version 1.0.1 — Responsive UI Improvements

- [x] Improve mobile responsiveness across all pages
- [x] Fix remaining layout and spacing inconsistencies
- [x] Improve checkout forms on small screens
- [x] Optimize admin pages for tablets
- [x] Improve touch targets and mobile usability
- [x] Test common screen sizes (320px–1440px)

Completed responsive improvements:

- Customer mobile navbar, avatar dropdown, sticky behavior, and scroll-lock fixes
- Hidden mobile nav click-target fix after closing the hamburger menu
- Mobile menu reveal animation trigger fix
- Checkout GCash QR/payment image containment fix
- Profile notification touch-target improvement
- Admin mobile/tablet sidebar toggle with backdrop and keyboard close behavior
- Admin tables, cards, forms, and action controls responsiveness improvements
- Final responsive regression test across 320px–1440px

---

### 🔒 Version 1.1.0 — Security & Stability

- [ ] Add CSRF protection
- [ ] Configure secure session cookies
- [ ] Add server-side password validation
- [ ] Add database constraints (e.g. UNIQUE email, foreign keys)
- [ ] Remove runtime schema creation and seeding
- [ ] Improve GitHub Actions CI
- [ ] Expand Playwright test coverage

---

### 💡 Future Improvements

- [ ] Email notifications
- [ ] Order analytics dashboard
- [ ] Advanced product search and filtering
- [ ] Customer reviews and ratings
- [ ] Performance optimization
- [ ] Accessibility improvements (WCAG)


## Author

Created by [JKCadilo14-CpE](https://github.com/JKCadilo14-CpE).

Licensed under the [MIT License](LICENSE).
