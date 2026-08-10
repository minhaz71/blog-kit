<?php

namespace App\Services\Ai;

use App\Models\BlogTopicIdea;
use App\Models\ConnectedSite;

/**
 * Excel round-trip for the Blog Ideas (funnel) waiting area: export the parked
 * ideas to a CSV, edit the brief (title, pain point, angle, outline, keywords…)
 * in Excel, then re-import to update the existing ideas in place. Rows keep
 * their database `id` so a re-import matches and UPDATES; a blank `id` CREATES
 * a new idea (deduped by the same fingerprint the funnel builder uses).
 *
 * CSV (not XLSX) on purpose — it opens directly in Excel/Sheets and matches the
 * rest of the app's native fputcsv/fgetcsv handling with no extra dependency.
 * List columns (secondary_keywords, outline) are pipe-separated: "A | B | C".
 */
class BlogIdeaCsv
{
    /** Column order for export; also the recognized import headers. */
    public const HEADERS = [
        'id', 'title', 'primary_keyword', 'secondary_keywords', 'pain_point',
        'audience_need', 'angle', 'outline', 'funnel_stage', 'role', 'cluster',
        'site_ids', 'status',
    ];

    public const FILENAME = 'blog-ideas.csv';

    /** @param iterable<BlogTopicIdea> $ideas */
    public static function export(iterable $ideas): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::HEADERS);

        foreach ($ideas as $idea) {
            fputcsv($handle, [
                $idea->id,
                $idea->title,
                (string) $idea->primary_keyword,
                implode(' | ', (array) $idea->secondary_keywords),
                (string) $idea->pain_point,
                (string) $idea->audience_need,
                (string) $idea->angle,
                implode(' | ', (array) $idea->outline),
                (string) $idea->funnel_stage,
                (string) $idea->role,
                (string) $idea->cluster,
                $idea->site_id ? (string) $idea->site_id : 'local',
                (string) $idea->status,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return array{updated:int, created:int, skipped:int}
     */
    public static function import(string $path): array
    {
        $updated = $created = $skipped = 0;

        $probe = fopen($path, 'r');
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string) fgets($probe));
        fclose($probe);
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $handle = fopen($path, 'r');
        $headers = null;

        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($headers === null) {
                $headers = array_map(
                    fn ($h) => str_replace([' ', '-'], '_', strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)))),
                    $cols
                );

                continue;
            }

            $row = [];
            foreach ($headers as $i => $key) {
                $row[$key] = trim((string) ($cols[$i] ?? ''));
            }
            // Accept "site_id" as an alias for "site_ids".
            if (! array_key_exists('site_ids', $row) && array_key_exists('site_id', $row)) {
                $row['site_ids'] = $row['site_id'];
            }

            $title = trim((string) ($row['title'] ?? ''));
            $id = (int) ($row['id'] ?? 0);
            $attrs = self::editableAttrs($row);

            if ($id > 0) {
                $idea = BlogTopicIdea::find($id);

                if (! $idea) {
                    $skipped++;

                    continue;
                }

                // A changed title needs a fresh fingerprint; skip if it would
                // collide with a DIFFERENT idea (the unique dedupe key).
                if ($title !== '' && $title !== $idea->title) {
                    $fp = BlogTopicIdea::fingerprint($title);

                    if (BlogTopicIdea::where('fingerprint', $fp)->where('id', '!=', $idea->id)->exists()) {
                        $skipped++;

                        continue;
                    }

                    $attrs['title'] = $title;
                    $attrs['fingerprint'] = $fp;
                }

                $idea->update($attrs);
                $updated++;

                continue;
            }

            // No id → create a new idea (title required, deduped by fingerprint).
            if ($title === '') {
                $skipped++;

                continue;
            }

            $fp = BlogTopicIdea::fingerprint($title);

            if (BlogTopicIdea::where('fingerprint', $fp)->exists()) {
                $skipped++;

                continue;
            }

            BlogTopicIdea::create($attrs + [
                'title' => $title,
                'fingerprint' => $fp,
                'cluster' => ($row['cluster'] ?? '') !== '' ? $row['cluster'] : 'imported',
                'role' => in_array($row['role'] ?? '', ['pillar', 'spoke', 'comparison'], true) ? $row['role'] : 'spoke',
                'funnel_stage' => in_array($row['funnel_stage'] ?? '', ['top', 'middle', 'bottom'], true) ? $row['funnel_stage'] : 'top',
                'status' => in_array($row['status'] ?? '', ['waiting', 'queued', 'written', 'dismissed'], true) ? $row['status'] : 'waiting',
                'verified_rounds' => 0,
            ]);
            $created++;
        }

        fclose($handle);

        return ['updated' => $updated, 'created' => $created, 'skipped' => $skipped];
    }

    /**
     * The subset of columns a user may edit in Excel. Only keys actually
     * present in the row are returned, so a trimmed-down CSV never wipes
     * fields it didn't include. Enum fields are only applied when valid.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    protected static function editableAttrs(array $row): array
    {
        $attrs = [];

        if (array_key_exists('primary_keyword', $row)) {
            $attrs['primary_keyword'] = $row['primary_keyword'] ?: null;
        }
        if (array_key_exists('secondary_keywords', $row)) {
            $attrs['secondary_keywords'] = self::splitList($row['secondary_keywords'], true);
        }
        if (array_key_exists('pain_point', $row)) {
            $attrs['pain_point'] = $row['pain_point'] ?: null;
        }
        if (array_key_exists('audience_need', $row)) {
            $attrs['audience_need'] = $row['audience_need'] ?: null;
        }
        if (array_key_exists('angle', $row)) {
            $attrs['angle'] = $row['angle'] ?: null;
        }
        if (array_key_exists('outline', $row)) {
            $attrs['outline'] = self::splitList($row['outline'], false);
        }
        if (array_key_exists('cluster', $row) && $row['cluster'] !== '') {
            $attrs['cluster'] = $row['cluster'];
        }
        if (($row['funnel_stage'] ?? '') !== '' && in_array($row['funnel_stage'], ['top', 'middle', 'bottom'], true)) {
            $attrs['funnel_stage'] = $row['funnel_stage'];
        }
        if (($row['role'] ?? '') !== '' && in_array($row['role'], ['pillar', 'spoke', 'comparison'], true)) {
            $attrs['role'] = $row['role'];
        }
        if (($row['status'] ?? '') !== '' && in_array($row['status'], ['waiting', 'queued', 'written', 'dismissed'], true)) {
            $attrs['status'] = $row['status'];
        }
        if (array_key_exists('site_ids', $row)) {
            $attrs['site_id'] = self::resolveSiteId($row['site_ids']);
        }

        return $attrs;
    }

    /** Split a pipe/newline (and, for keywords, comma) separated cell into a clean list. */
    protected static function splitList(string $value, bool $allowComma): array
    {
        $pattern = $allowComma ? '/\r\n|\r|\n|\s*\|\s*|\s*,\s*/' : '/\r\n|\r|\n|\s*\|\s*/';

        return array_values(array_filter(array_map('trim', preg_split($pattern, $value) ?: []), fn ($v) => $v !== ''));
    }

    /** "local"/blank → null (this site); a numeric id → that spoke IF it exists. */
    protected static function resolveSiteId(string $value): ?int
    {
        $value = strtolower(trim($value));

        if ($value === '' || in_array($value, ['local', 'self', 'this', 'none'], true) || ! ctype_digit($value)) {
            return null;
        }

        return ConnectedSite::whereKey((int) $value)->exists() ? (int) $value : null;
    }
}
