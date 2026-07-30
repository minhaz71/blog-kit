# AGENTS.md

Guidance for AI coding agents (and humans) working in this repository.

## What this is

**Terea Hub** — a Laravel 13 + Filament v5.6 e-commerce store for UAE IQOS / TEREA heated-tobacco products. Storefront (Blade) + admin panel (Filament) + an AI content pipeline that writes product/category/blog copy.

## Stack

- PHP 8.3, Laravel 13, Filament v5.6 (admin at `/admin`)
- MySQL (SQLite in-memory for tests), Blade + Tailwind (Vite), `spatie/laravel-permission` (guard `web`)
- Queue: `database` in prod/dev, `sync` in tests. AI batches run **inline** via `php artisan ai:run-batch` (detached `BackgroundProcess`), not a queue worker.

## Commands

- Tests: `php artisan test` (single file: `php artisan test --filter=SomeTest`)
- Lint a file: `php -l path/to/File.php`
- Clear caches after view/route/setting-driven changes: `php artisan view:clear`, `php artisan route:clear`

## Conventions (important)

- **URLs**: never hard-code `/product/…`; generate via `App\Support\Permalinks` (`Permalinks::product($slug)` etc.). Product/category/blog bases are configurable in Admin → SEO settings.
- **Custom Filament pages** are NOT covered by the panel's compiled Tailwind — each ships its own scoped `<style>` block with semantic classes + `.dark` overrides + brand teal `#0f766e` (see `resources/views/filament/pages/*.blade.php`).
- **Admin access**: every screen maps to a permission in `App\Support\AdminAccess`; resources/pages use the `GatedByPermission` trait.
- **Copy style**: no em-dashes in generated store content; keep facts (specs, prices, compatibility) accurate. AI writers live in `app/Services/Ai/` (`ProductWriter`, `BlogWriter`, `CategoryWriter`); the deterministic gate (`ContentReviewer`) is the final authority, never the LLM critic.
- **SEO/AEO surfaces**: `LlmsTxtGenerator` (`/llms.txt`, `/llms-full.txt`), `MarkdownRenderer` + `/…​.md` (markdown for agents), `AgentsJsonGenerator` (`/.well-known/agents.json`), `SchemaGenerator` (JSON-LD).

## Testing expectations

- Add/adjust feature tests under `tests/Feature/` for behaviour changes; keep `php artisan test` green.
- Don't edit source while a background full-suite run is in flight (Blade/route caches can compile mid-edit and cause false failures).

## Safety

- This store sells nicotine products to adults only; keep age/nicotine disclaimers intact in generated content and templates.
