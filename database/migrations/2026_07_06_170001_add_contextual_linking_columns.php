<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table): void {
            // Snapshot of product names + live URLs sent to the AI once per
            // batch (inside the cached system prompt) for contextual linking.
            $table->json('link_catalog')->nullable();
        });

        Schema::table('ai_import_items', function (Blueprint $table): void {
            // Slug reserved at CSV-parse time so the product's live URL is
            // known before writing starts — siblings can link to it.
            $table->string('reserved_slug')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', fn (Blueprint $table) => $table->dropColumn('link_catalog'));
        Schema::table('ai_import_items', fn (Blueprint $table) => $table->dropColumn('reserved_slug'));
    }
};
