<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Curated, severity-ranked security event — the source for the security
 * dashboard and intrusion-alert emails. Created via SecurityAlertService.
 */
class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'notified' => 'boolean', 'created_at' => 'datetime'];
    }

    public const SEVERITIES = ['info', 'warning', 'high', 'critical'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCritical($q)
    {
        return $q->whereIn('severity', ['high', 'critical']);
    }
}
