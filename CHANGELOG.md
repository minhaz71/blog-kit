# Changelog

All notable changes to ShopKit (Terea Hub). Format based on
[Keep a Changelog](https://keepachangelog.com); versions follow the
`shopkit` field in `version.json`.

## [1.1.0] - 2026-07-11

### Added
- Version management: `version.json` single source of truth with per-tool
  component versions, an admin **System → Updates** page, and a safe
  `shopkit:update` command (mandatory pre-update backup, additive migration,
  automatic rollback on failure).
- `shopkit:preflight` production-readiness check.
- Post revision history with visual compare and restore.
- Public author identity decoupled from the login account (random author URLs).
- Emirate delivery landing pages, Google/Bing product feed, IndexNow bulk submit.

### Changed
- Destructive migrations (`migrate:fresh`, `db:wipe`, …) are now hard-blocked
  when `APP_ENV=production` unless `SHOPKIT_ALLOW_DESTRUCTIVE=1` is set.
- Custom code fields (HTML/CSS/JS) are now restricted to Super Admin only.

### Security
- Sensitive columns on User/Order/Review/Address protected from mass assignment.

## [1.0.0] - 2026-07-05

### Added
- Initial ShopKit release: catalog, cart, checkout, orders, payments, CMS
  pages, blog, AI product + blog writers, SEO suite, security center, backups.
