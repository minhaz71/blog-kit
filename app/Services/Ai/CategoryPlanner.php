<?php

namespace App\Services\Ai;

use App\Models\ContentCluster;
use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns the content-cluster map into a browsable, two-level blog TAXONOMY:
 * each cluster becomes a sub-category, grouped under a small set of broad
 * MOTHER categories. Mothers are named by AI when a provider key is set,
 * otherwise a single deterministic mother is used.
 *
 * Hard-capped at blog.max_categories (default 20). Idempotent: re-running
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

        $maxTotal = max(1, min(50, (int) setting('blog.max_categories', 20) ?: 20));
        $maxRoot = max(1, min($maxTotal, (int) setting('blog.max_root_categories', 6) ?: 6));

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

                $sub = $this->resolveCategory($cluster->name, $mother->id, $sortSub++);
                if ($sub->wasRecentlyCreated) {
                    $subs++;
                }
                $cluster->update(['post_category_id' => $sub->id]);
                $linked++;
                $this->seedDescription($sub, $cluster);
            }

            $this->seedDescription($mother, null);
        }

        $message = "Built {$mothers} mother + {$subs} sub-categories from {$clusters->count()} clusters"
            .($capped > 0 ? " ({$capped} attached to a mother — {$maxTotal}-category cap reached)" : '').'.';

        return ['mothers' => $mothers, 'subs' => $subs, 'linked' => $linked, 'capped' => $capped, 'message' => $message];
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

    /** Find (by slug) or create a category at the given level. */
    protected function resolveCategory(string $name, ?int $parentId, int $sortOrder): PostCategory
    {
        $name = trim($name) ?: 'General';
        $slug = Str::slug($name) ?: 'general';

        $existing = PostCategory::where('slug', $slug)->first();
        if ($existing) {
            return $existing; // reuse as-is; never reparent an existing category
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
