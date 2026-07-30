<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            // Delay between one article's publish time and the next
            // (staggered scheduling). Null = publish immediately.
            $table->unsignedInteger('publish_interval_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', fn (Blueprint $table) => $table->dropColumn('publish_interval_minutes'));
    }
};
