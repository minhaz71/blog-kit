<?php

namespace App\Services\Research;

use App\Models\BlogTopicIdea;
use App\Models\KeywordResearchRun;
use App\Models\KeywordResearchTerm;
use Illuminate\Support\Str;

/**
 * The handoff: turn a research run's CHOSEN, clustered terms into
 * blog_topic_ideas (the existing waiting area) — carrying cluster / role /
 * funnel_stage / primary + secondary keywords / researched link targets — so
 * the existing writer → linker → category pipeline takes over unchanged.
 *
 * Re-uses the same cannibalization guard as the Funnel Builder, so a keyword
 * whose topic already exists as a post/idea is skipped, never duplicated.
 */
class PlanBuilder
{
    /**
     * @return array{created:int, skipped:int, message:string}
     */
    public function build(KeywordResearchRun $run, array $opts = []): array
    {
        $limit = max(1, (int) ($opts['limit'] ?? 60));

        $terms = $run->chosenTerms()
            ->whereNotNull('cluster')
            ->where('status', '!=', 'planned')
            ->orderByDesc('opportunity')
            ->get();

        if ($terms->isEmpty()) {
            return ['created' => 0, 'skipped' => 0, 'message' => 'No chosen, clustered terms to plan. Run research first (and keep some terms checked).'];
        }

        // Always keep every pillar; fill the rest with the highest-opportunity
        // spokes up to the limit.
        $pillars = $terms->where('role', 'pillar');
        $spokes = $terms->where('role', 'spoke')->take(max(0, $limit - $pillars->count()));
        $selected = $pillars->merge($spokes)->unique('id');

        // Secondary keywords for a term = its top sibling spokes in the same cluster.
        $byCluster = $terms->groupBy('cluster');

        // Cannibalization guard corpus: for a spoke target, check against THAT
        // site's mirrored posts + its own ideas (not this hub's content); for a
        // local run, the usual local corpus.
        $existing = $run->site_id
            ? array_merge(
                \App\Models\NetworkRemotePost::where('site_id', $run->site_id)->pluck('title')->filter()->values()->all(),
                BlogTopicIdea::where('site_id', $run->site_id)->pluck('title')->filter()->values()->all(),
            )
            : BlogTopicIdea::conflictCorpus(includePosts: true);
        // Opt-in: one AI call per cluster fills a full brief (sharp title +
        // pain_point, audience_need, angle, outline). Off → mechanical title,
        // empty brief (zero AI cost). Failures per cluster fall back silently.
        $briefs = [];
        if (($opts['ai_brief'] ?? false) && (new IdeaBriefWriter)->available()) {
            $writer = new IdeaBriefWriter;
            foreach ($selected->groupBy('cluster') as $clusterName => $clusterTerms) {
                $briefs += $writer->writeCluster((string) $clusterName, $clusterTerms, ['country' => $run->target_country]);
            }
        }

        $created = 0;
        $skipped = 0;

        foreach ($selected as $term) {
            $brief = $briefs[$term->id] ?? null;
            $title = ($brief && $brief['title'] !== '') ? $brief['title'] : $this->toTitle($term);
            $fingerprint = BlogTopicIdea::fingerprint($title);

            if (BlogTopicIdea::where('fingerprint', $fingerprint)->exists()
                || BlogTopicIdea::rankingConflict($title, $existing, 0.6) !== null) {
                $term->update(['status' => 'planned']);
                $skipped++;

                continue;
            }

            $secondary = ($byCluster[$term->cluster] ?? collect())
                ->where('id', '!=', $term->id)
                ->sortByDesc('opportunity')
                ->take(4)
                ->pluck('keyword')
                ->values()
                ->all();

            BlogTopicIdea::create([
                'batch_id' => null,
                'site_id' => $run->site_id, // multisite target carried into the idea
                'title' => $title,
                'fingerprint' => $fingerprint,
                'cluster' => $term->cluster,
                'role' => $term->role === 'pillar' ? 'pillar' : 'spoke',
                'funnel_stage' => in_array($term->funnel_stage, ['top', 'middle', 'bottom'], true) ? $term->funnel_stage : 'top',
                'primary_keyword' => $term->keyword,
                'secondary_keywords' => $secondary,
                'search_query' => $term->keyword,
                'pain_point' => $brief['pain_point'] ?? null,
                'audience_need' => $brief['audience_need'] ?? null,
                'angle' => $brief['angle'] ?? null,
                'outline' => $brief['outline'] ?? [],
                'link_targets' => [],
                'verified_rounds' => 0,
                'status' => 'waiting',
            ]);

            $existing[] = $title; // guard within this run too
            $term->update(['status' => 'planned']);
            $created++;
        }

        $run->update(['status' => 'planned']);

        return [
            'created' => $created,
            'skipped' => $skipped,
            'message' => "Added {$created} idea(s) to the waiting area"
                .($skipped > 0 ? " ({$skipped} skipped — too close to existing content)" : '')
                .'. Open Blog ideas to send them to the writer.',
        ];
    }

    /**
     * Turn a raw keyword into a working headline. The writer refines titles
     * later, so this only needs to be a clean, human starting point: questions
     * stay questions; a pillar reads as a guide.
     */
    protected function toTitle(KeywordResearchTerm $term): string
    {
        $kw = trim($term->keyword);
        $isQuestion = (bool) preg_match('/^(how|what|why|when|where|which|who|is|are|can|do|does|should)\b/i', $kw);

        $title = Str::of($kw)->squish()->title()->toString();
        // Title-case leaves small words capitalized; lower a few common ones mid-phrase.
        $title = preg_replace_callback('/\b(A|An|The|To|Of|In|On|For|And|Or|With|Vs)\b/', fn ($m) => strtolower($m[1]), $title);
        $title = ucfirst($title);

        if ($isQuestion && ! str_ends_with($title, '?')) {
            $title .= '?';
        }

        // A pillar gets a light "guide" framing when it isn't already a question.
        if ($term->role === 'pillar' && ! $isQuestion && ! preg_match('/\b(guide|complete|ultimate)\b/i', $title)) {
            $title .= ': The Complete Guide';
        }

        return Str::limit($title, 70, '');
    }
}
