<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\MovesInlineStylesToCustomCss;
use App\Models\Concerns\HasSeoMeta;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, HasFaqs, HasSeoMeta, HasSlug, MovesInlineStylesToCustomCss, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }
}
