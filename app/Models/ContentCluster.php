<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A canonical topic cluster (pillar + spokes). The ideation layer
 * (BlogTopicIdea) names clusters as free text; this table pins each name to one
 * stable record so linking, pillar pages and per-cluster thumbnail styling have
 * a single source of truth even across many research runs.
 */
class ContentCluster extends Model
{
    protected $guarded = [];

    /** Every post assigned to this cluster (pillar + spokes). */
    public function posts()
    {
        return $this->hasMany(Post::class, 'content_cluster_id');
    }

    /** Just the spokes (supporting articles). */
    public function spokes()
    {
        return $this->posts()->where('content_role', 'spoke');
    }

    /** The pillar/hub post for this cluster, if one is published. */
    public function pillar()
    {
        return $this->belongsTo(Post::class, 'pillar_post_id');
    }

    /**
     * Find (or create) the canonical cluster for a given free-text name. Match
     * is by slug so "Nicotine Strength" and "nicotine-strength" collapse to one
     * row. Safe to call repeatedly (used at publish time).
     */
    public static function resolve(string $name, ?int $siteId = null): self
    {
        $name = trim($name);
        $slug = Str::slug($name) ?: 'general';

        return static::firstOrCreate(
            ['slug' => $slug, 'site_id' => $siteId],
            ['name' => $name !== '' ? $name : 'General'],
        );
    }
}
