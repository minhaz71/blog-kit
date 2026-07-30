<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Auto-saved fixing instructions produced by the reviewer. Item-scope rows
 * record what a specific product needed; the batch-scope row is a rolling
 * digest of recurring issues, fed back into the writer so later products in
 * the batch pre-empt the same mistakes (fewer review cycles, lower cost).
 */
class AiFixPrompt extends Model
{
    use MassPrunable;

    protected $guarded = [];

    /**
     * Item-scope rows are working data — prune after 60 days. Batch-scope
     * digests are kept (they document what a batch learned).
     */
    public function prunable()
    {
        return static::where('scope', 'item')->where('created_at', '<', now()->subDays(60));
    }

    public function batch()
    {
        return $this->belongsTo(AiImportBatch::class, 'batch_id');
    }

    public function item()
    {
        return $this->belongsTo(AiImportItem::class, 'item_id');
    }
}
