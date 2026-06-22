# GitHub Upload Checklist

Use this checklist before publishing the project. Do not upload local runtime data, private demo records, or generated dependency folders.

## Remove Before Upload

- `node_modules/`
- `uploads/`
- `sess_*`
- `.agents/`
- `.qodo/`
- Any `*.log`, `*.tmp`, `*.temp`, `*.bak`, `*.old`, `*.copy`, `*.orig`, `*.zip`
- Placeholder or external-only tests that do not test this project, such as `tests/example.spec.js`, unless replaced with real project tests.

## Keep Local Only

- `.env` and `.env.*`
- Raw database dumps, especially `*.sql` files with users, orders, contact messages, phone numbers, addresses, emails, or password hashes.
- Uploaded profile pictures and product images from `uploads/`.
- Local session files named `sess_*`.
- Local agent/tooling folders such as `.agents/` and `.qodo/`.

## Privacy And Security Risks

- `config.local.php` contains local/private database credentials and must never be committed.
- Do not publish real admin/user emails, phone numbers, addresses, recovery contacts, password hashes, order records, or contact messages.
- Confirm any public contact phone/email in `contact.php` is intentionally public demo information.
- Confirm admin pages are protected by login/admin checks before showing a deployed demo.

## Database Configuration

- `config.local.php` is ignored by Git and must never be committed.
- `config.example.php` is the safe template included in the repository.
- For local XAMPP development:
  - Copy `config.example.php`.
  - Rename it to `config.local.php`.
  - Enter local database credentials.
- For InfinityFree deployment:
  - Upload or create `config.local.php` manually on the server.
  - Enter InfinityFree database credentials.
  - The file is not included from GitHub because it is ignored.
- The application will not connect to the database without `config.local.php`.
- Never upload real passwords, database credentials, or private configuration files to GitHub.

## Database Cleanup

- Create a schema-only SQL file for GitHub if you want others to install the project.
- If demo data is included, make it fake and clearly labeled as demo data.
- Remove or anonymize rows from:
  - `users`
  - `orders`
  - `order_items`
  - `contact_messages`
  - `user_notifications`
  - `product_reminders`
- Use fake admin credentials only, and document them in the README if you choose to provide them.

## Sanitized SQL Reminder

- Do not commit raw exports from phpMyAdmin or MySQL until reviewed.
- Ensure sanitized SQL contains no real:
  - emails
  - phone numbers
  - addresses
  - payment details
  - password hashes from real accounts
  - uploaded file paths tied to real users

## QR Code Warning

- `own qr code/QR-Code.jpg` is referenced by the GCash checkout page.
- If this QR code points to a real payment account, replace it with a dummy/demo QR before publishing.
- The checkout UI says payments are demo-only, so the QR image should also be demo-only.

## README Suggestions

- Project overview and purpose.
- Tech stack: PHP, MySQL/MariaDB, CSS, JavaScript, Playwright.
- Required local setup, including XAMPP/PHP/MySQL steps.
- Database setup instructions using sanitized schema/demo data.
- Demo-only disclaimers for checkout and forgot password.
- Folder structure.
- User features: menu, cart, checkout demo, profile, order tracking.
- Admin features: products, orders, users, messages, analytics.
- Test instructions.
- Deployment notes and files intentionally ignored by Git.

## InfinityFree Deployment Checklist

- Create MySQL database in InfinityFree.
- Import database schema.
- Upload project files.
- Create `config.local.php`.
- Update database credentials.
- Test login.
- Test signup.
- Test profile.
- Test checkout.
- Test admin dashboard.

## Final Manual Testing Checklist

- Home page loads.
- Menu page loads and filters/categories work.
- Signup works with demo data.
- Login works for a demo user.
- Forgot password demo shows the same success message for existing and missing accounts.
- Cart add/update/remove works.
- Checkout demo flow works for COD, card, and GCash.
- Order success/tracking page loads for owned orders only.
- Profile order history links open the correct order details.
- Settings profile image, recovery contact, username, password, and delete account flows behave as expected.
- Admin login redirects to admin dashboard.
- Admin product add/edit/delete works with demo products.
- Admin orders/messages/users pages load.
- Mobile responsive check at 375px, 480px, 768px, 1024px, and 1440px.
- Browser console has no unexpected errors.
