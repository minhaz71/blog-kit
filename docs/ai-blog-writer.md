# AI Blog Writer & Content Funnel Builder — Workflow & Feature Specification

Portable specification for re-implementation (e.g. as a WordPress plugin).
The blog agent **shares the product agent's engine** (same batch/item tables, same
review loop, same deterministic gate, same runner — see `ai-product-writer.md` §1–§4)
with blog-specific planning, prompts, scheduling, design system, and a research
front-end (the Funnel Builder).

---

## 1. Input modes (first match wins)

A blog batch (`kind = blog`) decides what to write from:

1. **CSV of article briefs** — one row per article. Canonical columns (+aliases):
   `title, keywords (first = primary), country, city, niche, details, angle, outline,
   publish_date, publish_time`. Unknown columns pass through as research context.
   Scheduling: `publish_date` only → publishes that day 00:00; + `publish_time` →
   exact minute; both empty → batch settings decide. A sample CSV ships in-product.
2. **Pasted title list** — one per line; writes exactly those (no planning tokens).
3. **Niche → AI topic cluster** — one LLM call designs 1 pillar + N spokes with
   per-topic primary/secondary keywords, angle, outline; deduplicated against every
   existing post; commercially anchored to real store products.
4. **Funnel waiting area** — items pre-built from researched ideas (see §3);
   the planner is skipped entirely.

### Ranking-conflict guard (all modes, including the owner's own titles)
Before any title becomes an item it must survive a similarity check
(Jaccard token overlap, stopwords dropped, light stemming, threshold 0.6) against:
existing post titles, parked funnel ideas, **published product names, and active
category names** — a blog post must never cannibalize an article or compete with a
money page. Drops are logged with the conflicting page, never silent.

---

## 2. Writing (deltas from the product engine)

