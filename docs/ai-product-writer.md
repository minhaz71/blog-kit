# AI Product Writer — Workflow & Feature Specification

Portable specification of the product content-writing agent, written for re-implementation
on any platform (e.g. a WordPress plugin). Describes behavior and contracts, not
framework code.

---

## 1. Concept

A batch pipeline that turns a CSV of product rows into fully written, reviewed,
SEO-complete, internally-linked, published products. Three principles drive every
design decision:

1. **The LLM never gates itself.** An LLM critic reviews and improves copy, but a
   deterministic rule engine (string/regex checks) makes the publish decision.
   LLM judges nitpick forever; machines converge.
2. **Prompts are cached.** The entire instruction set (rulebook, store brief, link
   catalog, output contract) is assembled once per batch and kept byte-identical
   across items, so provider prompt caching (Anthropic `cache_control`, OpenAI/Gemini
   prefix caching) charges the big block once. Per-item data is the only variable part.
3. **Content is never lost.** Every intermediate output is stored on the item row.
   Failures retry; rejected copy is held with its full text, never discarded.

---

## 2. Data model

### Batch (`ai_import_batches`)
| Field | Purpose |
|---|---|
| `kind` | `product` \| `blog` \| `blog_ideas` — one table serves all agents |
| `name`, `user_id` | Display + author attribution |
| `csv_path` | Uploaded CSV (product mode requires it) |
| `prompt` | The **store brief** — brand facts, selling unit, tone, audience, hard rules. Sent once per batch (cached) |
| `system_prompt` | Optional override of the default writer persona |
| `provider`, `model` | Writer LLM (anthropic / openai / gemini / deepseek…, model optional → provider default) |
| `reviewer_provider`, `reviewer_model` | The critic — a **different** provider by default (cross-review catches more) |
| `review_passes` | Max critique→rewrite cycles (1–4, default 3) |
| `publish_mode` | `publish` \| `draft` |
| `require_approval` | On: unapproved copy is **held** (product mode) / saved as draft (blog mode) |
| `output_format` | `html_css` (scoped classes + generated CSS) \| `html_plain` (semantic tags only) \| `html_classes` (user's own class list) |
| `allowed_tags` | Whitelist of HTML tags the writer may use |
| `competitor_count` | N > 0 adds a "beat the top-N competitors" positioning instruction |
| `target_country/city/language`, `audience_note` | Localization block |
| `link_scope`, `link_catalog` | Internal-link whitelist (see §7) |
| `total_items`, `done_items`, `failed_items`, `status` | Progress: `pending → processing → linking → completed / failed / paused` |

### Item (`ai_import_items`)
| Field | Purpose |
|---|---|
| `row` (JSON) | The parsed CSV row — every column reaches the writer |
| `ai_output` (JSON) | Latest full draft (survives crashes, powers "Approve & publish") |
| `status` | `pending → writing → reviewing → published / needs_review / failed` |
| `passes_done`, `open_issues`, `error` | QA telemetry |
| `reserved_slug` | Slug reserved **at plan time** so sibling items can link to each other before publishing |
| `product_id` | Created product (idempotency anchor: re-runs update, never duplicate) |

### Support tables
- `ai_activity_logs` — human-readable live feed (emoji-prefixed), auto-pruned.
- `ai_usage_logs` — per-call token + cost ledger (input/output/cached, priced per model).
- `ai_fix_prompts` — "learned fixes": reviewer instructions that worked are stored and
  replayed to future items in the same batch (self-improving batch).

---

## 3. Input: the CSV contract

- Delimiter sniffing (comma/semicolon/tab), UTF-8 BOM strip, empty-row skip,
  duplicate-name dedupe, price normalization → Excel/WooCommerce/Shopify/Sheets
  exports all import cleanly.
- Header aliases map many spellings to canonical keys
  (`keyword/target_keyword/focus_keyword → keywords`, etc.).
- Canonical columns: `name, regular_price, sale_price, short_description,
  specifications, brand, category, keywords`. **Unknown columns are not dropped** —
  they are passed to the writer as extra research context.
- `keywords`: comma-separated; **first = primary** (hard requirement, see §6),
  rest = secondary (guidance only).
- Images are NOT in the CSV. A Google Drive folder is set on the batch; each product
  is matched to the image whose **filename** best matches the product name
  (token overlap). Image permalinks = slugified original filename, fixed at upload.

---

## 4. Pipeline (state machine)

```
CSV upload → PARSE (once, re-entrancy-guarded)
  → items created (status pending, slug reserved)
  → link catalog built (cached, versioned)
FOR EACH ITEM (background process; queue fallback):
  claim item atomically (status+updated_at compare-and-swap → no double writing)
  WRITE    — cached system block + per-item prompt → JSON draft
  REVIEW   — up to N critique→rewrite cycles with the cross-provider critic
  GATE     — final rewrite for outstanding issues, then the DETERMINISTIC lint decides
  PUBLISH  — idempotent upsert: product + SEO meta + FAQs + images + price
  (hold path: require_approval + not approved → status needs_review, full draft kept)
FINALIZE (when last item ends):
  verify every internal link URL against items that actually went live
  unwrap links pointing at products that never published ("dead-link cleanup")
  write summary log; batch → completed
```

**Runner model:** no queue worker required. A detached background process
(`artisan ai:run-batch {id}` equivalent; in WP: WP-Cron event or `wp ai run-batch` WP-CLI
+ Action Scheduler) processes all items sequentially and survives page closes.
Pause/Stop flags are checked between items. Retry re-queues `failed`/`needs_review`
items without touching published siblings.

---

## 5. Prompt architecture

**Static per batch (cached):** writer persona → store brief → local targeting →
market positioning (competitor_count) → WRITING RULES (the rulebook) →
SEARCH ENGINE RULES → LINKING RULES + full catalog → format contract (tags/classes)
→ JSON output contract.

**Per item (small, variable):** compact row dump (empties dropped, values truncated),
batch-memory uniqueness digest (headings/openers/anchors already used by siblings),
learned fixes, TARGET KEYWORDS directive, STRUCTURE VARIATION directive
(8 rotating layout recipes selected by `item_id % 8`).

**Output contract (JSON only, one object):**
`short_description_html, description_html (400–800 words), css, suggested_price,
meta_title, meta_description, focus_keyword, image_alt, image_title, image_caption,
faqs[6–10]`.

### The rulebook (condensed)
- Section list of 20 blocks (intro, highlights, experience/flavor or device specs,
  spec table, FAB benefits, package contents, compatibility incl. explicit
  "not compatible with", how-to, best-for, who-should-NOT, sibling comparison,
  pricing, delivery, authenticity, ingredients/tech, safety note, where-to-buy,
  why-us, FAQ, summary) — writer picks 8–12 that fit, **never all, never the same
  subset/order**, headings rewritten in its own words per product.
- Machines-first opening sentence: `[product type] + differentiator + 1–2 specs`.
- Benefits before features; one idea per bullet; paragraphs ≤ 4 sentences.
- Banned filler phrases + banned AI-vocabulary list (delve, seamless, meticulously…).
- **No em/en dashes anywhere** (also enforced mechanically).
- No medical/health claims; no invented certifications/reviews/statistics.
- E-E-A-T: hands-on observations, honest limitations, correct terminology.
- Search-engine block: Google (people-first, entities over repetition),
  Bing (literal keyword in meta_title/H2/first paragraph; tables parse well),
  AI answer engines (question-form H2 + self-contained 40–60-word answer,
  definition sentence near the top, citable specifics, standalone FAQ answers).

---

## 6. Review loop + deterministic gate

1. **Lint before critique**: the deterministic findings are handed to the critic so
   it fixes real violations, not taste.
2. **Critic** (different provider) returns strict JSON:
   `{approved, issues[], summary}` — issues must be imperative, specific, ≤20 words,
   and only BLOCKING problems (facts, missing sections, rulebook violations).
   Style preferences are explicitly not issues.
3. Writer rewrites with the issue list (cached rulebook unchanged).
4. After the last cycle: **one final rewrite**, then mechanical fixes
   (meta clamps, em-dash strip), then the **lint verdict decides**.

### Deterministic lint rules (the actual gate)
| Rule | Behavior |
|---|---|
| Banned phrases / AI words | Blocking (word-boundary regex, "unlocked" ≠ "unlock") |
| Em/en dashes in any field | Blocking in lint; also auto-rewritten (`word — word` → `word, word`; `2—4` → `2-4`) as final guarantee |
| Meta lengths | **NEVER blocking.** Auto-clamped at word boundaries: title ≤ 63 (aim 50–60), description ≤ 164 (aim 150–164) |
| meta_title identical to H1 | Blocking (title tag must be a search snippet, not the headline) |
| Primary keyword | Blocking only if absent **directly AND indirectly** (indirect = ≥60 % of its meaningful words, stopwords dropped, light stemming so "cleaning" matches "clean") |
| FAQs < 5 | Blocking |
| `<h1>` in body | Blocking (page renders its own H1) |
| Internal link URLs not in catalog | Blocking in lint; also unwrapped at publish |
| Generic anchors ("click here") | Blocking |

**Hold semantics (products):** unapproved after the final gate → item `needs_review`
with full draft stored; UI offers **Re-run** (full cycle again, reserved slug kept) and
**Approve & publish** (publish stored draft as-is, zero tokens).

---

## 7. Internal linking (write-time)

- The **catalog is the whitelist**: products (60) + product categories + posts +
  blog categories + home page, name + live URL, built once per content version
  (cached; invalidated by any content save).
- Writer places 3–6 links inside sentences with keyword-bearing anchors; never link
  lists, never self-links, URLs copied verbatim.
- Publisher hygiene: internal URLs not on the whitelist are unwrapped to plain text;
  surviving internal links are made **root-relative** so copy survives domain moves.
- Finalize pass removes links to sibling items that ultimately failed to publish.

---

## 8. Publishing

Idempotent upsert keyed by `item.product_id`: name, slug (reserved), prices
(`suggested_price` respected within bounds), descriptions, brand/category
(created if missing — admin-controlled toggle), SEO meta row (meta title verbatim
as title tag, no site suffix), FAQs replaced wholesale (no stacking), featured +
gallery images from Drive matching with alt/title from the writer, price snapshot
rules, `publish` vs `draft` per batch. Publishing fires the site's standard events
(sitemap bump, cache flush, IndexNow ping).

---

## 9. Admin/UI features

- **Batch form** with cost preview per model ($/1M in-out-cached), sample CSV download.
- **Live Monitor**: auto-refreshing pipeline board — per-item status chips, QA pass
  count, activity feed (every write/review/publish/fail logged with emoji + level),
  Pause / Resume / Stop / "Parse now", per-item Retry / Re-run / Approve & publish,
  link to the created product.
- **Usage dashboard**: tokens + cost per batch/provider/purpose (write/review/plan).
- **Never-delete guarantee**: no pipeline path deletes live products.

---

## 10. WordPress porting notes

- Batch/items → two custom tables (don't abuse postmeta; the JSON columns matter).
- Runner → Action Scheduler (background loops, retries) or WP-CLI command spawned
  via `wp_remote_post` loopback; store Pause/Stop flags in options.
- Products → WooCommerce CRUD (`wc_get_product`, `WC_Product::set_*`);
  SEO meta → Yoast/RankMath meta keys; FAQs → theme/ACF repeater or block.
- Reserved slugs → pre-insert `post_name` on a draft shell post.
- Prompt cache: same principle — one static system string per batch, per-item suffix.
- The deterministic lint is plain PHP string/regex work — port it verbatim; it is
  the heart of the system.
