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
}
