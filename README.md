# JKC Cafe

JKC Cafe is a PHP and MySQL cafe ordering demo with user-facing menu, cart, checkout, profile, order tracking, and admin management pages.

## Database Configuration

`config.local.php` is ignored by Git and must never be committed.

`config.example.php` is the safe template included in this repository. It does not contain private credentials.

For local XAMPP development:

1. Copy `config.example.php`.
2. Rename the copy to `config.local.php`.
3. Enter your local database credentials in `config.local.php`.

For InfinityFree deployment:

1. Upload or create `config.local.php` manually on the server.
2. Enter your InfinityFree database credentials in `config.local.php`.
3. Keep in mind that `config.local.php` is not included from GitHub because it is ignored.

The application will not connect to the database without `config.local.php`.

Never upload real passwords, database credentials, or private configuration files to GitHub.

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
