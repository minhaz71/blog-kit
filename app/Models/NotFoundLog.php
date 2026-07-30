<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotFoundLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_hit_at' => 'datetime'];
    }

    public function redirect()
    {
        return $this->belongsTo(Redirect::class);
    }

    public static function track(string $url, ?string $referrer, ?string $userAgent, ?string $ip): void
    {
        $log = static::firstOrNew(['url' => str($url)->limit(255, '')->toString()]);

        if ($log->exists) {
            $log->hits++;
        }

        $log->fill([
            'referrer' => $referrer ? str($referrer)->limit(995, '')->toString() : $log->referrer,
            'user_agent' => $userAgent ? str($userAgent)->limit(495, '')->toString() : $log->user_agent,
            'ip_address' => $ip ?? $log->ip_address,
            'last_hit_at' => now(),
        ])->save();
    }
}
