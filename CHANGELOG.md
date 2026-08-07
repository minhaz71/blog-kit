# Changelog

All notable changes to Hemdox BlogKit. Format based on
[Keep a Changelog](https://keepachangelog.com); versions follow the
version field in `version.json`.

## [Unreleased] - 2026-08-03

### Changed
- **Rebranded to Hemdox BlogKit** — a blog-first content platform. Ecommerce is
  now an **optional module that is retained in the codebase but ships DISABLED**,
  gated behind `BLOGKIT_ECOMMERCE_ENABLED` / System → Modules; flip it to enable
  the full store (products, cart, checkout, payments, shipping, tax).
- Homepage redesigned (teal editorial layout).
- AI thumbnail generator extended with low-cost models (fal.ai FLUX schnell) and
  a richer prompt.
- Ecommerce fully gated behind the module flag across settings and frontend.
- Self-update/preflight commands renamed `shopkit:update`/`shopkit:preflight` →
  `blogkit:update`/`blogkit:preflight`.

## [1.1.0] - 2026-07-11

### Added
- Version management: `version.json` single source of truth with per-tool
  component versions, an admin **System → Updates** page, and a safe
  `blogkit:update` command (mandatory pre-update backup, additive migration,
  automatic rollback on failure).
- `blogkit:preflight` production-readiness check.
- Post revision history with visual compare and restore.
- Public author identity decoupled from the login account (random author URLs).
- Emirate delivery landing pages, Google/Bing product feed, IndexNow bulk submit.

### Changed
- Destructive migrations (`migrate:fresh`, `db:wipe`, …) are now hard-blocked
  when `APP_ENV=production` unless `BLOGKIT_ALLOW_DESTRUCTIVE=1` is set
  (legacy `SHOPKIT_ALLOW_DESTRUCTIVE=1` still honoured).
- Custom code fields (HTML/CSS/JS) are now restricted to Super Admin only.

### Security
- Sensitive columns on User/Order/Review/Address protected from mass assignment.

## [1.0.0] - 2026-07-05

### Added
- Initial release (formerly ShopKit): blog, CMS pages, AI blog + product
  writers, SEO suite, security center, backups — plus the now-optional
  ecommerce module (catalog, cart, checkout, orders, payments), retained but
  disabled by default.
