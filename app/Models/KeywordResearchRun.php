<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One keyword-research session: the seed keywords an admin pasted plus the
 * discovered term universe (hasMany terms). Culminates in blog_topic_ideas.
 */
class KeywordResearchRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['seeds' => 'array'];
    }

    public function terms()
    {
        return $this->hasMany(KeywordResearchTerm::class, 'run_id');
    }

    public function chosenTerms()
    {
        return $this->terms()->where('chosen', true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The connected spoke this run targets (null = this/local site). */
    public function site()
    {
        return $this->belongsTo(ConnectedSite::class, 'site_id');
    }

    public function targetLabel(): string
    {
        return $this->site_id ? ($this->site?->name ?? "site #{$this->site_id}") : 'This site';
    }
}
