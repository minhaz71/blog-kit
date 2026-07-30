# AI Product Publisher — Developer Guide

> Complete reference for the bulk AI product-writing agent: architecture, every class and model,
> the pipeline step by step, statuses, settings, security, and how to customize or debug it.
> Read [PROJECT.md](../PROJECT.md) first for overall project orientation.

---

## 1. What it does

Upload a CSV of products → the agent writes SEO/AIO-optimized copy for each row with one LLM
("writer"), has a second cheap LLM ("reviewer") critique it, loops write→review until approved,
publishes the product (draft or live), verifies internal links, and downloads the product image —
**image download happens only after the copy passed review and the product exists.**

```
CSV upload (Filament form)
   │
   ▼
StartAiImportBatch (queued job, re-entrancy safe)
   ├─ smart CSV parse (delimiter sniff, BOM, header aliases, dedupe, price normalize)
   ├─ reserve a unique slug per row  ──►  live URL known BEFORE writing
   ├─ build link catalog (batch URLs + newest store products, ≤120)
   └─ dispatch WriteAiProduct per item (sequential via queue)
        │
        ▼
WriteAiProduct (one item)                       ← atomic claim, no double-processing
   ├─ 1. WRITE    ProductWriter (writer LLM, cached system prompt)
   ├─ 2. REVIEW   ReviewCycle: CrossReviewer critiques → ProductWriter.rewrite → repeat
   │              (deterministic ContentReviewer::lint findings are always blocking)
   ├─ 3. GATE     not approved + require_approval → status=needs_review, STOP (no product, no image)
   ├─ 4. PUBLISH  ProductPublisher.publish — DB transaction, idempotent
   ├─ 5. EXTRAS   (non-fatal) preview URL → InternalLinker.audit → attachImage
   └─ 6. maybe dispatch FinalizeAiImportBatch
        │
        ▼
FinalizeAiImportBatch (once, atomic entry)
   ├─ unwrap links to URLs that never went live (needs_review URLs are KEPT)
   └─ batch → completed (+ honest summary incl. failed/held counts)

ai:sweep-stuck (scheduler, every 10 min)
   └─ re-queues items abandoned mid-write; finalizes batches that never completed
```

---

## 2. File map

| Layer | File | Responsibility |
|---|---|---|
| Job | `app/Jobs/StartAiImportBatch.php` | Parse CSV, reserve slugs, build link catalog, dispatch items |
| Job | `app/Jobs/WriteAiProduct.php` | Per-item pipeline (claim → write → review → gate → publish → extras) |
| Job | `app/Jobs/FinalizeAiImportBatch.php` | Batch completion pass: dead-link cleanup + summary |
| Service | `app/Services/Ai/LlmClient.php` | Multi-provider HTTP client (Anthropic/OpenAI/Gemini) |
| Service | `app/Services/Ai/ProductWriter.php` | Writer prompts: rulebooks, catalog, batch memory, rewrite |
| Service | `app/Services/Ai/CrossReviewer.php` | Reviewer LLM: compact critique JSON (never rewrites) |
| Service | `app/Services/Ai/ReviewCycle.php` | Orchestrates write↔review loop; learns recurring fixes |
| Service | `app/Services/Ai/ContentReviewer.php` | Deterministic zero-token lint (+ legacy single-model review) |
| Service | `app/Services/Ai/ProductPublisher.php` | Transactional product creation + attachImage() |
| Service | `app/Services/Ai/DriveImageFetcher.php` | Validated image download (Drive folder / link / URL) |
| Service | `app/Services/Ai/InternalLinker.php` | Link verification: audit() + unwrapUrls() |
| Service | `app/Services/Ai/SampleCsv.php` | Downloadable sample import file |
| Model | `app/Models/AiImportBatch.php` | Batch config + MODELS registry + modelOptions() |
| Model | `app/Models/AiImportItem.php` | One CSV row: status, reserved_slug, ai_output, preview_url |
| Model | `app/Models/AiUsageLog.php` | Token accounting + PRICES + cache-write billing |
| Model | `app/Models/AiActivityLog.php` | Live monitor feed (prunable, 30 days) |
| Model | `app/Models/AiFixPrompt.php` | Saved reviewer fixes; batch-scope learning digest (prunable) |
| Admin | `app/Filament/Resources/AiImportBatchResource.php` | Batch CRUD + reactive model dropdowns |
| Admin | `…/Pages/MonitorAiImportBatch.php` + `resources/views/filament/pages/ai-batch-monitor.blade.php` | Live monitor (pause/resume/stop, parse-now, process-now) |
| Admin | `app/Filament/Pages/AiSettings.php` | API keys, default models, extra models, Drive key |
| Admin | `app/Filament/Pages/AiUsageDashboard.php` + blade | Cost analytics (per product / model / batch) |
| CLI | `app/Console/Commands/AiDiagnose.php` | `ai:diagnose [--live] [--batch=ID]` health report |
| CLI | `app/Console/Commands/AiSweepStuck.php` | `ai:sweep-stuck` — scheduled safety net |
| Log | `config/logging.php` → `ai` channel | `storage/logs/ai.log` (daily, 14 days) |

