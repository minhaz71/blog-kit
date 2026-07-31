<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hub-side mapping of one local Post to its copy on one connected site,
 * with the remote id + sync status used for re-pushes and (later) two-way sync.
 */
class NetworkPostLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_pushed_at' => 'datetime',
            'conflict_detected_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ConnectedSite::class, 'site_id');
    }
}
