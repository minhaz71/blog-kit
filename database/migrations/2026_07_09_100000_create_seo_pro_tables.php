<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per internal <a> found in product/post CONTENT (menus and
        // static pages intentionally excluded — this measures editorial
        // linking, RankMath-style). Rebuilt per source on each scan.
        Schema::create('internal_links', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 60);
            $table->unsignedBigInteger('target_id');
            $table->string('anchor', 255)->nullable();
            $table->index(['source_type', 'source_id']);
            $table->index(['target_type', 'target_id']);
        });

        // Unlimited extra JSON-LD blocks: attached to one page (morph) or
        // global (null morph = every page). Injected into the @graph.
        Schema::create('custom_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->nullableMorphs('schemable');
            $table->json('json_ld');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // PageSpeed Insights snapshots per URL (cron-fetched, quota-aware).
        Schema::create('page_speed_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500);
            $table->string('strategy', 10)->default('mobile'); // mobile | desktop
            $table->unsignedTinyInteger('performance')->nullable(); // 0-100
            $table->decimal('lcp', 6, 2)->nullable();  // seconds
            $table->decimal('cls', 5, 3)->nullable();
            $table->unsignedInteger('inp')->nullable(); // ms
            $table->timestamp('fetched_at');
            $table->index(['url', 'strategy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_speed_snapshots');
        Schema::dropIfExists('custom_schemas');
        Schema::dropIfExists('internal_links');
    }
};
