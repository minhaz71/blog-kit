<?php

namespace App\Services\Ai;

use App\Models\ContentCluster;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns the content-cluster map into a browsable, two-level blog TAXONOMY:
 * each cluster becomes a sub-category, grouped under a small set of broad
 * MOTHER categories. Mothers are named by AI when a provider key is set,
 * otherwise a single deterministic mother is used.
 *
 * Hard-capped at funnel.max_categories (default 20). Idempotent: re-running
 * re-uses existing categories by slug and never exceeds the cap — over-cap
 * clusters attach to their mother instead of spawning a new sub.
 *
 * No writing/AI is REQUIRED: with no API key it still builds a usable tree.
 */
class CategoryPlanner
{
    /** @return array{mothers:int, subs:int, linked:int, capped:int, message:string} */
    public function run(): array
    {
        $clusters = ContentCluster::query()->orderBy('name')->get();

        if ($clusters->isEmpty()) {
            return ['mothers' => 0, 'subs' => 0, 'linked' => 0, 'capped' => 0, 'message' => 'No content clusters yet — nothing to categorize.'];
        }

        $maxTotal = max(1, min(50, (int) setting('funnel.max_categories', 20) ?: 20));
        $maxRoot = max(1, min($maxTotal, (int) setting('funnel.max_root_categories', 6) ?: 6));

        $groups = $this->groupClusters($clusters, $maxRoot);

        $mothers = 0;
        $subs = 0;
        $linked = 0;
        $capped = 0;
        $sortRoot = 0;

        foreach ($groups as $motherName => $groupClusters) {
            $mother = $this->resolveCategory($motherName, null, $sortRoot++);
            if ($mother->wasRecentlyCreated) {
                $mothers++;
            }

            $sortSub = 0;
            foreach ($groupClusters as $cluster) {
                // Cap reached → the cluster still gets a home (its mother), just
                // no dedicated sub-category. Never exceed the total.
                if (PostCategory::count() >= $maxTotal && ! $this->categoryExists($cluster->name)) {
                    $cluster->update(['post_category_id' => $mother->id]);
                    $capped++;
                    $linked++;

                    continue;
                }

                $sub = $this->resolveCategory($cluster->name, $mother->id, $sortSub++, reparentAuto: true);
                if ($sub->wasRecentlyCreated) {
                    $subs++;
                }
                $cluster->update(['post_category_id' => $sub->id]);
                $linked++;
                $this->seedDescription($sub, $cluster);
            }

            $this->seedDescription($mother, null);
        }

        // File any still-uncategorized post onto its cluster's category. We only
        // touch posts with NO category, so an admin's manual choice is safe.
        $refiled = 0;
        foreach (ContentCluster::whereNotNull('post_category_id')->get(['id', 'post_category_id']) as $c) {
            $refiled += Post::where('content_cluster_id', $c->id)
                ->whereNull('post_category_id')
                ->update(['post_category_id' => $c->post_category_id]);
        }

        $message = "Built {$mothers} mother + {$subs} sub-categories from {$clusters->count()} clusters"
            .($capped > 0 ? " ({$capped} attached to a mother — {$maxTotal}-category cap reached)" : '')
            .($refiled > 0 ? "; filed {$refiled} post(s)" : '').'.';

        return ['mothers' => $mothers, 'subs' => $subs, 'linked' => $linked, 'capped' => $capped, 'refiled' => $refiled, 'message' => $message];
    }

    /**
     * Group clusters into mother categories. AI when a provider key exists,
     * otherwise a single deterministic mother (still a valid mother→sub tree).
     *
     * @return array<string, array<int, ContentCluster>>  mother name => clusters
     */
    protected function groupClusters(Collection $clusters, int $maxRoot): array
    {
        if ($provider = $this->provider()) {
            try {
                return $this->aiGroup($provider, $clusters, $maxRoot);
            } catch (\Throwable) {
                // Fall through to the deterministic grouping — never fail the build.
            }
        }

        $mother = trim((string) setting('general.site_name')) ?: 'Topics';

        return [$mother => $clusters->all()];
    }

