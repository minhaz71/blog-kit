# Internal Linking Agent — Workflow & Feature Specification (as built)

Portable specification (e.g. for a WordPress plugin). A Link-Whisper-style internal
linking system that is **100 % deterministic — no AI, no API, no per-link cost** —
and **suggest-only**: it never modifies content until a human clicks Apply.
(Supersedes the original build plan; this documents the shipped behavior.)

---

## 1. Concept & guarantees

- **Suggest-only.** The agent proposes links on product/post edit screens and a
  dashboard; an admin applies or dismisses each one. No auto-apply path exists.
- **No AI.** Matching is lexical: phrase decomposition + token-set matching.
  (Known limit: it cannot see synonyms/context — pair it with an AI writer that
  places links at write time; this agent is the follow-up layer for older content.)
- **Duplicate-link protection.** If the source already links to a target —
  with ANY anchor text — no new suggestion for that source→target pair is ever made
  (duplicate links to the same URL dilute anchors and look spammy).
- **Byte-offset safety.** All text arithmetic uses byte offsets (`stripos`/`strlen`,
  never `mb_*`), and the SAME DOM helper computes offsets for both the engine and
  the applier — so an apply can never land inside a UTF-8 character or drift from
  where the suggestion was found.

---

## 2. Data model

### `link_targets` — the phrase dictionary
| Field | Purpose |
|---|---|
| `target_type`, `target_id` | The linkable page (product / post / category / blog category), polymorphic |
| `url`, `title` | Live URL + display title |
| `kind` | `phrase` (ordered n-gram) or `set` (sorted token set) |
| `phrase` | The matchable text, normalized lowercase |
| `weight` | Phrase quality score (longer/original phrases score higher) |
| index | composite (`kind`,`phrase`) — phrase and set entries can share a string, so the kind is part of the key |

### Phrase sources per target (fallback chain — no field required)
1. Product name / post title (always exists)
2. SEO title, cleaned (strip "Buy", price/CTA words, site suffix)
3. Slug, dehyphenated
4. Focus + secondary keywords + CSV keywords (indexed verbatim when present)
5. Post H2/H3 headings + tags (posts only)

### `link_suggestions` — the queue
| Field | Purpose |
|---|---|
| `source_type`, `source_id` | The page that would receive the link |
| `target_type`, `target_id`, `target_url` | Where it would point |
| `anchor_text` | The exact existing words in the source that become the anchor |
| `occurrence` | Which occurrence of that text (1st, 2nd…) — disambiguates repeats |
| `score` | 0–100 relevance (see §4) |
| `reason` | Human-readable why ("matches 3 of 4 title words + context overlap") |
| `status` | `pending → applied / dismissed` |
| `fingerprint` | `md5(source|target|anchor|occurrence)` UNIQUE — a dismissed suggestion never returns |

---

## 3. Dictionary build (per target page)

1. Normalize: lowercase, strip punctuation, collapse whitespace.
2. Drop **filler tokens** (brand-generic + stopwords, e.g. `iqos, buy, uae, the,
   for, with…`) to get the meaningful token set.
3. Generate **ordered n-grams** (`kind=phrase`): contiguous sub-phrases of the
   meaningful title, ≥ 2 tokens ("terea amber kazakhstan" → "terea amber",
   "amber kazakhstan", "terea amber kazakhstan").
4. Generate **token subsets** (`kind=set`, bitmask enumeration): unordered
   combinations of ≥ 2 meaningful tokens, stored SORTED — this lets
   "kazakhstan terea amber" in a sentence match the page "TEREA Amber Kazakhstan"
   regardless of word order.
5. **Document-frequency filter**: a phrase shared by **> 3 targets** is dropped
   (too generic to link anywhere); shared by 2–3 → kept but *ambiguous*, needing a
   context vote to win (§4).
6. Weight: longer phrases and full-title matches outrank fragments.

Rebuild triggers: content saved (incremental, that target only) + weekly full
rebuild cron (drift safety).

---

## 4. Suggestion engine (per source page)

Runs on content save (published pages only) and from the dashboard scan action.

1. **Extract linkable text** via the DOM helper (§5): visible text nodes only,
   each with its byte offset in the raw HTML.
2. **Pass 1 — ordered phrases**: `stripos` search of every dictionary phrase;
   longest match wins on overlap.
