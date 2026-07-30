<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            // Refresh batch: items point at EXISTING products/posts; the
            // writer analyzes + rewrites the current copy in place instead
            // of creating new records.
            $table->boolean('refresh')->default(false)->after('funnel_rounds');
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', fn (Blueprint $table) => $table->dropColumn('refresh'));
    }
};
