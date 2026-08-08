<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make keyword research + the topic-idea waiting area MULTISITE-aware: a run (and
 * each idea it produces) can target a connected spoke site instead of this
 * install. null = local. The plan → write → push-to-spoke half is already
 * per-site capable; this threads the target from the research stage through.
 *
 * Additive + nullable + guarded — pull-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_research_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('keyword_research_runs', 'site_id')) {
                $table->unsignedBigInteger('site_id')->nullable()->index(); // connected spoke, null = local
            }
        });

        Schema::table('blog_topic_ideas', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_topic_ideas', 'site_id')) {
                $table->unsignedBigInteger('site_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('keyword_research_runs', function (Blueprint $table) {
            if (Schema::hasColumn('keyword_research_runs', 'site_id')) {
                $table->dropColumn('site_id');
            }
        });
        Schema::table('blog_topic_ideas', function (Blueprint $table) {
            if (Schema::hasColumn('blog_topic_ideas', 'site_id')) {
                $table->dropColumn('site_id');
            }
        });
    }
};
