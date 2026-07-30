# ShopKit — Project Guide & Changelog

> **Read this first.** This file is the single orientation point for any developer or AI agent
> picking up this codebase. It explains what the project is, how it's structured, the conventions
> to follow, and a dated changelog of significant work. Keep it up to date: when you make a
> substantial change, add a changelog entry at the top of the [Changelog](#changelog).

---

## 1. What this is

**ShopKit** is a production-oriented Laravel ecommerce platform positioned as a faster, more secure,
SEO-optimized WooCommerce alternative. It ships a full storefront, a Filament admin panel, a Rank
Math-style SEO module, a Wordfence-style security layer, LiteSpeed cache integration, multiple
payment gateways, and an **AI Product Publisher** that writes SEO/AIO-optimized product copy from a
CSV in bulk.

### Core goals (guardrails — do not regress)
- **Server-side truth for money.** Never trust frontend prices/totals — always recompute in
  `CheckoutService`.
- **Never store raw card data.** Payments go through Stripe/PayPal; webhooks must verify signatures.
- **SEO/AIO first.** Every content surface emits correct schema.org JSON-LD; never fabricate
  reviews/ratings.
- **Security by default.** Firewall, login throttling, audit logging, CSRF, rate limiting on
  login/checkout/search/coupon.
- **Lightweight & cacheable.** Prefer server-rendered Blade + Alpine over heavy JS; keep pages
  LiteSpeed-cacheable.

---

## 2. Tech stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13.18.x, PHP 8.3 |
| Database | MySQL 9.x |
| Admin | Filament v5.6 (+ spatie/laravel-permission v8 for RBAC) |
| Storefront | Blade + Alpine.js + Tailwind CSS v4 (Vite) |
| Search | Laravel Scout |
| PDF | barryvdh/laravel-dompdf (invoices) |
| Images | intervention/image (WebP) |
| AI providers | Anthropic / OpenAI / Google Gemini via HTTP (no SDKs) |

Local URL: `http://127.0.0.1:8000`. Admin panel: `/admin`. Seeded super admin:
`admin@example.com` / `password`.

---

## 3. Architecture map

```
app/
  Console/Commands/       Artisan: ai:diagnose, sitemap:generate, security:scan, cache:warm,
                          abandoned-cart email, review requests, schema:regenerate, litespeed:purge…
  Filament/
    Resources/            CRUD for every domain entity (+ AiImportBatchResource, ProductTemplateResource)
    Pages/                Settings pages (General, SEO, AI, Email, Security, Performance, Appearance…)
                          + AiUsageDashboard (cost analytics)
    Widgets/              Dashboard widgets (SalesOverview, RecentOrders, SecurityOverview)
    Support/              Shared form helpers (SeoForm, Editor, ResourceActions)
  Http/
    Controllers/          Storefront controllers (Home, Shop, Product, Cart, Checkout, Account…)
    Middleware/           Firewall, HandleRedirects, LiteSpeedCache, EnforceTwoFactor, VerifyRecaptcha
    Requests/             Form requests (PlaceOrderRequest…)
  Jobs/                   StartAiImportBatch, WriteAiProduct, FinalizeAiImportBatch
  Models/                 ~50 Eloquent models
    Concerns/             HasSlug, HasSeoMeta, HasFaqs, MovesInlineStylesToCustomCss, NormalizesJsonLists
  Observers/              Product, Category, Post, ProductImage
  Payments/               PaymentGateway contract → AbstractGateway → COD/BankTransfer/Stripe/PayPal
    PaymentManager        Registry of gateway drivers (extend()-able)
  Policies/               One policy per model (ChecksPermission trait → flat "manage x" perms)
  Services/
    Ai/                   LlmClient, ProductWriter, ContentReviewer, ProductPublisher,
                          InternalLinker, DriveImageFetcher, SampleCsv
    Cart/                 CartService, CouponService
    Checkout/             CheckoutService (server-side totals, stock lock, idempotency)
    Content/              ShortcodeParser ({{block:key}})
    Email/                EmailService (templated, queued)
    Payments/             PaymentRuleService (per-gateway country/city/method rules)
    Performance/          LiteSpeedPurger, ImageOptimizer, SafeCache
    Security/             LoginSecurityService, MalwareScanner, TotpService (RFC 6238 2FA)
    Seo/                  SeoManager, SchemaGenerator, SeoAnalyzer, SitemapGenerator, SeoData
    Shipping/ Tax/        ShippingCalculator, TaxCalculator
  Support/helpers.php     Global helpers: setting(), price_format(), parse_shortcodes(),
                          safe_cache(), pb_block_style()

resources/views/
  layouts/app.blade.php         HTML shell (seo-head, header, footer, flash)
  partials/
    seo-head.blade.php          <title>, meta, robots, canonical, OG, Twitter, JSON-LD
    header/ footer/ flash…
    homepage/*                  Homepage section partials (14 types)
    product/*                   ⭐ Product template block partials (see §6)
    custom-code.blade.php       Per-entity custom CSS/HTML/JS
  components/
    price, rating-stars, breadcrumbs, faq-section, product-card, pb-block ⭐
  shop/product.blade.php        ⭐ Block-driven single product page renderer
  filament/pages/               Custom Filament page views (ai-usage-dashboard, ai-batch-monitor…)

database/
  migrations/             Domain migrations (catalog, customer, commerce, shipping/tax, cms, seo,
                          security, system, payment rules, homepage/content blocks, AI tables,
                          product_templates)
  seeders/                RolePermission, Settings, EmailTemplate, DemoCatalog, Homepage,
                          ContentBlock, ProductTemplate
```

---

## 4. Key conventions

- **Settings**: `Setting::get('group.key')` / global `setting('group.key', $default)`. Per-group
  cached. Edited via Filament settings Pages.
- **Polymorphic traits**: `HasSeoMeta` (morphOne SeoMeta), `HasSlug` (auto-slug + slug-history
  redirects), `HasFaqs` (morphMany Faq). Applied to Product/Category/Post/Page.
- **Caching Eloquent models/collections**: **always** use `safe_cache()` / `SafeCache::remember()`,
  never raw `Cache::remember()`. The file/database cache stores rehydrate cached models to
  `__PHP_Incomplete_Class` otherwise. (This bit us — see changelog 2026-07-07.)
- **Schema.org**: generated by `SchemaGenerator`, assembled by `SeoManager::forX()`. Real
  reviews/ratings only — never fabricate.
- **Payments**: implement `PaymentGateway`, extend `AbstractGateway`, register in `PaymentManager`.
  Webhooks verify provider signatures.
- **Policies**: extend the `ChecksPermission` trait; abilities map to flat `manage x` permissions.
  Super Admin bypasses via `Gate::before` in `AdminPanelProvider`.
- **Filament forms**: this panel ships Filament's precompiled CSS only — **custom Filament page
  Blade views must not rely on arbitrary Tailwind utility classes**; scope your own `<style>` block
  instead (the AI usage dashboard does this). Filament's own components (forms/tables) are fine.
- **Tests**: Feature tests under `tests/Feature`, `RefreshDatabase`. Run `php artisan test`.

---

## 5. AI Product Publisher

Bulk product copywriter. Flow: **CSV upload → parse → per item: write → multi-pass QA review →
publish → verify links → finalize batch.**

- **`LlmClient`** — multi-provider (Anthropic/OpenAI/Gemini) HTTP client. Retry on 429/5xx,
  provider-specific error hints, usage logging, prompt caching (`cacheStatic`), health checks.
- **`ProductWriter`** — builds the **byte-identical-per-batch** system prompt (so providers serve it
  from prompt cache): base persona + store brief + local targeting + market positioning + the
  **writing rulebook** + the **search-engine/AIO rulebook** + the **linking rules + live URL
  catalog** + output contract. Per-item user prompt carries the row + a rotating structure-variation
  directive + a **compacted "batch memory" digest** (headings/openers already used) for uniqueness on
  large runs.
- **`ContentReviewer`** — 1–4 QA passes; deterministic zero-token lint (banned phrases, meta
  lengths, FAQ count, `<h1>`, **internal-link checks**) fed into the LLM review.
- **`ProductPublisher`** — creates the Product + SEO meta + FAQs; uses the **slug reserved at parse
  time** so sibling links resolve; pricing per batch policy; CSS auto-separated to `custom_css`.
- **`InternalLinker`** — **verification only** (no LLM cost): `audit()` unwraps self-links and
  non-catalog URLs after publish; `unwrapUrls()` (finalize) strips links to products that never went
  live.
- **`DriveImageFetcher`** — pulls product images from a Google Drive folder (name match) or a
  per-row Drive/direct URL.

Models: `AiImportBatch`, `AiImportItem`, `AiUsageLog` (per-model pricing → USD cost),
`AiActivityLog` (human-readable event feed). Admin: **AiImportBatchResource** (form + live monitor
with pause/resume/stop + queue-worker fallback), **AiSettings** page (keys + default models +
endpoint tester), **AiUsageDashboard** (cost analytics). CLI: `php artisan ai:diagnose [--live]
[--batch=ID]`. Dedicated log channel: `storage/logs/ai.log`.

**Model dropdowns** are driven by `AiImportBatch::MODELS` (real IDs per provider, kept in sync with
`AiUsageLog::PRICES`); provider selection reactively repopulates the model list.

---

## 6. Product Template Builder (Elementor-style, lightweight)

A **block-based, server-rendered** single-product layout builder. No canvas JS — uses Filament's
`Builder` field; renders via Blade partials; LiteSpeed-cacheable.

- **`ProductTemplate`** model (`product_templates` table): `blocks` (ordered typed blocks),
  `settings` (schema toggles, container width, gallery image width), `is_default` (only one).
  `ProductTemplate::resolve($product)` → product override → default row → **code fallback**
  (`codeDefault()`), so pages always render. Resolution is `safe_cache()`d.
- **`products.product_template_id`** — optional per-product override (null → default).
- **Blocks** (23): breadcrumbs, gallery, title, rating, price, key_facts, short_description,
  variations, add_to_cart, categories, payment, delivery_info, description (tabbed), specifications,
  faq, reviews, related, cross_sells, upsells, heading, **html (drop anywhere)**, divider, spacer.
- **Placement**: each block chooses `left` / `right` (2-col hero) or `full` (stacked). Renderer:
  full blocks before first hero block render on top (breadcrumbs), hero grid in the middle, full
  blocks below.
- **Per-block style**: text/heading/background colour, font size, alignment, padding, custom class →
  `pb_block_style()` builds inline CSS; `<x-pb-block>` wraps each partial. Heading colour is exposed
  as `--pb-heading`.
- **Schema toggles** (template settings) control which JSON-LD `SeoManager::forProduct` emits
  (product, review, breadcrumb, faq, organization, website, localbusiness).
- **Admin**: `Admin → Content → Product templates`. Files: `app/Models/ProductTemplate.php`,
  `app/Filament/Resources/ProductTemplateResource.php`, `resources/views/shop/product.blade.php`,
  `resources/views/partials/product/*.blade.php`, `resources/views/components/pb-block.blade.php`,
  `database/seeders/ProductTemplateSeeder.php`.

---

## 7. Local development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate      # if fresh
php artisan migrate --seed
npm run build            # or: npm run dev  (HMR; picks up new Tailwind classes automatically)
php artisan serve        # http://127.0.0.1:8000
php artisan queue:work   # REQUIRED for AI batches (they queue jobs)
php artisan test         # full suite
```

Notes:
- After editing Blade with **new** Tailwind classes, run `npm run build` (or keep `npm run dev`
  running) or they won't be in the compiled CSS.
- After changing settings/templates cached values, `php artisan cache:clear` if needed (models
  self-heal via `safe_cache`).
- AI batches need a running `queue:work`; the live monitor also offers a "process next now"
  fallback when no worker is detected.

---

## Changelog

> Newest first. Add an entry for any substantial change. Dates are absolute.

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
  checklist, top IPs/attack-types, event feed, threat-feed + dependency status, one-click
  scan/update/audit/baseline actions.
- Firewall extended (threat-IP + country checks + auto-ban alerts); malware scan now stamps run
  time + alerts on findings; Security settings gained threat-intel/country/alert controls; scheduler
  wired. Tests: `AdvancedSecurityTest` (11). Suite 163 passing.

### 2026-07-08 (later)

**Portable full-snapshot backup + verified import system.** Every backup archive now embeds a
`manifest.json` (`BackupManifest`: PHP/Laravel/ShopKit/MySQL versions, required extensions,
ran-migrations list, row counts, APP_KEY fingerprint, SHA-256 of the dump) and full backups also
carry AI-import CSVs. `backup:restore` gained a **compatibility gate** (`BackupCompatibility`)
that blocks incompatible restores BEFORE anything is overwritten (corrupt archive, older PHP,
missing extensions, newer-ShopKit/unknown-migration backups, driver mismatch), then: safety-backup
→ DB restore (schema travels in the dump — **no migrations needed**) → files restore → auto-run
newer migrations → cache clear → row-count verification against the manifest. New
`config/shopkit.php` version constant. Admin Backups page gained **Import backup file** (upload →
check → restore) and per-row **Check**. Round-trip verified live on MySQL; suite 152 passing.
Tests: `BackupSystemTest` (10). Docs: BACKUPS.md §2 rewritten.

### 2026-07-08

**Data-loss protection + backups.** After a `migrate:fresh` wiped the dev DB, added a fail-safe:
`DatabaseSafetyGuard` (registered in `AppServiceProvider`) intercepts every destructive Artisan
command (`migrate:fresh`/`refresh`/`reset`/`rollback`, `db:wipe`) and forces a DB backup first —
**aborting the command if the backup fails**. New `backup:restore` (GTID-safe) and `backup:prune`
commands; `backup:run` dumps now use `--single-transaction --set-gtid-purged=OFF --no-tablespaces`
so they restore cleanly on the same server. Scheduler: daily DB backup, weekly full, daily prune
(30-day retention). Free off-machine cloud guide (rclone→Google Drive, +B2/Dropbox/R2) in
**[docs/BACKUPS.md](docs/BACKUPS.md)**. Verified: guard fires + backup/restore round-trips; 142 tests pass.

### 2026-07-07 (later)

**Hardening — full remediation of the 31-finding AI-pipeline audit** (6 high, 13 medium, 12 low) +
pipeline reorder: **images now download only after review approval and a successful publish**.
Full architecture reference added at **[docs/AI-PUBLISHER.md](docs/AI-PUBLISHER.md)** — read that
before touching the agent. Highlights:

- *High*: Drive downloads validated (Content-Type/bytes/size — HTML pages can never become product
  images, regression-tested); atomic compare-and-swap item claiming (worker + monitor button can't
  double-process); job timeout 1800s with 45-min reclaim window + `failed()` hook + scheduled
  `ai:sweep-stuck` (every 10 min); `needs_review` items got a **Re-run** action and finalize keeps
  links to them; banned-phrase lint is word-boundary matched; OpenAI o-series models use
  `max_completion_tokens`.
- *Medium*: publish is transactional + idempotent (`ProductPublisher`); post-publish steps
  (preview URL, link audit, image) are non-fatal; Gemini key moved to the `x-goog-api-key` header;
  truncation throws on all three providers (OpenAI `length`, Gemini `MAX_TOKENS`); Anthropic cache
  writes billed at 1.25× (`ai_usage_logs.cache_write_tokens`); dashboard cache-savings uses a
  GROUP-BY aggregate; CSV header-alias collisions warn instead of silently dropping data;
  cross-batch slug reservation race closed; parse job is re-entrancy guarded; worker-stall banner
  needs two signals; `ai:diagnose` checks reviewer keys per batch; `money()` parses `29,99` and
  `1.299,00` correctly.
- *Low*: link audit covers `short_description` + single-quoted hrefs; `image_title`/`image_caption`
  stored on `product_images` (migration `2026_07_07_100001`); finalize reports held items honestly;
  duplicate-finalize race closed; dead `?preview=1` param removed; digest normalization no longer
  swallows text between apostrophes; parse fopen leak fixed; dead `listModels()` removed; unpriced
  models flagged on the dashboard (+ `o3-mini` priced, boundary-aware price matching);
  ConnectionException retries honoring `Retry-After`; `ai_activity_logs`/`ai_fix_prompts` are
  Prunable with scheduled `model:prune`.
- Suite: 142 passing.

### 2026-07-07

**Fix — Product template caching crash (`__PHP_Incomplete_Class`).**
`ProductTemplate::default()`/`resolve()` cached the Eloquent model with raw `Cache::remember()`;
the database/file cache store rehydrated it to `__PHP_Incomplete_Class`, breaking the
`: ProductTemplate` return type and 500-ing every product page. Fixed by switching to `safe_cache()`
(the codebase's guard for exactly this) plus `instanceof self` fallbacks to `codeDefault()`. Cleared
the poisoned cache entry. Verified product pages return 200. **Convention reminder added to §4: never
cache Eloquent models/collections with raw `Cache::remember()`.**

### 2026-07-06 (into 07-07)

**Feature — Product Template Builder (Elementor-style, lightweight).** New block-based, server-rendered
single-product layout builder. See §6. Added:
- Migration `product_templates` + `products.product_template_id`.
- `ProductTemplate` model (block registry, cached resolver, code fallback, single-default enforcement).
- `ProductTemplateResource` (Filament `Builder` with 23 typed blocks; per-block style fieldset with
  colour pickers, font size, placement; global settings incl. schema toggles + gallery image width).
- Rewrote `resources/views/shop/product.blade.php` as a block renderer (full-above-hero → 2-col hero
  → full-below); 23 partials in `resources/views/partials/product/`; `<x-pb-block>` wrapper +
  `pb_block_style()` helper.
- `SeoManager::forProduct` + `SchemaGenerator::product($product, $includeReviews)` now honour the
  template's schema toggles.
- `ProductTemplateSeeder` (registered in `DatabaseSeeder`); default layout mirrors the reference
  IQOS TEREA product page (gallery + summary + 3 coloured delivery boxes + tabbed description + FAQ +
  reviews + related).
- Tests: `ProductTemplateTest` (7) + smoke URLs for the new resource.

**Feature — AI Publisher: contextual internal linking (single-send, cached).** Reworked so the AI
places links **contextually while writing** instead of a post-hoc end-of-copy block:
- Slugs are **reserved at CSV-parse time** (`ai_import_items.reserved_slug`); a **link catalog**
  (batch products' live URLs + newest store products, capped 120) is snapshotted onto the batch
  (`ai_import_batches.link_catalog`) and injected into the **cached system prompt once per batch** —
  no per-product token cost.
- `LINKING_RULES` instruct in-context links (comparisons/compatibility/alternatives), descriptive
  anchors, never self-link, never invent URLs, no end-of-copy dump.
- `InternalLinker` rewritten to **verification only**: `audit()` (after publish) unwraps self/invalid
  links; `FinalizeAiImportBatch` unwraps links to products that never went live. Removed the old
  staged `LINK_AFTER` / "You may also like" append.
- Migration `2026_07_06_170001_add_contextual_linking_columns`.

**Feature — AI Publisher: bulk uniqueness via compacted batch memory.** `ProductWriter::uniquenessDigest()`
gives later items a terse digest of headings/openers already used in the batch (not full replays), so
~20+ product runs stay unique for a few hundred extra tokens each.

**Feature — AI Publisher: search-engine + AIO rulebook.** Added `ProductWriter::SEARCH_ENGINE_RULES`
(cached, per batch) covering Google (Helpful Content, E-E-A-T, PageRank via descriptive internal
links), Bing guidelines, AI answer engines/AIO (question-form H2s + self-contained 40–60 word
answers, citable specifics, definition sentences, standalone FAQ answers), and UGC-style authenticity
(honest trade-offs, never fabricated social proof). Reviewer lint extended with internal-link checks.

**Redesign — AI usage & cost dashboard.** Rebuilt `resources/views/filament/pages/ai-usage-dashboard.blade.php`
with a **self-contained scoped `<style>` block** (the panel ships only Filament's precompiled CSS, so
Tailwind utility classes weren't applying). Added gradient stat cards (Today / 7d / All-time / Cache
savings), a **cost-per-product** table (tokens + USD + Edit link), cost-by-model with share bars, and
header actions (New batch, All batches, AI settings, **Export CSV**). Added
`AiImportBatch::usageLogs()` relation.

**Redesign — AI Product Publisher form + accurate model dropdowns.** Sectioned the batch form with
icons/colours; the **Model** field is now a searchable dropdown scoped to the selected provider
(`AiImportBatch::MODELS`, synced with `AiUsageLog::PRICES`) that reactively repopulates and shows live
per-model pricing. Applied the same model dropdowns to the AI settings page. Batch list table gained
provider badges, a Model column, and a live **Cost (USD)** column. Tests: `AiBatchFormDesignTest`.

**Test status after the day's work:** 133 passing (`php artisan test`).

---

*Maintainers: keep §6/§5 and this changelog current. When you touch caching, re-read the §4 note on
`safe_cache`.*
