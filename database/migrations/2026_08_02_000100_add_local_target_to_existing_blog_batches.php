<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill for the new multisite "This site" checkbox.
 *
 * Before this, network_site_ids held ONLY spoke IDs and the local site was
 * always published to. Now the local site is a first-class, un-tickable-able
 * target represented by the `local` sentinel — and its ABSENCE means "do not
 * publish here". So every existing batch that already had spoke targets must
 * gain `local`, or it would silently stop publishing on this site. Empty
 * selections need no change (an empty target already resolves to local-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        $batches = DB::table('ai_import_batches')
            ->whereNotNull('network_site_ids')
            ->where('network_site_ids', '!=', '[]')
            ->where('network_site_ids', '!=', '')
            ->get(['id', 'network_site_ids']);

        foreach ($batches as $batch) {
            $ids = json_decode((string) $batch->network_site_ids, true);

            if (! is_array($ids) || $ids === []) {
                continue;
            }

            $lower = array_map(fn ($v) => strtolower((string) $v), $ids);

            // Already carries a local token → leave it untouched.
            if (array_intersect($lower, ['local', 'self', 'this', 'here', 'current', 'own', 'all'])) {
                continue;
            }

            array_unshift($ids, 'local');

            DB::table('ai_import_batches')
                ->where('id', $batch->id)
                ->update(['network_site_ids' => json_encode(array_values(array_unique($ids)))]);
        }
    }

    public function down(): void
    {
        // Non-destructive backfill — nothing to reverse.
    }
};
