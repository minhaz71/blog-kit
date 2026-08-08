# Hemdox BlogKit — Real Keyword & Topic Research (finding → writing → linking)

## The gap (today)

The pipeline **cluster → write → link → categorize** is already built and real. But the
step *before* it — research — is **not real**. `FunnelPlanner::research()` asks the LLM to
*imagine* "what real readers search for." That means:

- ❌ No **search volume** → can't prioritize by what's actually popular.
- ❌ No real **People-Also-Ask / autocomplete** → misses the real questions people type.
- ❌ No **SERP data** → doesn't know what already ranks (competition / content gap).
- ✅ Everything downstream (clusters, funnel, titles, writer, linker, categories) is real.

**Goal:** replace the imagined dossier with a real *discovery layer* — popular topics, real
questions, real pain — then let the existing machine cluster, write, and link them.

---

## The system: 8 stages (travel-niche example)

Seed: **"Japan travel"**

1. **Expand** — seed → hundreds of related keywords + monthly volume + difficulty + intent.
   *(e.g. "japan itinerary 7 days" 8.1k/mo, "is japan expensive" 2.4k, "jr pass worth it" 1.9k…)*
2. **Questions** — real People-Also-Ask + autocomplete questions
   *("do you tip in japan", "is japan safe for solo female travellers", "what to pack for japan")*.
3. **Pain mining** — synthesize real pains from the question phrasing (+ optional Reddit/forum
   signals): "overwhelmed planning a first trip", "afraid of the language barrier", "budgeting".
4. **Prioritize** — score every keyword and keep the winners:
   `opportunity = √volume × intentFit × clusterFit ÷ (difficulty + 10)`, minus a
   cannibalization check against what you already published (already built).
5. **Cluster** — group keywords into pillar + spokes. The *proper* method is **SERP-overlap**:
   keywords whose top-10 Google results overlap ≥3 URLs belong to the same cluster (Google
   itself treats them as one topic). Pillar = the broad high-volume head term; spokes = the
   long-tail questions. Funnel stage comes from intent (informational→top, comparison→middle,
   "best/booking"→bottom).
6. **Plan** — write the winners into the existing **`blog_topic_ideas` waiting area**, now
   carrying REAL volume/difficulty/SERP evidence per idea (not a guess).
7. **Write** — existing cluster/funnel-aware `BlogWriter`, one by one, pillar first.
8. **Link + Categorize** — existing cluster-aware internal linker + auto-taxonomy (just built).

Stages **4–8 already exist**. This project builds **1, 2, 3** and upgrades **5** to
SERP-overlap.

---

## The one decision: where does the real data come from?

Real keyword data needs a source. Options, cheapest→richest:

| Provider | Volume? | PAA/SERP? | Cost | Setup |
|---|---|---|---|---|
| **Free (Google Autocomplete + PAA + related searches)** | ❌ no numbers | ✅ questions + related | **$0** | none, but scraping is rate-limited/fragile |
| **DataForSEO** (recommended) | ✅ real volume + difficulty + trends | ✅ full SERP + PAA | ~**$0.05–0.10 / keyword task**, pay-as-you-go | one API key |
| **Ahrefs / Semrush** | ✅ richest | ✅ | **$$$ subscription** | account + key |
| **Keep LLM-only** (today) | ❌ | ❌ | $0 | none — but not "real" |

**Recommended: DataForSEO primary + free Google Autocomplete/PAA as a $0 fallback + LLM to
fill gaps** — the same "cheap primary + free fallback" shape as the AI thumbnail generator
(fal.ai + fallbacks). Real numbers when the key is set; still useful (real questions, no
volume) when it isn't.

---

## How it plugs into the code

- **`App\Services\Research\KeywordResearch`** — a provider interface with three calls:
  `expand($seed)`, `questions($seed)`, `serp($keyword)`. Drivers:
  `DataForSeoDriver`, `GoogleFreeDriver`, `LlmDriver` (current behaviour, always the fallback).
- **`keyword_research` table** — stores keyword, volume, difficulty, intent, SERP snapshot,
  cluster, chosen/skipped. This is the *evidence* behind every idea (auditable, re-usable).
- **`FunnelPlanner::research()`** — calls `KeywordResearch` first; feeds REAL keywords +
  questions + pain into the existing cluster/candidate prompts, so titles are grounded in
  real demand. SERP-overlap clustering replaces the imagined grouping when SERP data exists.
- **Admin** — the "Generate ideas" modal gets a **"Use real keyword data"** toggle + a seed
  box; a new **Keyword Research** screen shows the keyword universe (volume/difficulty/intent),
  lets you pick which to turn into a plan, and shows the SERP-overlap clusters before writing.
- **Settings** — provider + API key (Admin → SEO/AI settings) + a monthly spend cap guard.

All additive; no existing behaviour removed (LLM-only stays the zero-config default).

---

## Two ways to deliver

1. **Build it into BlogKit** (this plan) — every site gets real research as a feature. Needs
   the data-source decision above.
2. **Run it once now, Claude-side** — I can do the travel-niche research this session using
   the SERP-clustering tooling and load the results straight into your `blog_topic_ideas`, as
   a working example, without building the in-app feature yet.