3. **Pass 2 — token sets**: per sentence window, build the sorted meaningful-token
   set and look up `kind=set` entries — catches reordered mentions.
4. **Ambiguity resolution**: a phrase mapping to 2–3 targets is decided by
   *context votes* — other meaningful tokens from each candidate's title found
   elsewhere in the source text (pure function over a local `seen` set — no shared
   state between candidates). Highest vote wins; ties drop.
5. **Occurrence arithmetic**: the Nth occurrence of the anchor is recorded so
   apply-time finds the exact spot even when the text repeats.
6. **Blocking rules** (before a suggestion is written):
   - source == target (self-link) → skip
   - source already links to the target's URL, ANY anchor → skip (duplicate guard,
     fed by the link tracker, §8)
   - anchor inside a skip-ancestor (existing `<a>`, h1–h3, `<th>`, buttons,
     script/style/code/pre) → skip
   - fingerprint exists (pending/applied/dismissed) → skip
7. **Scoring** (0–100, floor 30): phrase weight + match length + context votes +
   position bonus (earlier in the content scores higher).
8. **Caps**: max 5 pending suggestions per source (top-scored kept).
9. Saving a source **purges its stale pending suggestions** and regenerates —
   byte offsets are only valid against current content.

---

## 5. The DOM helper (shared by engine AND applier)

One class owns all HTML text arithmetic:

- Walks the DOM returning `[text, byte_offset, node]` for visible text nodes.
- `SKIP_ANCESTORS = a, h1, h2, h3, th, button, script, style, code, pre` — text
  inside these is invisible to matching and untouchable when applying.
- `findOccurrence(html, needle, n)` → byte range of the Nth occurrence using the
  same normalization as the engine.
- Apply = splice `<a href="URL">…</a>` at that byte range only — the rest of the
  document is byte-identical (no full-DOM re-serialization; the author's exact
  markup survives).

---

## 6. Apply / dismiss flow

**Apply (per suggestion, explicit click):**
1. Re-verify the anchor still exists at the recorded occurrence (content may have
   changed) — otherwise mark stale and drop.
2. Re-check the duplicate guard (an editor may have added the link manually) —
   if it now exists, dismiss with reason "already links".
3. **Mark the suggestion `applied` BEFORE saving the content.** The save fires the
   content observer, which purges *pending* suggestions for that source — updating
   after the save would purge the very row being applied. Revert to `pending` if
   the save fails. (Hard-won ordering bug — port this exactly.)
4. Splice via the DOM helper; save; the link tracker re-scans and now counts the
   new link, which blocks future duplicate suggestions automatically.

**Dismiss:** status `dismissed`; the fingerprint permanently suppresses recurrence.

**Undo:** applied suggestions retain source/URL/anchor/occurrence — unwrap the
exact `<a>` and revert the row to `pending`.

---

## 7. Surfaces

- **Edit-screen widget** (product + post editors): pending suggestions for THIS
  page — anchor in context, target, score, reason; Apply / Dismiss; content
  reloads after apply so the editor sees the live link.
- **Dashboard**: totals (pending/applied/dismissed), per-source grouping, bulk
  scan, orphan hints (pages with zero inbound links are prioritized as targets).
- **Cron**: weekly dictionary rebuild + site-wide suggestion refresh
  (suggest-only — nothing ever auto-applies).

---

## 8. Companion: the link TRACKER (separate subsystem)

Scans published HTML on save and records every real internal link
(`internal_links`: polymorphic source → target + anchor). Powers the duplicate
guard, the orphan report, and a drill-down UI. Keep tracker and agent as
**separate tables**: the tracker mirrors reality and rebuilds destructively; the
suggestion queue holds human decisions (dismissals) that must survive rebuilds.

---

## 9. WordPress porting notes

- Two custom tables as above; polymorphic type = post_type string.
- Hooks: `save_post` (published only) → incremental dictionary rebuild + re-scan +
  purge-and-regenerate suggestions; `wp_schedule_event` weekly for full rebuild.
- Edit-screen widget → Gutenberg sidebar plugin or classic meta box; apply via
  AJAX through `wp_update_post` (respect the §6.3 ordering — WP's `save_post`
  hook will fire your purge).
- Byte offsets: keep the `strpos`-family discipline; PCRE `u`-flag only for
  tokenizing, never for offset math.
- The filler-token list and the doc-frequency threshold (>3) are the quality
  knobs — expose both as plugin settings.