Migrations: `2026_07_06_130001` (batches/items) · `140001` (output options) · `150001` (usage logs +
targeting) · `160001` (activity logs) · `170001_add_contextual_linking_columns` (reserved_slug +
link_catalog) · `170001_add_cross_review_workflow` (reviewer seat, require_approval, preview_url) ·
`2026_07_07_100001_ai_pipeline_hardening` (image title/caption + cache_write_tokens).

---

## 3. The pipeline, step by step

### Step 0 — Batch creation (admin form)
`AiImportBatchResource::form()`. Key fields on `ai_import_batches`:

| Field | Meaning |
|---|---|
| `csv_path`, `prompt` | The file + the store brief (tone, market, SEO angle) |
| `provider` / `model` | Writer seat (dropdown from `AiImportBatch::modelOptions()`) |
| `reviewer_provider` / `reviewer_model` | Reviewer seat — default `openai` / gpt-4o-mini |
| `review_passes` (1-4) | Max write↔review cycles per product |
| `require_approval` | true → unapproved copy is HELD (`needs_review`), never published |
| `publish_mode` | `draft` or `publish` |
| `price_mode` | `csv` (as-is) or `ai` (AI may adjust the sale price) |
| `output_format` | `html_css` (scoped `pd-*` CSS) / `html_plain` / `html_classes` |
| `system_prompt`, `competitor_count`, `allowed_tags`, `custom_classes` | Writing style controls |
| `target_country/city/language`, `audience_note` | Local SEO targeting |
| `drive_folder` | Google Drive folder for image search (per-row `image_link` wins) |

### Step 1 — Parse (`StartAiImportBatch`)
1. **Re-entrancy guard**: bails if the batch already has items (Parse-now button + queued job can
   both fire).
2. **Smart parse**: delimiter sniffing (`,` `;` tab), UTF-8 BOM strip, header normalization +
   aliases (`Title→name`, `Price→regular_price`, `Image→image_link`…). **Alias collisions are
   detected** — if the file has both `Title` and `Name`, the alias is skipped with a warning instead
   of silently overwriting. Empty rows and duplicate names are skipped (logged). Prices are
   normalized (`AED 1,299.00` → `1299.00`).
3. Minimum **2 rows** (internal linking needs siblings).
4. **Slug reservation**: every row gets a unique `reserved_slug` checked against existing products
   AND other unfinished batches' reservations — so the product's live URL is known before writing.
5. **Link catalog**: batch products (name + reserved URL) + newest published store products, capped
   at 120, snapshotted to `batch.link_catalog`. Sent to the AI **once per batch** inside the cached
   system prompt — never re-sent per product.
6. Dispatches one `WriteAiProduct` per item.

### Step 2 — Claim (`WriteAiProduct::handle`)
Concurrency-safe **compare-and-swap**: `UPDATE ai_import_items SET status='writing' WHERE id=? AND
status=<read status> AND updated_at=<read timestamp>`. Zero rows affected → another runner (queue
worker vs monitor button) won; this one exits. Claimable states: `pending`, `failed`, or
`writing`/`reviewing` older than `RECLAIM_MINUTES` (45 — deliberately **above** the job `timeout`
of 1800s so a live run is never reclaimed). A hard kill triggers the job's `failed()` hook, which
marks the item failed and keeps counters honest.

### Step 3 — Write (`ProductWriter::write`)
One LLM call. The **system prompt is byte-identical for every item in the batch** (this is the
prompt-cache invariant — Anthropic `cache_control`, OpenAI/Gemini automatic prefix caches). It is
assembled from: persona (`system_prompt` → global setting → `DEFAULT_SYSTEM`) + store brief + local
targeting + market positioning + `WRITING_RULES` (structure, FAB, banned phrases, templates,
uniqueness) + `SEARCH_ENGINE_RULES` (Google Helpful Content/E-E-A-T/PageRank, Bing, AI answer
engines, UGC authenticity) + `LINKING_RULES` + the link catalog + format contract + JSON output
contract.

