<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkSuggestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function target()
    {
        return $this->morphTo();
    }

    public static function fingerprintFor(string $sourceType, int $sourceId, string $field, string $targetType, int $targetId, string $anchor, int $occurrence): string
    {
        return md5(implode('|', [$sourceType, $sourceId, $field, $targetType, $targetId, mb_strtolower($anchor), $occurrence]));
    }
}
