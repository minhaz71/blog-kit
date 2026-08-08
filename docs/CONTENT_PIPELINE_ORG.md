# Hemdox BlogKit — Organized, site-aware AI content pipeline

## The problem
The AI content tools work but feel scattered: they live across 3 nav groups
(Content, Research, Network) and don't consistently ask **"for which site?"**.
The site dimension is already threaded through the DATA (keyword_research_runs,
blog_topic_ideas, ai_import_batches all carry a target site), but the UI doesn't
surface it, so there's no obvious end-to-end flow.

## The organizing principle
**SITE is the top-level choice.** Every generation step answers *"for This site
(hub) or a connected spoke?"* — and that choice flows straight through:

```
RESEARCH ─▶ IDEAS ─▶ WRITE ─▶ PUBLISH ─▶ MONITOR
(keywords)  (funnel)  (AI)     (push)    (live links)
   └──────────── target site carried the whole way ───────────┘
```

## The 5 stages (one clear order)

| # | Stage | Screen | Site-aware? | Gap to fix |
|---|---|---|---|---|
| 1 | **Research** | Keyword Research | ✅ has "Target site" | — |
| 2 | **Ideas** | Blog Ideas + "Generate ideas" | ⚠️ data yes, UI no | add site selector to *Generate ideas*; site column + filter on the list |
| 3 | **Write** | AI Blog Batches | ⚠️ has `network_site_ids` | show target site column |
| 4 | **Publish** | (automatic fan-out) | ✅ | — |
| 5 | **Monitor** | Batch monitor + Sync status | ✅ (live links added) | show target-site badge on the monitor |

## What to build

### A. "Generate ideas" gets a Target-site selector  🔴 (the headline ask)
The funnel "Generate ideas" modal only asks provider/model/rounds/niche. Add a
**Target site** dropdown (This site + active connected sites, hub-only) — so you
can research + plan a cluster/funnel **for a specific spoke** from the hub.

### B. FunnelPlanner becomes site-aware  🔴
When a target site is chosen, FunnelPlanner:
- dedupes candidate titles against **that spoke's** mirrored posts
  (`NetworkRemotePost`), not the hub's, and
- stamps `site_id` on the `blog_topic_ideas` it creates,
so ideas flow to the writer already targeted (same as Keyword Research's
PlanBuilder already does).

### C. Blog Ideas: see + filter by site  🟠
Add a **Site** column (This site / spoke name) and a **site filter** to the Blog
Ideas list, so "what's planned for site X" is one click. `sendToWriter` already
carries the idea's `site_id` to the batch fan-out.

### D. AI Blog Batches: show the target  🟠
A **Target sites** column on the batches list, and a target-site badge on the
live monitor, so a running batch clearly shows where its articles go.

### E. Nav: read top-to-bottom as the flow  🟡
Group the five stages so the sidebar reads in pipeline order (Research → Ideas →
Clusters → Batches → Sync status), instead of split across unrelated groups.

### F. (Optional) Per-site pipeline overview  🟢
A small dashboard: for each site, counts of keywords researched, ideas waiting,
articles writing, and published/pushed — the "content plan at a glance".

## Recommended build order
A + B (choose the site and plan for it) → C (see ideas per site) → D (batches
show target) → E (nav order) → F (overview). All additive; no data migrations.