The **per-item user prompt** carries: the compacted row (internal columns like `image_link` are
excluded), a **batch-memory digest** (headings/openers already used → uniqueness on 20+ product
runs), **learned fixes** (recurring reviewer complaints from this batch), and a rotating structure
directive. Expected output keys: `short_description_html, description_html, css, suggested_price,
meta_title, meta_description, focus_keyword, image_alt, image_title, image_caption, faqs[]`.

### Step 4 — Review loop (`ReviewCycle::run`)
Per pass: `ContentReviewer::lint()` (deterministic, zero tokens: **word-boundary** banned-phrase
matching, meta lengths, FAQ count, `<h1>`, link checks over description AND short description with
both quote styles) → `CrossReviewer::critique()` (compact JSON verdict; lint findings are always
blocking) → if rejected, `ProductWriter::rewrite()` fixes the issues → repeat, up to
`review_passes`. Reviewer infrastructure failures degrade to lint-only (never kill a good draft).
Same provider+model on both seats → **combined mode** (critique + correction in one call). Every
critique is saved to `ai_fix_prompts`; recurring issues roll into a batch digest fed back to the
writer (**the batch gets cheaper and cleaner as it progresses**).

### Step 5 — Gate
Approved → continue. Not approved + `require_approval` → `status='needs_review'`, **no product, no
image**. Held items are re-runnable from the batch's Items table (**Re-run** action) after you edit
the copy/settings; their reserved URL stays valid, so sibling links to them are kept.

### Step 6 — Publish (`ProductPublisher::publish`)
**Idempotent** (an item that already has `product_id` returns the existing product — retries can't
duplicate) and **transactional** (product + FAQs + SEO meta land together or not at all). Uses the
reserved slug (re-suffixes only on genuine collision, logged). Prices parse locale-safely
(`29,99` → 29.99, `1.299,00` → 1299.00). `<style>` blocks are auto-moved to `custom_css`.

### Step 7 — Post-publish extras (non-fatal, each logged)
The product is live/draft at this point; nothing here may fail it:
1. **Preview URL** stored on the item (drafts are visible to logged-in admins only).
2. **Link audit** (`InternalLinker::audit`): every `<a>` the AI placed is verified against the
   catalog — self-links and invented URLs unwrap to plain text.
3. **Image** (`ProductPublisher::attachImage` → `DriveImageFetcher`): per-row `image_link` wins,
   else the batch Drive folder is searched by name similarity. Downloads are **validated**:
   Content-Type must be `image/*`, bytes must decode as a real image, ≤20 MB — Google's HTML
   "confirmation page" for big files can never become a product image. Stored slug-named with
   `alt`, `title`, `caption` from the AI output.

### Step 8 — Finalize (`FinalizeAiImportBatch`)
Atomic entry (two last-items finishing together dispatch it twice; only one runs). Collects catalog
URLs that never went live — failed items or slug changes, **excluding** `needs_review` items — and
unwraps those links from every published sibling. Completion log + `batch.error` honestly report
failed/held counts.

### Item status lifecycle
```
pending → writing → reviewing → published            (happy path; 'linked' = legacy alias)
                    ↘ needs_review  (held; Re-run action → pending)
any step ↘ failed                  (Retry action → pending)
writing/reviewing older than 45 min → reclaimable (sweeper or next dispatch)
```
Batch statuses: `pending → processing → linking → completed`, plus `paused`/`stopped`/`failed`.

---

## 4. LLM infrastructure (`LlmClient`)

