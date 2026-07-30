<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'secondary_keywords' => 'array',
            'noindex' => 'boolean',
            'nofollow' => 'boolean',
            'noarchive' => 'boolean',
            'nosnippet' => 'boolean',
            'schema_overrides' => 'array',
            'schema_enabled' => 'boolean',
            'seo_analysis' => 'array',
        ];
    }

    public function metable()
    {
        return $this->morphTo();
    }

    /** Builds the robots meta content string, e.g. "noindex, nofollow, max-snippet:120". */
    public function robotsContent(): ?string
    {
        $parts = [];

        if ($this->noindex) {
            $parts[] = 'noindex';
        }
        if ($this->nofollow) {
            $parts[] = 'nofollow';
        }
        if ($this->noarchive) {
            $parts[] = 'noarchive';
        }
        if ($this->nosnippet) {
            $parts[] = 'nosnippet';
        }
        if ($this->max_snippet !== null) {
            $parts[] = 'max-snippet:'.$this->max_snippet;
        }
        if ($this->max_image_preview) {
            $parts[] = 'max-image-preview:'.$this->max_image_preview;
        }
        if ($this->max_video_preview !== null) {
            $parts[] = 'max-video-preview:'.$this->max_video_preview;
        }

        return $parts ? implode(', ', $parts) : null;
    }

    public function scoreLabel(): string
    {
        return match (true) {
            $this->seo_score >= 80 => 'good',
            $this->seo_score >= 50 => 'needs_improvement',
            default => 'poor',
        };
    }
}
