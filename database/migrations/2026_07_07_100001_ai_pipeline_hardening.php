<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            // SEO image metadata the AI already writes — now stored, not discarded.
            $table->string('title')->nullable()->after('alt');
            $table->string('caption')->nullable()->after('title');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            // Anthropic bills cache WRITES at 1.25× input rate — tracked
            // separately so dashboard costs match the real invoice.
            $table->unsignedInteger('cache_write_tokens')->default(0)->after('cached_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn(['title', 'caption']));
        Schema::table('ai_usage_logs', fn (Blueprint $table) => $table->dropColumn('cache_write_tokens'));
    }
};
