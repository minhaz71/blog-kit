<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiImportItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'row' => 'array',
            'ai_output' => 'array',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(AiImportBatch::class, 'batch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
