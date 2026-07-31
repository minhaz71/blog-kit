<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hub-side mirror of one post that lives on a connected site. Read-only from
 * the hub's perspective in Phase 4 (browse/filter); editing remotely arrives
 * with two-way sync.
 */
class NetworkRemotePost extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'remote_updated_at' => 'datetime',
            'pulled_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ConnectedSite::class, 'site_id');
    }
}
