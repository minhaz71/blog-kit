# Hemdox BlogKit — Auto Category Taxonomy (Mother + Sub, from Cluster/Funnel/Title)

**Plan only — no code in this document.** All DB changes proposed here are
*additive, nullable* migrations (no drops), so a `git pull` on the live site
stays safe, exactly like the content-strategy work.

---

## Where we are (what this plan builds on)

Already wired & tested (content-strategy work):
- Every post now carries `cluster`, `content_cluster_id`, `content_role`
  (pillar/spoke), `funnel_stage`, `primary_keyword`, `pillar_post_id`.
- Canonical `content_clusters` registry (pillar + spokes) with an admin screen.
- The Funnel Builder designs named clusters and cluster-aware titles.

**The gap this plan closes:** none of that reaches the **public taxonomy**.

| Today | Reality |
|---|---|
| Blog category hierarchy | ❌ none — `post_categories` is flat (`id, name, slug, description`) |
| Post → category | ⚠️ one **manual** `batch.blog_category_id` for the whole batch; never derived from title/cluster |
| Categories in the menu | ❌ never automatic — the header auto-fallback pulls **product** categories only; blog categories must be typed in by hand |
| Category archive page | ✅ exists: `/blog/category/{slug}` (`BlogController::category`) |
| A hierarchy pattern to copy | ✅ the **product** `Category` already has `parent_id` + `parent()/children()/root()/descendantIdsWithSelf()/breadcrumbTrail()` — reuse it verbatim |

So the job is: **add a 2-level blog category tree, auto-fill it from clusters
(capped at 20), auto-assign each post to its cluster's category, and surface the
tree in the menu.**

---

## Design decisions (read before the phases)

### 1. What becomes a category — cluster, not funnel
- **Sub-category = Cluster (1:1).** A cluster is already a hub-and-spoke topic
  set with a pillar; that is *exactly* what a browsable sub-category is. Each
  `content_cluster` maps to one sub-category.
- **Mother category = a theme that groups several clusters** (e.g. for a vape
  blog: *Devices*, *Flavors & Pods*, *Nicotine & Health*, *Guides & Reviews*).
  Derived by one AI "grouping" pass over the cluster list (or hand-set later).
- **Funnel stage is NOT its own category.** People browse by *topic*, not by
  "awareness vs decision", and a `/blog/category/top-funnel` page is thin,
  duplicative and bad for SEO. Funnel is still *used*, just not as a category:
  - the cluster's **pillar** (top-funnel hub) becomes the category's featured /
    intro post;
  - **bottom-funnel** ("best X", buying guides) optionally roll up under one
    cross-cutting mother — *Guides & Reviews* — if you want a reviews hub
    (toggleable; see Phase 4). This is the one place funnel shapes taxonomy.

> Net: **title + cluster decide _which_ category; funnel decides the post's
> _role inside_ it** (pillar = category lead; decision = optional Guides hub).

### 2. The 20-category cap, split across two levels
- One setting: `blog.max_categories` (default **20**), plus
  `blog.max_root_categories` (default **6**).
- The Funnel Builder already makes **3–12 clusters** → ≤12 subs, and ≤6 mothers,
  so ~18 total sits comfortably under 20. The cap is a hard backstop with a
  graceful fallback (below), never a hard failure.
- **Dedup/merge** on creation by slug + title similarity (reuse
  `BlogTopicIdea::similarity`) so "Nicotine Strength" and "Nicotine Strengths"
  collapse to one category instead of two.

### 3. Cluster ↔ category link is stored, not guessed
Add `content_clusters.post_category_id` so a cluster points at its sub-category.
A post then inherits its category from its cluster — deterministic, no
re-classification of body text needed.

---

## Phase A — Give blog categories a hierarchy (foundation)

**Additive migration** on `post_categories` (mirrors the product `Category`):
- `parent_id` (nullable, self-FK, `nullOnDelete`) — null = mother, set = sub.
- `sort_order` (default 0) — menu/order control.
- `is_active` (default true) — hide a category without deleting it.
- `show_in_menu` (default true) — per-category menu opt-out.

**Model** (`PostCategory`): add `parent()`, `children()` (ordered by
`sort_order`), `scopeRoot()`, `breadcrumbTrail()`, `descendantIdsWithSelf()` —
copied from `App\Models\Category`. The existing "never orphan a post on delete"
logic stays; deleting a mother reparents its subs to null (or to the default).

**Admin** (`ContentClusterResource` already exists; `PostCategoryResource`
gains): a `parent_id` select (roots only) + `sort_order` + `show_in_menu`, and
the list groups subs under their mother.

**Front-end:** the category archive (`/blog/category/{slug}`) shows its
sub-categories as chips and, for a mother, lists posts from all descendants
(`descendantIdsWithSelf()`), matching how the shop category page already works.

---

## Phase B — Auto-build the category tree from clusters (capped at 20)

A new service — call it `CategoryPlanner` — runs **after** the Funnel Builder
designs clusters (it already produces the cluster list + themes):

1. **Group clusters into mothers.** One AI pass: "group these N clusters into
   ≤`max_root_categories` broad, non-overlapping sections; name each section."
   Output = mother name per cluster. (Deterministic fallback: 1 mother = the
   niche, all clusters as subs.)
2. **Materialize the tree, respecting the cap:**
   - Create/find each **mother** (`parent_id = null`), then each **sub** =
     cluster (`parent_id = mother`), deduping by slug/similarity.
   - Stop creating **new** categories once `count(post_categories) >=
     blog.max_categories`; any cluster that can't get its own sub is attached to
     its **mother** category instead (never left uncategorised, never over cap).
