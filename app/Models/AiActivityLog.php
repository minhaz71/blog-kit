<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class AiActivityLog extends Model
{
    use MassPrunable;

    protected $guarded = [];

    /** Feed rows are operational, not archival — prune after 30 days. */
    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(30));
    }

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function batch()
    {
        return $this->belongsTo(AiImportBatch::class, 'batch_id');
    }

    public function item()
    {
        return $this->belongsTo(AiImportItem::class, 'item_id');
    }

    /** Fire-and-forget logger — must never break the pipeline. */
    public static function write(
        int $batchId,
        ?int $itemId,
        string $stage,
        string $message,
        string $level = 'info',
        array $context = [],
    ): void {
        try {
            self::create([
                'batch_id' => $batchId,
                'item_id' => $itemId,
                'stage' => $stage,
                'message' => mb_substr($message, 0, 1000),
                'level' => $level,
                'context' => $context ?: null,
            ]);
        } catch (\Throwable) {
            // Logging failures are swallowed by design.
        }
    }
}
