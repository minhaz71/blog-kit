<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One discovered keyword with its research evidence (volume/difficulty/intent/
 * SERP) and its place in the plan (cluster/role/funnel_stage). The audit trail
 * behind every blog_topic_idea a research run produces.
 */
class KeywordResearchTerm extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'serp' => 'array',
            'chosen' => 'boolean',
            'cpc' => 'decimal:2',
        ];
    }

    public function run()
    {
        return $this->belongsTo(KeywordResearchRun::class, 'run_id');
    }

    /** Order-independent normalized token key, for cross-source dedupe. */
    public static function normalize(string $keyword): string
    {
        $tokens = preg_split('/\s+/', mb_strtolower(trim($keyword)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($tokens);

        return Str::limit(implode(' ', $tokens), 190, '');
    }
}
