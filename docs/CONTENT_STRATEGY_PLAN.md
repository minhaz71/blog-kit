# Hemdox BlogKit — Content Strategy (Cluster + Funnel + Linking + Schema + Thumbnails)

**Audit + fix plan.** Everything here is pull-safe: all DB changes are *additive, nullable*
migrations (no drops, no data loss), so pulling on the live site cannot harm it.

> **STATUS: all 7 phases implemented** (branch `claude/blogkit-home-page-design-843738`).
> Run `php artisan blogkit:backfill-clusters` after deploy to stamp existing posts.
> Tune behaviour in **Admin → SEO → Content strategy**; view the plan in
> **Admin → Content → Content clusters**.

---

## TL;DR — what's the real state?

You are **not** missing a clustering/funnel engine. You have one (`FunnelPlanner` +
the "Content Cluster & Funnel Builder" admin page). The problem is it **stops at the
planning stage**: the cluster/funnel intelligence never reaches the published post, the
internal linker ignores it, the funnel has no bottom, and titles only get cluster context
in one of the two generation paths.

| Area | Status | One-line verdict |
|---|---|---|
| Cluster planning (pillar + spoke) | ✅ Exists | Real hub-and-spoke design in `FunnelPlanner::designClusters()` |
| Funnel planning | ⚠️ Partial | Only **top + middle**. No bottom/decision stage. |
| Title generation logic | ⚠️ Partial | Keyword-aware; cluster-aware **only for funnel batches**, never explicit |
| Cluster/funnel saved on the Post | ❌ Missing | Dropped at publish — `posts` has no cluster/funnel/role columns |
| Internal linking by cluster/funnel | ❌ Missing | Links by keyword coincidence; no pillar↔spoke, no funnel flow |
| Blog schema (JSON-LD) | ✅ Mostly | Auto BlogPosting/FAQ/Breadcrumb/ItemList. Missing HowTo + inline-FAQ |
| Thumbnail generation | ✅ Good | Cheap, clean prompt. Missing per-cluster look + OG size + brand color |
| Config/settings for all this | ❌ Missing | Cluster count, funnel mix, facets all hardcoded |

**Biggest issue for a vape blog with e-commerce OFF:** the funnel is built on the
assumption *"product/category pages are the bottom of the funnel."* With the store
disabled, MOFU/BOFU has **nowhere to send readers** — so the funnel currently dead-ends.
Fix #2 below addresses this directly.

---

## What already works (don't rebuild these)

- **`FunnelPlanner`** designs named clusters (pillar + spokes), generates verified,
  de-duplicated title ideas with `primary_keyword`, `secondary_keywords`, `outline`,
  `pain_point`, `search_query`, `link_targets` — and a cannibalization guard
  (`BlogTopicIdea::rankingConflict`) so new titles don't fight existing posts.
- **`blog_topic_ideas`** table already stores `cluster`, `role` (pillar/spoke),
  `funnel_stage` (top/middle), keywords, outline, link_targets.
- **Admin "Content Cluster & Funnel Builder"** (`BlogTopicIdeaResource`) — waiting area
  with pillar/spoke badges and cluster filters.
- **Writer** (`BlogWriter`) feeds cluster/role/funnel into the prompt for funnel batches;
  `role` drives article depth (pillar = long, spoke = focused); `FUNNEL_RULES` makes
  top-funnel non-salesy and middle-funnel route to next step.
- **Schema** is automatic at render (`SeoManager::forPost` → `SchemaGenerator`):
  BlogPosting/Article (admin-overridable type), FAQPage, BreadcrumbList, comparison
  ItemList, plus Organization/WebSite/Person/speakable.
- **Thumbnails** (`ThumbnailService` + `ImageGenerator`): one cheap call
  (FLUX schnell ≈ $0.003, or gpt-image-1 / Gemini / Imagen), prompt built from
  title + excerpt + category + a style preset, "no text/logo" negative, 16:9, SEO filename.

---

## The fixes (prioritized, phased)

### Phase 1 — Make cluster + funnel *survive publish* (foundation) 🔴 highest impact
Right now `BlogPublisher::createPost()` writes only `compared_product_ids`; cluster, role,
and funnel stage are lost. Nothing downstream (linking, pillar pages, reporting) can work
without this.

1. **Additive migration** on `posts` (all nullable, indexed):
   `cluster` (string), `content_role` (pillar|spoke), `funnel_stage` (top|middle|bottom),
   `pillar_post_id` (self-FK, nullable), `primary_keyword` (string).
2. **New `content_clusters` table** (canonical): `id, name, slug, pillar_post_id,
   description, thumbnail_style, brand_hint`. Stops cluster names from drifting across
   research runs and gives the pillar a home.