- **Providers**: `anthropic` (Messages API), `openai` (Chat Completions), `gemini`
  (generateContent). Keys live in settings (`ai.{provider}_api_key`), sent **in headers only**
  (Gemini uses `x-goog-api-key` — never a URL query param, so keys can't leak into logs/errors).
- **o-series handling**: models matching `/^o\d/` (o3, o3-mini, o4-mini…) get
  `max_completion_tokens` (doubled, to cover hidden reasoning tokens) instead of `max_tokens`.
- **Truncation/refusal safety**: all three providers throw on cut-off output (Anthropic
  `stop_reason: max_tokens`, OpenAI `finish_reason: length|content_filter`, Gemini
  `finishReason: MAX_TOKENS|SAFETY|RECITATION`, block reasons) — partial JSON is never returned.
- **Retries**: HTTP 429/5xx/529 AND connection errors retry up to 3× with backoff; a `Retry-After`
  header is honored (capped 30s). Sleeps are skipped under unit tests.
- **Usage recording**: every call logs provider/model/purpose/tokens to `ai_usage_logs`. Anthropic
  cache **reads** bill at the cache rate, cache **writes** at 1.25× input
  (`AiUsageLog::CACHE_WRITE_MULTIPLIER`) — dashboard costs match the invoice.
- **Model pricing**: `AiUsageLog::PRICES` ($/1M input, output, cached-input), matched
  boundary-aware, longest key first (`o3-mini` beats `o3`). Unpriced models show an **"unpriced"**
  badge on the dashboard instead of silently costing $0.
- **Diagnostics**: everything logs to the `ai` channel; Anthropic request-ids are always kept.

---

## 5. Admin & operations

- **Live monitor** (`/admin/ai-import-batches/{id}/monitor`): status, progress, spend, per-item
  pipeline, live activity feed, Pause/Resume/Stop, "Parse CSV now" (re-entrancy safe), "Process next
  product now" (safe next to a worker thanks to the atomic claim). The "no worker" banner needs two
  signals: no recent item activity AND unclaimed jobs — a worker busy on one long item won't
  false-alarm.
- **Items table**: Retry (failed) / **Re-run** (needs_review) per item.
- **Cost dashboard**: per-product, per-model (share bars, unpriced badge), recent requests, cache
  savings (computed via GROUP-BY aggregate — constant memory), CSV export.
- **`php artisan ai:diagnose [--live] [--batch=ID]`**: keys (writer **and reviewer** per batch),
  endpoint pings, queue health, batch deep-dive. Report saved to `storage/app/`.
- **`php artisan ai:sweep-stuck`** (scheduled every 10 min): re-queues abandoned items, finalizes
  stuck batches.
- **Pruning** (scheduled daily): `ai_activity_logs` > 30 days; item-scope `ai_fix_prompts` > 60
  days (batch digests kept).
- **A queue worker is required**: `php artisan queue:work` (Supervisor/systemd in production).

---

## 6. How to customize (recipes)

| Want to… | Change |
|---|---|
| Add a new LLM provider | `LlmClient`: add to `defaultModel()` + a `protected function {provider}()`; add to `AiImportBatch::PROVIDERS` + `MODELS`; add prices to `AiUsageLog::PRICES`; add key fields in `AiSettings` |
| Add a model that just shipped | Settings → AI settings → "Extra models" (one `model-id \| Label` per line) — no deploy. Add a `PRICES` row for correct costing |
| Change the writing rules | `ProductWriter::WRITING_RULES` / `SEARCH_ENGINE_RULES` / `LINKING_RULES` (all cached per batch — edits don't multiply token cost) |
| Change what the reviewer rejects | `CrossReviewer::CRITIC_SYSTEM` (LLM judgment) and/or `ContentReviewer::lint()` + `BANNED_PHRASES` (deterministic, always blocking) |
| Support more CSV column names | `$aliases` in `StartAiImportBatch::handle()` |
| Change publish defaults (stock, visibility, type) | `ProductPublisher::publish()` create array |
| Change image rules (size cap, formats) | `DriveImageFetcher::MAX_BYTES` / `store()` |
| Adjust batch memory / learning size | `ProductWriter::uniquenessDigest()` limits; `ReviewCycle::refreshBatchDigest()` caps |
| Tune stuck-item handling | `WriteAiProduct::$timeout` + `RECLAIM_MINUTES` (**reclaim must stay > timeout**) |
| Change catalog size | `StartAiImportBatch::CATALOG_LIMIT` |

**Invariants — do not break:**
1. `ProductWriter::systemFor($batch)` must stay **byte-identical across items** of one batch
   (anything per-item goes in the user prompt) — this is the whole token-cost model.
2. Publishing must stay idempotent + transactional; everything after publish must be non-fatal.
3. Images only after review approval + successful publish.
4. Deterministic lint findings are always blocking (a lenient LLM reviewer can't wave them through).
5. Never fabricate reviews/ratings/social proof (Google policy + `SEARCH_ENGINE_RULES`).
6. API keys in headers only; provider error text is surfaced, keys are never echoed.

---

## 7. Testing

Feature tests (all under `tests/Feature/`): `AiProductPublisherTest` (end-to-end + audit/finalize),
`MultiAgentReviewTest` (review loop, gate, combined mode), `AiWriterPromptTest` +
`AiWritingRulesTest` (prompt contracts, lint), `AiContextualLinkingTest` (slug reservation, catalog,
link lint), `AiUsageTrackingTest` (token/cost math), `AiBatchControlsTest` (pause/stop/resume,
error hints), `AiDiagnosticsTest`, `AiSampleCsvTest` (image validation incl. the HTML-page
rejection), `AiActivityLogTest`, `ClaudeApiComplianceTest`, `AiBatchFormDesignTest`. Provider HTTP
is always faked (`Http::fake`) — tests never hit real APIs. Run: `php artisan test --filter=Ai`.
