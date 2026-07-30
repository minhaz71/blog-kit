<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReplaceItem extends Model
{
    protected $fillable = [
        'batch_id', 'table_name', 'column_name', 'record_id',
        'old_value', 'new_value', 'occurrences',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ContentReplaceBatch::class, 'batch_id');
    }
}
