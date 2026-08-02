<?php

use App\Services\Network\NetworkTargets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site cost tracking (S5). Each article item records which site it was
 * written FOR — 'local', a spoke ID, or 'shared' (targets more than one) — so
 * the Live Monitor can break spend down per site. Backfilled from each item's
 * existing row.site_ids so historical batches attribute correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_items', function (Blueprint $table) {
            $table->string('site_key')->nullable()->after('status')->index();
        });

        DB::table('ai_import_items')->orderBy('id')->chunkById(500, function ($items) {
            foreach ($items as $item) {
                $row = json_decode((string) $item->row, true);
                $value = is_array($row) ? ($row['site_ids'] ?? null) : null;

                DB::table('ai_import_items')
                    ->where('id', $item->id)
                    ->update(['site_key' => NetworkTargets::siteKey($value)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_items', function (Blueprint $table) {
            $table->dropColumn('site_key');
        });
    }
};
