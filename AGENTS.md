# AGENTS.md

Guidance for AI coding agents (and humans) working in this repository.

## What this is

**Hemdox BlogKit** — a Laravel 13 + Filament v5.6 **blog-first content platform**: posts, categories, tags, authors, revisions, CMS pages, a homepage section builder, reusable content blocks, an AI blog-writing pipeline, an AI thumbnail/image generator, a full SEO/AEO suite, a multisite hub/spoke publishing network, Wordfence-style security, backups and LiteSpeed cache. Filament admin at `/admin`; public storefront/blog rendered with Blade.

**Ecommerce is an optional module.** The full store (products, cart, checkout, payments, shipping, tax) is **retained in the codebase but ships DISABLED**. It is gated behind `ecommerce_enabled()` / `module_enabled('ecommerce')`, driven by `BLOGKIT_ECOMMERCE_ENABLED` (default `false`, see `config/blogkit.php`) and the System → Modules settings. Do not delete ecommerce code — flip the flag to enable it.

## Stack

- PHP 8.3, Laravel 13, Filament v5.6 (admin at `/admin`)
- MySQL (SQLite in-memory for tests), Blade + Tailwind (Vite), `spatie/laravel-permission` (guard `web`)
- Queue: `database` in prod/dev, `sync` in tests. AI batches run **inline** via `php artisan ai:run-batch` (detached `BackgroundProcess`), not a queue worker.

## Commands

- Tests: `php artisan test` (single file: `php artisan test --filter=SomeTest`)
- Lint a file: `php -l path/to/File.php`
- Clear caches after view/route/setting-driven changes: `php artisan view:clear`, `php artisan route:clear`
- Safe self-update / preflight: `php artisan blogkit:update`, `php artisan blogkit:preflight`

## Conventions (important)

- **URLs**: never hard-code `/post/…` or `/product/…`; generate via `App\Support\Permalinks`. Blog/product/category bases are configurable in Admin → SEO settings.
- **Optional ecommerce is gated**: any store-only route, nav item, setting or view must be guarded by `ecommerce_enabled()`. Blog-first features are always on.
- **Custom Filament pages** are NOT covered by the panel's compiled Tailwind — each ships its own scoped `<style>` block with semantic classes + `.dark` overrides + brand teal `#0f766e` (see `resources/views/filament/pages/*.blade.php`).
- **Admin access**: every screen maps to a permission in `App\Support\AdminAccess`; resources/pages use the `GatedByPermission` trait.
- **Copy style**: no em-dashes in generated content; keep facts accurate. AI writers live in `app/Services/Ai/`: blog pipeline (`BlogPlanner`, `BlogWriter`, `BlogPublisher`) plus `CategoryWriter` and the retained ecommerce `ProductWriter`/`ProductPublisher`. The deterministic gate (`ContentReviewer`) is the final authority, never the LLM critic. `LlmClient` is the multi-provider HTTP client.
- **SEO/AEO surfaces**: `LlmsTxtGenerator` (`/llms.txt`, `/llms-full.txt`), `MarkdownRenderer` + `/…​.md` (markdown for agents), `AgentsJsonGenerator` (`/.well-known/agents.json`), `SchemaGenerator` (JSON-LD).

## Testing expectations

- Add/adjust feature tests under `tests/Feature/` for behaviour changes; keep `php artisan test` green.
- Don't edit source while a background full-suite run is in flight (Blade/route caches can compile mid-edit and cause false failures).
