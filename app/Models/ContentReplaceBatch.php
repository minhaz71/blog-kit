<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentReplaceBatch extends Model
{
    protected $fillable = [
        'user_id', 'find', 'replace', 'case_sensitive', 'whole_word',
        'scopes', 'records_count', 'occurrences_count', 'reverted_at',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'whole_word' => 'boolean',
        'scopes' => 'array',
        'reverted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ContentReplaceItem::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