    /** @return array<string, array<int, ContentCluster>> */
    protected function aiGroup(string $provider, Collection $clusters, int $maxRoot): array
    {
        $list = $clusters
            ->map(fn (ContentCluster $c) => '- '.$c->name.($c->primary_keyword ? ' (keyword: '.$c->primary_keyword.')' : ''))
            ->implode("\n");

        $system = <<<'SYS'
You are an information architect for a content blog. Group the given content clusters into a SMALL set of broad, non-overlapping MOTHER categories a reader would browse by (like top navigation sections).
Return ONLY JSON: {"mothers":[{"name":"<short mother category name>","clusters":["<exact cluster name from the list>", ...]}]}
Rules:
- At most %d mother categories. Every cluster assigned to exactly ONE mother.
- Mother names: short, plain, reader-facing nouns. NEVER use funnel words (top/middle/bottom, awareness, decision) — group by TOPIC, not funnel stage.
- Mothers must be distinct and not overlap. Use the exact cluster names given.
SYS;

        $user = "CONTENT CLUSTERS:\n".$list."\n\nGroup them into at most {$maxRoot} mother categories now.";

        $parsed = LlmClient::parseJson(
            LlmClient::for($provider)->withContext('plan')->complete(sprintf($system, $maxRoot), $user, maxTokens: 3000)
        );

        // Map returned cluster names back to models (case-insensitive by name).
        $byName = $clusters->keyBy(fn (ContentCluster $c) => mb_strtolower(trim($c->name)));
        $groups = [];
        $assigned = [];

        foreach ((array) ($parsed['mothers'] ?? []) as $m) {
            $name = trim((string) ($m['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            foreach ((array) ($m['clusters'] ?? []) as $cn) {
                $cluster = $byName->get(mb_strtolower(trim((string) $cn)));
                if ($cluster && ! isset($assigned[$cluster->id])) {
                    $groups[$name][] = $cluster;
                    $assigned[$cluster->id] = true;
                }
            }
        }

        if ($groups === []) {
            throw new \RuntimeException('AI grouping returned nothing usable.');
        }

        // Any cluster the model forgot → the first mother, so none is lost.
        $firstMother = array_key_first($groups);
        foreach ($clusters as $cluster) {
            if (! isset($assigned[$cluster->id])) {
                $groups[$firstMother][] = $cluster;
            }
        }

        return $groups;
    }

    /** First AI provider with an API key, or null (deterministic mode). */
    protected function provider(): ?string
    {
        foreach (['anthropic', 'openai', 'gemini'] as $p) {
            if (trim((string) setting("ai.{$p}_api_key")) !== '') {
                return $p;
            }
        }

        return null;
    }

    protected function categoryExists(string $name): bool
    {
        return PostCategory::where('slug', Str::slug(trim($name)) ?: 'general')->exists();
    }

    /**
     * Find (by slug) or create a category at the given level. When $reparentAuto
     * is true, an existing sub that currently sits under the auto default mother
     * (i.e. was auto-filed at publish) is moved to the AI-chosen mother — so the
     * "Build category tree" pass can reorganize auto-created categories. An
     * admin-organized category (any other parent, or a root) is never moved.
     */
    protected function resolveCategory(string $name, ?int $parentId, int $sortOrder, bool $reparentAuto = false): PostCategory
    {
        $name = trim($name) ?: 'General';
        $slug = Str::slug($name) ?: 'general';

        $existing = PostCategory::where('slug', $slug)->first();
        if ($existing) {
            if ($reparentAuto && $parentId && $existing->id !== $parentId) {
                $autoMotherId = (int) setting('funnel.auto_mother_category_id');
                if ($autoMotherId && $existing->parent_id === $autoMotherId) {
                    $existing->update(['parent_id' => $parentId]);
                }
            }

            return $existing;
        }

        return PostCategory::create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'is_active' => true,
            'show_in_menu' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * The default "mother" that auto-filed sub-categories live under until the
     * AI grouping pass reorganizes them. Named after the site; id remembered in
     * a setting so reparenting can find it.
     */
    public function defaultMother(): PostCategory
    {
        $id = (int) setting('funnel.auto_mother_category_id');
        if ($id && ($m = PostCategory::find($id))) {
            return $m;
        }

        $name = trim((string) setting('general.site_name')) ?: 'Topics';
        $mother = $this->resolveCategory($name, null, 0);
        Setting::set('funnel.auto_mother_category_id', $mother->id);

        return $mother;
    }

    /**
     * The category a post in this cluster should be filed under — creating a
     * sub-category for the cluster on the fly (under the default mother) the
     * first time, cap-aware. Used at publish so a blank site self-categorizes
     * without anyone clicking "Build category tree". Returns the category id.
     */
    public function categoryForCluster(ContentCluster $cluster): int
    {
        if ($cluster->post_category_id && PostCategory::whereKey($cluster->post_category_id)->exists()) {
            return $cluster->post_category_id;
        }

        $maxTotal = max(1, min(50, (int) setting('funnel.max_categories', 20) ?: 20));
        $mother = $this->defaultMother();

        // At the cap and this cluster has no category yet → file under the mother.
        if (PostCategory::count() >= $maxTotal && ! $this->categoryExists($cluster->name)) {
            $cluster->update(['post_category_id' => $mother->id]);

            return $mother->id;
        }

        $sub = $this->resolveCategory($cluster->name, $mother->id, 0);
        $cluster->update(['post_category_id' => $sub->id]);
        $this->seedDescription($sub, $cluster);

        return $sub->id;
    }

    /** Give a fresh category a one-line description so its archive isn't thin. */
    protected function seedDescription(PostCategory $category, ?ContentCluster $cluster): void
    {
        if (filled($category->description)) {
            return;
        }

        $text = $cluster && filled($cluster->description)
            ? $cluster->description
            : 'Articles and guides about '.$category->name.'.';

        $category->forceFill(['description' => Str::limit(trim($text), 240, '')])->saveQuietly();
    }
}
