<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch-level AI thumbnail defaults. A per-row CSV "generate_image" column
 * overrides generate_images; image_style seeds the prompt when a row has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->boolean('generate_images')->default(false)->after('network_site_ids');
            $table->string('image_style')->nullable()->after('generate_images');
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->dropColumn(['generate_images', 'image_style']);
        });
    }
};