3. **Link back:** set `content_clusters.post_category_id = sub.id`.
4. **Seed SEO:** give each new category a one-line `description` + SEO meta
   (title/description) from the cluster theme/pillar keyword, so the archive
   page is never thin. Pillar post becomes the category's featured post.

Idempotent + safe to re-run (like `blogkit:backfill-clusters`). Exposed as a
button on the Content Clusters screen ("Build category tree") and/or an artisan
command `blogkit:build-categories`.

**Where auto-creation is triggered:**
- Preferred: at **cluster design time** (Funnel Builder), so categories exist
  before articles are written.
- Also at **publish time** as a safety net (Phase C).

---

## Phase C — Auto-assign each post to its category at publish

In `BlogPublisher` the single line `post_category_id => $batch->blog_category_id`
becomes a resolution chain:

1. If the post has a cluster with a linked category → use
   `cluster.post_category_id` (auto-create the sub if missing, honouring the
   cap → else its mother).
2. Else if the batch set `blog_category_id` (manual override) → use it.
3. Else → the default category (`PostCategory::defaultCategory()`).

So a batch category becomes an **optional override**, not the only source.
Refresh runs keep the existing category (don't re-file a live post) unless it's
still null.

**Result:** publishing a cluster-planned batch auto-populates the whole
taxonomy — mothers, subs, and every post filed correctly — on a blank site,
with zero manual category picking.

---

## Phase D — Surface the tree in the menu (automatic)

The menu is a JSON blob (`navigation.header_menu`) that supports **2 levels
(parent > dropdown children)** — a perfect fit for **mother > subs**.

Changes (all in the header's existing auto-fallback + one setting):
1. New setting `navigation.auto_blog_categories` (default **on** when
   e-commerce is **off**). When on and the admin hasn't defined a manual
   `header_menu`, the header auto-builds from **blog** categories:
   - each **mother** (root, `show_in_menu`, ordered by `sort_order`) → a
     top-level item linking to its archive;
   - its **subs** → the dropdown children;
   - capped to a sensible width (e.g. top 6 mothers), with a "Blog" root and an
     "All topics" link.
2. When e-commerce is **on**, keep product categories as today and optionally
   append a single **"Blog"** dropdown whose children are the mother categories
   (avoids a giant mixed menu).
3. A manual `header_menu` still **wins** — auto only fills the blank. Admins can
   also click "Insert blog categories" in Navigation settings to freeze the
   auto structure into editable rows.

No new menu tables — this reuses the existing repeater/JSON + fallback logic.

---

## Phase E — Guardrails (SEO / UX / safety)

- **Cap is hard:** never exceed `blog.max_categories`; over-cap clusters fall
  back to their mother. Log what was merged/capped (no silent truncation).
- **No thin archives:** a category needs ≥1 published post to show in the menu;
  auto-seed a description + the pillar as featured content.
- **No duplicate taxonomy:** cluster (internal grouping) and sub-category
  (public) are 1:1 by design; dedup by slug/similarity on create.
- **Slugs are stable:** renaming a category keeps its slug unless empty (301s
  are out of scope; keep slugs stable to protect rankings).
- **Reversible + pull-safe:** additive nullable columns; a fresh clone with no
  clusters simply has an empty tree and the current product-category fallback.

---

## Settings added (Content strategy / Navigation)

| Setting | Default | Controls |
|---|---|---|
| `blog.max_categories` | 20 | hard cap on total blog categories |
| `blog.max_root_categories` | 6 | max mother categories |
| `blog.auto_categorize` | on | derive post category from its cluster at publish |
| `navigation.auto_blog_categories` | on (blog mode) | inject blog category tree into the header when the menu is blank |

---

## Build order & effort

| Phase | Depends on | Size | Why this order |
|---|---|---|---|
| A. Category hierarchy (migration + model + admin) | — | M | Nothing works without parent/sub |
| B. `CategoryPlanner` auto-build (capped, mother+sub) | A | M | Fills the tree from existing clusters |
| C. Auto-assign at publish (cluster → category) | A,B | S | Every new post lands in the right place |
| D. Menu auto-population | A | S | Categories become navigable |
| E. Guardrails + settings | A–D | S | Cap, SEO, safety |

All additive/nullable → safe to pull onto puffandpod.com. After deploy:
`php artisan blogkit:build-categories` (idempotent) builds the tree from
existing clusters; new batches then self-categorise.

---

## Decisions locked in

1. **Mother categories → AI-grouped.** The `CategoryPlanner` runs one AI pass to
   group clusters into ≤`max_root_categories` broad, non-overlapping mothers and
   names them; the admin can rename afterwards. (Phase B step 1.)
2. **Bottom-funnel → topic-first.** Decision-stage posts (buying guides, "best
   X") stay in their topic category; funnel stage is metadata only, never its
   own category. The "Guides & Reviews" hub and literal funnel-categories are
   **dropped** from scope.

## Still-open (sensible defaults chosen; change any before/at build)

3. **Cap split:** default ≤6 mothers, remaining budget as subs (≤20 total).
4. **Menu width:** default top 6 mothers in the bar, rest under "More".

---

## Status

Plan finalized with the two decisions above. **No code written yet** — awaiting a
"build it" go-ahead. When you give it, build order is A → B → C → D → E, all
additive/nullable (pull-safe), then `php artisan blogkit:build-categories` on the
server to populate the tree from existing clusters.
