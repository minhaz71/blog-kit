<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: batch-level default target sites for network fan-out. Each written
 * article is also pushed to these connected sites, unless the CSV row carries
 * its own `site_ids` (which overrides this per row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->json('network_site_ids')->nullable()->after('link_catalog');
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->dropColumn('network_site_ids');
        });
    }
};