3. **Carry metadata at publish**: `BlogPublisher::createPost()` copies
   cluster/role/funnel_stage/primary_keyword from the item row; resolve/create the
   `content_clusters` row; set `pillar_post_id`.
4. **Backfill command** `php artisan blogkit:backfill-clusters` — populate existing posts
   from `blog_topic_ideas.post_id`. Safe/idempotent.

### Phase 2 — Give the funnel a *bottom* (works with the store OFF) 🔴
The funnel is truncated to top/middle because it assumes products = BOFU.

1. Add `bottom` (decision) to `funnel_stage` everywhere (`FunnelPlanner` validation +
   prompts, `FUNNEL_RULES`, badges).
2. **Blog-mode BOFU targets** when e-commerce is off: buying guides, "best X" roundups,
   the cluster's pillar page, newsletter signup, or affiliate links — not product pages.
   A `funnel.bottom_target` setting picks the destination (pillar | affiliate | newsletter | product).
3. Update `BlogWriter` `FUNNEL_RULES` with a bottom-stage block (comparison tables,
   clear recommendation, single strong CTA to the chosen target).

### Phase 3 — Cluster- & funnel-aware internal linking 🟠
Today `LinkSuggestionEngine` links by keyword coincidence only.

1. **Structural bonuses/guarantees** in `score()`:
   - spoke → its pillar (guaranteed suggestion), pillar → each of its spokes (hub links),
     siblings in the same cluster interlink.
   - funnel-forward bonus (top→middle→bottom), penalty for backward links.
2. **Cluster completeness report**: extend `InternalLinksReport` to flag spokes missing a
   link to their pillar, and pillars missing spoke links (orphan-in-cluster).
3. Optional: `rel`/`nofollow` control on `LinkApplier` (needed for affiliate BOFU links).

### Phase 4 — Cluster-aware titles + unify both generation paths 🟠
1. Add an explicit **title directive** naming the cluster + role + funnel stage so titles
   are shaped by their place in the map, not just keywords.
2. **Map `primary_keyword`/`secondary_keywords` → the `keywords` column** the writer reads,
   so `keywordDirective` actually fires for funnel batches.
3. **`BlogPlanner` niche/CSV path**: persist idea rows with cluster/role/funnel_stage so
   those clusters aren't ephemeral (today they live only in item JSON).

### Phase 5 — Schema completeness 🟡
1. **HowTo schema** from the `bd-steps` markup the writer already emits (add `howTo()` to
   `SchemaGenerator`, auto-select when steps present).
2. **Auto-derive FAQPage** from the inline `bd-faq` block when the `faqs` relation is empty.
3. Auto-pick `@type`: HowTo when steps present, else BlogPosting.

### Phase 6 — Settings surface (stop hardcoding) 🟡
New **"Content Strategy" settings** group:
- cluster count range, funnel-stage mix ratio (e.g. 40/40/20), similarity threshold
  (currently hardcoded `0.6`), min internal links per cluster member.
- **Configurable comparison facets** — currently hardcoded to
  `flavor-family / cooling-level / tobacco-strength` (`ComparisonPlanner`). *These happen
  to fit vape*, but should be editable per site.
- `funnel.bottom_target` (from Phase 2).

### Phase 7 — Thumbnail upgrades 🟢
1. **Per-cluster visual identity**: store `thumbnail_style` on `content_clusters` so every
   post in a cluster shares a look (recognizable on the catalogue).
2. **Prompt upgrade**: lead with the primary keyword's concrete subject noun + cluster
   theme; keep the no-text negative.
3. **Second render size** 1200×630 for OG/social alongside the 16:9 hero.
4. Optional brand-color hint injected from the active theme.

---

## Suggested build order & effort

| Phase | Depends on | Rough size | Why this order |
|---|---|---|---|
| 1. Persist cluster/funnel on Post | — | M | Unblocks everything else |
| 2. Funnel bottom (store-off aware) | 1 | M | Fixes the dead-end funnel for your vape blog |
| 4. Cluster-aware titles + unify paths | 1 | M | Better titles the moment you generate |
| 3. Cluster/funnel-aware linking | 1 | L | The SEO payoff (topical authority) |
| 6. Settings surface | 1,2 | S | Makes it reusable across sites |
| 5. Schema (HowTo + inline FAQ) | — | S | Independent, quick win |
| 7. Thumbnail upgrades | 1 | S | Polish |

All migrations additive + nullable → a `git pull` on puffandpod.com stays safe.

---

## Open questions before building
1. Keep e-commerce **off** for puffandpod (so BOFU routes to pillar/affiliate/newsletter),
   or will you enable the store for real product BOFU targets?
2. Affiliate links needed for BOFU? (drives the `rel="sponsored"` work in Phase 3.)
3. Build all phases, or start with 1 + 2 + 4 (the core "cluster plan actually drives titles
   and survives publish") and review before the linking rework?
