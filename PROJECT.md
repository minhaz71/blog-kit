# Hemdox BlogKit — Project Guide & Changelog

> **Read this first.** This file is the single orientation point for any developer or AI agent
> picking up this codebase. It explains what the project is, how it's structured, the conventions
> to follow, and a dated changelog of significant work. Keep it up to date: when you make a
> substantial change, add a changelog entry at the top of the [Changelog](#changelog).

---

## 1. What this is

**Hemdox BlogKit** is a production-oriented Laravel **blog-first content platform** with a Filament
admin. It ships:

- **Blog** — posts, categories, tags, decoupled public authors, revision history (visual
  compare + restore), table of contents, reading-time.
- **CMS pages** and a **homepage section builder** (typed section partials) plus reusable
  **content blocks** (`{{block:key}}` shortcodes).
- **AI blog-writing pipeline** — `BlogPlanner` → `BlogWriter` → `BlogPublisher`, with a
  deterministic `ContentReviewer` QA gate and a multi-provider `LlmClient`
  (Anthropic / OpenAI / Google Gemini over HTTP, no SDKs).
- **AI thumbnail / image generator** — `ThumbnailService` + `ImageGenerator` targeting fal.ai
  FLUX (incl. low-cost FLUX schnell), OpenAI images and Gemini.
- **SEO / AEO suite** — `SchemaGenerator` (JSON-LD), XML sitemaps, `/llms.txt` + `/llms-full.txt`,
  `/.well-known/agents.json`, and markdown-for-agents (`/…​.md`).
- **Multisite hub/spoke publishing network** — plan, schedule and propagate content across a
  network of spoke sites, with per-site cost tracking.
- **Wordfence-style security layer**, **portable backups** with verified restore, and
  **LiteSpeed cache** integration.
- **Filament admin** at `/admin`.

### Optional ecommerce module (retained, ships DISABLED)

The full store — products, cart, checkout, orders, payments, shipping, tax — is **retained in the
codebase but disabled by default**. It is gated behind `ecommerce_enabled()`
(`module_enabled('ecommerce')`), driven by `BLOGKIT_ECOMMERCE_ENABLED` (default `false`, see
`config/blogkit.php`) and the **System → Modules** settings. Flip the flag / toggle the module to
enable the entire store (catalog, cart, checkout, payments, shipping/tax, the AI **Product**
Publisher, and product templates). Nothing is deleted; store routes, nav, settings and views are
simply guarded by `ecommerce_enabled()`.

### Core goals (guardrails — do not regress)

- **SEO/AEO first.** Every content surface emits correct schema.org JSON-LD; never fabricate
  reviews/ratings.
- **Security by default.** Firewall, login throttling, audit logging, CSRF, rate limiting.
- **Lightweight & cacheable.** Prefer server-rendered Blade + Alpine over heavy JS; keep pages
  LiteSpeed-cacheable.
- **Ecommerce stays gated.** When the store module is off, no store surface should render or route;
  when on, **server-side truth for money** (recompute in `CheckoutService`, never trust frontend
  totals) and **never store raw card data** (Stripe/PayPal, verified webhook signatures).

### Env vars & backward compatibility

`BLOGKIT_*` env vars are canonical (e.g. `BLOGKIT_ECOMMERCE_ENABLED`, `BLOGKIT_ALLOW_DESTRUCTIVE`).
Legacy `SHOPKIT_*` variants are still honoured as fallbacks, so existing deployments keep working.

---

## 2. Tech stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13.18.x, PHP 8.3 |
| Database | MySQL 9.x (SQLite in-memory for tests) |
| Admin | Filament v5.6 (+ spatie/laravel-permission v8 for RBAC) |
| Frontend | Blade + Alpine.js + Tailwind CSS v4 (Vite) |
| Search | Laravel Scout |
| PDF | barryvdh/laravel-dompdf (invoices — ecommerce) |
| Images | intervention/image (WebP) |
| AI providers | Anthropic / OpenAI / Google Gemini via HTTP (no SDKs); fal.ai for images |

Local URL: `http://127.0.0.1:8000`. Admin panel: `/admin`. Seeded super admin:
`admin@example.com` / `password`.

---

## 3. Architecture map

```
app/
  Console/Commands/       Artisan: blogkit:update, blogkit:preflight, ai:diagnose,
                          sitemap:generate, security:scan, cache:warm, schema:regenerate,
                          litespeed:purge, backup:*, security:* …
  Filament/
    Resources/            CRUD for every domain entity (Post, Category, Tag, Page, …;
                          + AiImportBatchResource; ecommerce resources gated by the module)
    Pages/                Settings pages (General, SEO, AI, Email, Security, Performance,
                          Appearance, Modules, System → Updates…) + AiUsageDashboard
    Widgets/              Dashboard widgets
    Support/              Shared form helpers (SeoForm, Editor, ResourceActions)
  Http/
    Controllers/          Blog/CMS controllers (Home, Post, Page, Author…) + gated store
                          controllers (Shop, Product, Cart, Checkout, Account)
    Middleware/           Firewall, HandleRedirects, LiteSpeedCache, EnforceTwoFactor…
  Jobs/                   AI batch jobs (blog + product writers)
  Models/                 Eloquent models (blog + CMS + system; ecommerce models retained)
    Concerns/             HasSlug, HasSeoMeta, HasFaqs, NormalizesJsonLists…
  Payments/               Retained ecommerce: PaymentGateway → AbstractGateway → drivers
  Policies/               One policy per model (ChecksPermission trait)
  Services/
    Ai/                   LlmClient, BlogPlanner, BlogWriter, BlogPublisher, ContentReviewer,
                          CategoryWriter, InternalLinker, ThumbnailService, ImageGenerator,
                          (retained) ProductWriter, ProductPublisher…
    Content/              ShortcodeParser ({{block:key}})
    Email/                EmailService (templated, queued)
    Performance/          LiteSpeedPurger, ImageOptimizer, SafeCache
    Security/             LoginSecurityService, MalwareScanner, TotpService (2FA)
    Seo/                  SeoManager, SchemaGenerator, SeoAnalyzer, SitemapGenerator,
                          LlmsTxtGenerator, AgentsJsonGenerator, MarkdownRenderer
    Checkout/ Cart/ Shipping/ Tax/   Retained ecommerce services (server-side totals, etc.)
  Support/helpers.php     Global helpers: setting(), ecommerce_enabled(), module_enabled(),
                          price_format(), parse_shortcodes(), safe_cache(), pb_block_style()

resources/views/
  layouts/app.blade.php         HTML shell (seo-head, header, footer, flash)
  partials/
    seo-head.blade.php          <title>, meta, robots, canonical, OG, Twitter, JSON-LD
    homepage/*                  Homepage section partials
    header/ footer/ flash…
  components/                   price, rating-stars, breadcrumbs, faq-section, pb-block…
  shop/ cart/ checkout/         Retained ecommerce views (rendered only when the module is on)
  filament/pages/               Custom Filament page views

database/
  migrations/             Domain migrations (blog, CMS, seo, security, system, homepage/content
                          blocks, AI tables; ecommerce: catalog/commerce/shipping/tax/payment)
  seeders/                RolePermission, Settings, EmailTemplate, Homepage, ContentBlock…
                          (DemoCatalog/TereaHub/TereaAttribute run only when ecommerce is enabled)
```

---

## 4. Key conventions

- **Settings**: `Setting::get('group.key')` / global `setting('group.key', $default)`. Per-group
  cached. Edited via Filament settings Pages.
- **Optional ecommerce is gated**: guard every store-only route, nav item, setting and view with
  `ecommerce_enabled()`. Blog/CMS/SEO/security features are always on.
- **URLs**: never hard-code `/post/…` or `/product/…`; generate via `App\Support\Permalinks`. Bases
  are configurable in Admin → SEO settings.
- **Polymorphic traits**: `HasSeoMeta`, `HasSlug` (auto-slug + slug-history redirects), `HasFaqs`.
  Applied to Post/Category/Page (and Product when the store is on).
- **Caching Eloquent models/collections**: **always** use `safe_cache()` / `SafeCache::remember()`,
  never raw `Cache::remember()` (file/database cache rehydrates cached models to
  `__PHP_Incomplete_Class` otherwise — see changelog 2026-07-07).
- **Schema.org**: generated by `SchemaGenerator`, assembled by `SeoManager::forX()`. Real
  reviews/ratings only — never fabricate.
- **AI writers**: blog pipeline is `BlogPlanner`/`BlogWriter`/`BlogPublisher`; the deterministic
  `ContentReviewer` is the final authority, never the LLM critic. Product writers are retained for
  the optional store module.
- **Payments** (ecommerce): implement `PaymentGateway`, extend `AbstractGateway`, register in
  `PaymentManager`. Webhooks verify provider signatures.
- **Policies**: extend the `ChecksPermission` trait; abilities map to flat `manage x` permissions.
  Super Admin bypasses via `Gate::before`.
- **Filament forms**: the panel ships Filament's precompiled CSS only — **custom Filament page Blade
  views must not rely on arbitrary Tailwind utility classes**; scope your own `<style>` block
  instead. Filament's own components (forms/tables) are fine.
- **Tests**: Feature tests under `tests/Feature`, `RefreshDatabase`. Run `php artisan test`.

---

## 5. AI blog-writing pipeline

Flow: **plan → write → multi-pass QA review → publish.**

- **`LlmClient`** — multi-provider (Anthropic/OpenAI/Gemini) HTTP client. Retry on 429/5xx,
  provider-specific error hints, usage logging, prompt caching (`cacheStatic`), health checks.
- **`BlogPlanner`** — turns a topic/brief into an outline and structure directives.
- **`BlogWriter`** — builds the batch-stable system prompt (site brief + writing rulebook +
  search-engine/AEO rulebook + linking rules + output contract) so providers serve it from prompt
  cache; per-item user prompt carries the plan plus a uniqueness digest for large runs.
- **`ContentReviewer`** — 1–4 QA passes; a deterministic zero-token lint (banned phrases, meta
  lengths, FAQ count, `<h1>`, internal-link checks) is fed into the LLM review. It is the final gate.
- **`BlogPublisher`** — creates the Post + SEO meta + FAQs, resolves the reserved slug, separates CSS.
- **`InternalLinker`** — verification only (no LLM cost): unwraps self-links and invalid URLs.

**AI thumbnail / image generator** — `ThumbnailService` orchestrates `ImageGenerator` to produce
post thumbnails and inline images via fal.ai FLUX (incl. low-cost FLUX schnell), OpenAI or Gemini,
from a richer subject/style prompt.

Models & cost: `AiImportBatch`, `AiImportItem`, `AiUsageLog` (per-model pricing → USD), plus the
**AiSettings** page (keys, default models, endpoint tester) and **AiUsageDashboard**. CLI:
`php artisan ai:diagnose [--live]`. Log channel: `storage/logs/ai.log`.

---

## 6. Optional ecommerce module (retained, disabled by default)

When enabled (`BLOGKIT_ECOMMERCE_ENABLED=true` / System → Modules), the store adds: catalog,
cart, server-side checkout (`CheckoutService`), orders, payments (COD/Bank/Stripe/PayPal via
`PaymentManager`), shipping/tax calculators, the AI **Product** Publisher, and the Elementor-style
**Product Template Builder** (block-based server-rendered single-product layouts). See
**[docs/AI-PUBLISHER.md](docs/AI-PUBLISHER.md)** and **[docs/ai-product-writer.md](docs/ai-product-writer.md)**
for the retained product pipeline. All of this is dormant until the module flag is on.

---

## 7. Local development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate      # if fresh
php artisan migrate --seed
npm run build            # or: npm run dev  (HMR)
php artisan serve        # http://127.0.0.1:8000
php artisan queue:work   # for AI batches
php artisan test         # full suite
```

Notes:
- After editing Blade with **new** Tailwind classes, run `npm run build` (or keep `npm run dev`).
- `php artisan cache:clear` after changing cached settings/templates if needed.
- Production self-update: `bash deploy.sh` (wraps `php artisan blogkit:update`); readiness gate:
  `php artisan blogkit:preflight`.

---

## Changelog

> Newest first. Add an entry for any substantial change. Dates are absolute.

### 2026-08-03

**Rebranded to Hemdox BlogKit; blog-first with ecommerce as an optional disabled module.**
Repositioned the project as a blog-first content platform (posts/categories/tags/authors/revisions,
CMS pages, homepage builder, AI blog pipeline, SEO/AEO, multisite network, security, backups). The
**ecommerce module is retained in the codebase but ships DISABLED** behind `ecommerce_enabled()` /
`BLOGKIT_ECOMMERCE_ENABLED` (System → Modules) — settings and frontend fully gated. Homepage
redesigned (teal editorial). AI thumbnail generator extended with low-cost models (fal.ai FLUX
schnell) + a richer prompt. Renamed the self-update/preflight commands
`shopkit:update`/`shopkit:preflight` → `blogkit:update`/`blogkit:preflight` (behaviour unchanged;
`SHOPKIT_*` env vars still honoured as fallbacks).

### 2026-07-08 (later still)

**Wordfence-style advanced security system.** Elevated the security module to a premium-grade,
multi-layer system (all self-hosted, free data sources) — full reference in
**[docs/SECURITY.md](docs/SECURITY.md)**:
- **Real-time threat-intelligence blocklist** (`ThreatIntelligence` + `threat_intel_ips`): daily
  `security:update-blocklist` pulls free public feeds (blocklist.de, FireHOL level-1); firewall
  blocks matches via O(1) cached lookup.
- **GeoIP + country blocking** (`GeoIp`, ip-api.com cached 30d): allow-list / deny-list wired into
  the firewall.
- **Dependency CVE monitoring** (`DependencyAudit`, `security:audit-dependencies` weekly): `composer
  audit` → advisories + alerts. Verified live (0 CVEs).
- **Intrusion alerts** (`SecurityAlertService` + `security_events`): severity-ranked events, email
  on high/critical (auto-ban, threat-IP, malware, CVE), throttled 1/type/10min.
- **Posture audit + score** (`SecurityAudit`): ~16 weighted checks → 0-100 + A–F grade with fixes.
- **Security Center dashboard** (`/admin/security-center`): score ring, blocked-attack stats, audit
  checklist, top IPs/attack-types, event feed, threat-feed + dependency status, one-click actions.
- Firewall extended; malware scan stamps run time + alerts; scheduler wired. Suite 163 passing.

### 2026-07-08 (later)

**Portable full-snapshot backup + verified import system.** Every backup archive embeds a
`manifest.json` (`BackupManifest`: PHP/Laravel/app/MySQL versions, required extensions,
ran-migrations list, row counts, APP_KEY fingerprint, SHA-256 of the dump). `backup:restore` gained
a **compatibility gate** (`BackupCompatibility`) that blocks incompatible restores BEFORE anything
is overwritten, then: safety-backup → DB restore → files restore → auto-run newer migrations →
cache clear → row-count verification. Admin Backups page gained **Import backup file** and per-row
**Check**. Round-trip verified live on MySQL; suite 152 passing. Docs: BACKUPS.md §2 rewritten.

### 2026-07-08

**Data-loss protection + backups.** `DatabaseSafetyGuard` (registered in `AppServiceProvider`)
intercepts every destructive Artisan command (`migrate:fresh`/`refresh`/`reset`/`rollback`,
`db:wipe`) and forces a DB backup first — aborting the command if the backup fails. New
`backup:restore` (GTID-safe) and `backup:prune` commands. Scheduler: daily DB backup, weekly full,
daily prune (30-day retention). Free off-machine cloud guide in **[docs/BACKUPS.md](docs/BACKUPS.md)**.
Verified: guard fires + backup/restore round-trips; 142 tests pass.

### 2026-07-07 (later)

**Hardening — full remediation of the 31-finding AI-pipeline audit** (6 high, 13 medium, 12 low) +
pipeline reorder: **images now download only after review approval and a successful publish**. Full
architecture reference at **[docs/AI-PUBLISHER.md](docs/AI-PUBLISHER.md)**. Suite: 142 passing.

### 2026-07-07

**Fix — template caching crash (`__PHP_Incomplete_Class`).** A cached Eloquent model rehydrated to
`__PHP_Incomplete_Class` on the file/database cache store, 500-ing pages. Fixed by switching to
`safe_cache()` plus `instanceof self` fallbacks. **Convention reminder added to §4: never cache
Eloquent models/collections with raw `Cache::remember()`.**

---

*Maintainers: keep §5/§6 and this changelog current. When you touch caching, re-read the §4 note on
`safe_cache`. When you touch store surfaces, keep them behind `ecommerce_enabled()`.*
