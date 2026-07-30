<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A snapshot of a post's title/excerpt/content BEFORE an edit replaced it —
 * WordPress-style revision history. user_id is who made the replacing edit
 * (null = the AI writer / a system process).
 */
class PostRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editorLabel(): string
    {
        return $this->editor?->name ?: 'AI writer / system';
    }
}