- **Blog persona + rulebook**: direct-answer opening (2–3 sentences, the AI-quotable
  summary), question-form H2s each followed by a self-contained 40–60-word answer,
  short paragraphs, honest limitation note, topic discipline (siblings cover adjacent
  topics — link them, don't drift), 5–8 standalone FAQs, same banned-phrase /
  AI-vocabulary / no-dash rules, same uniqueness memory across the batch.
- **Adaptive length** (sent per item; cached system block untouched):
  cluster **pillar** 1800–2500 words (comprehensive, sectioned so spokes can link in),
  **spoke** 900–1500 (one intent, completely), everything else 700–1800 **sized to the
  topic** — narrow question answered fully at the low end beats padding.
- **Six blog structure variants** rotate by item id (scenario opener, surprising-fact
  opener, definition-first, mistakes-to-avoid, checklist-ending, etc.).
- **Output contract** adds `title` (final headline ≤ 70 chars) and an excerpt;
  meta_title must not equal the H1; meta_description targets 150–164.
  **Meta lengths never block** — clamped mechanically (title ≤ 63, description ≤ 164).
- **Design toolkit (bd-\* classes)** — the writer may use exactly eight classes,
  each styled by the site stylesheet: `bd-callout, bd-tip, bd-warning, bd-steps,
  bd-proscons, bd-verdict, bd-faq, bd-table-wrap`. The publisher **mechanically strips
  every other class and all id/style attributes** — the vocabulary is the whitelist.
  Plain tags are already fully styled by a tag-level design layer (`.bd-article`),
  so any classless article still looks designed.
- **Keyword rule**: primary keyword satisfied **directly or indirectly**
  (exact phrase, or ≥60 % of its meaningful words present, stemmed). Critics are
  instructed never to demand verbatim placement in a specific field.

## Never-lose guarantee (blog-specific)

With "hold articles that fail review" ON, an article the reviewer never approves is
still **saved as a draft post** labeled *needs review* — visible in the normal posts
list, linked from the batch item, publishable via one-click **Approve & publish**
(zero tokens) or by editing the draft. Content can never sit invisible inside the
pipeline.

---

## 3. Content Cluster & Funnel Builder (topic research)

Premise: on an ecommerce site, **products/categories are the bottom of the funnel**.
This subsystem researches them and generates TOP (educate/awareness) and MIDDLE
(compare/choose) funnel titles into a **waiting area**; nothing is written until the
admin selects and sends.

### Research run (`kind = blog_ideas` batch, background command)
1. **Store research + customer insight mining** — from published products,
   categories, prices + the store brief, an LLM builds a dossier: audiences,
   10–20 pain points, 15–30 real search queries, needs. (Improvement path:
   feed Search Console queries + on-site search logs here.)
2. **Cluster & funnel design** — 3–12 clusters, each with a middle-funnel pillar
   focus and 2–5 bottom-funnel target URLs chosen **only from the real link catalog**.
3. **Title generation** — chunked calls (~20/call), each idea carrying: title (≤70),
   cluster, role (pillar/spoke), funnel stage (top/middle), primary + secondary
   keywords, pain point, search query, audience need, angle, 4–7-section outline
   (the table-of-contents idea), 1–4 internal link targets from the catalog.
4. **Verification rounds (3–5, admin-set)** — per round:
   **deterministic gate first** (fingerprint dedupe; ranking-conflict similarity vs
   posts + ideas + products + categories; keyword present; funnel stage valid;
   link targets exist on the site; outline ≥ 3 sections; length ≤ 75) →
   **LLM editor pass** (real intent? right stage? overlaps a sibling? drop when
   uncertain) → regeneration call refills the deficit with "avoid these" context.
   Critic failure never blocks (deterministic survivors stand).
5. Survivors land in `blog_topic_ideas` (status `waiting`) with a normalized
   token-set **fingerprint** (unique) so no idea ever re-enters across runs.

### Waiting area (admin page)
Table filterable by cluster / funnel stage / status with badges; **bulk select-all,
multi-select, or single row** → *Send to writer* (choose category, provider/model,
publish mode, delay between articles); Edit / Dismiss / Regenerate per idea; a
"Generate ideas" action opens the research form (count default 100, rounds 3–5,
provider/model, optional niche, store brief).

### Send to writer
Creates a normal blog batch with **pre-built items** — each row carries the full
research brief (`funnel_stage, cluster, role, pain_point, search_query, audience_need,
outline, required_links, idea_id`). Extra row keys flow into the writer prompt
automatically. `required_links` must be woven in; funnel batches get an extra
FUNNEL RULES block (analyze the brief before writing; top = zero selling; middle =
help choose). Ideas flip `waiting → queued → written` (with `post_id`) as articles
publish. **Mark the idea `queued` BEFORE dispatching** the write job — on synchronous
runners the job finishes inside dispatch and would otherwise be overwritten.

---

## 4. Scheduling

- **Manual**: any post can be status `scheduled` with a future `published_at`
  (invisible on the frontend until due). A cron (`* * * * *`) flips due posts to
  `published`, firing the normal publish events (sitemap, cache purge, IndexNow).
- **Batch stagger**: "delay between articles" (1 hour … 1 year) on the batch —
  article N publishes at `batch_start + N × interval` (deterministic by item rank,
  so parallel workers agree). Row-level `publish_date/time` (CSV) overrides the
  interval. Draft mode ignores scheduling (drafts stay drafts).

---

## 5. Publishing (deltas)

Idempotent upsert on `item.post_id`: title, unique slug, body (link hygiene +
class whitelist), excerpt, category, author (batch creator → E-E-A-T author box),
reading time, SEO meta (verbatim meta title, description ≤ 164, focus keyword),
FAQs replaced wholesale, featured-image alt. Status/`published_at` from the
scheduling slot; held articles force `draft`. On publish, the linked waiting-area
idea is closed out.

---

## 6. WordPress porting notes

- Reuse the product engine port (batches/items/runner/lint) — the blog agent is
  ~85 % shared machinery.
- Scheduled posts: WP has native `future` status + `wp_publish_post` cron — map the
  stagger logic onto `post_date_gmt`.
- bd-\* classes → a small enqueue'd stylesheet + `wp_kses` allow-list as the
  mechanical class whitelist.
- Waiting area → custom table + list-table screen (or CPT `idea` with statuses);
  fingerprints in an indexed column.
- Similarity guard: pure PHP (tokenize → stopwords → stem → Jaccard) — port verbatim.
- Cluster/funnel prompts are provider-agnostic JSON-out calls; keep the
  "deterministic gate first, LLM critic advisory" order — it is the reliability core.
