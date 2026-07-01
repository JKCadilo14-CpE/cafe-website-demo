# Changelog

All notable changes to this portfolio project are documented here.

This changelog complements the README roadmap and GitHub Releases. Dates are intentionally left blank unless they are already published in release metadata.

## [Unreleased]

Planned production-readiness work:

- [ ] Improve database integrity with stronger constraints
- [ ] Add foreign keys where appropriate
- [ ] Harden upload validation
- [ ] Add a Content Security Policy compatibility pass
- [ ] Improve CI coverage and GitHub Actions workflows

## [v1.1.0] - Security & Authentication Hardening

Added and improved:

- CSRF protection across state-changing POST actions
- Hardened session configuration and session cookie settings
- POST-only logout with CSRF validation
- Shared password policy and server-side password validation
- Session-based rate limiting for sensitive forms
- Admin authorization refresh from the database
- Transactional admin role/delete safeguards
- Expanded Playwright security and authentication regression coverage

Notes:

- This release strengthens the demo application's security baseline.
- Remaining production-hardening work is tracked under Unreleased.

## [v1.0.1] - Responsive UI Improvements

Added and improved:

- Customer mobile responsiveness across storefront and account pages
- Admin responsive redesign for tablet and mobile workflows
- Mobile admin sidebar drawer with backdrop behavior
- Responsive admin tables, cards, forms, and action controls
- Checkout layout improvements on smaller screens
- Touch target improvements for mobile interactions
- Responsive regression testing across common viewport sizes

## [v1.0.0] - First Public Portfolio Release

Added:

- Customer storefront for browsing cafe products
- User authentication and profile management
- Shopping cart
- Demo checkout flows
- Admin dashboard
- Product management
- Customer contact management
- Live demo deployment
- Playwright smoke tests
- MIT License
