<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the cluster + funnel intelligence SURVIVE publish.
 *
 * Until now the "Content Cluster & Funnel Builder" stored cluster / role /
 * funnel_stage on `blog_topic_ideas` (the ideation stage), but that context was
 * dropped when an idea became a Post. These additive, nullable columns carry it
 * onto the published article so internal linking, pillar pages, thumbnails and
 * reporting can all read a post's place in the content map.
 *
 * Everything here is additive + nullable and guarded with hasColumn/hasTable —
 * pulling this on a live site cannot drop data or break existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'cluster')) {
                $table->string('cluster')->nullable()->index();          // cluster NAME (denormalized for fast filtering)
            }
            if (! Schema::hasColumn('posts', 'content_cluster_id')) {
                $table->unsignedBigInteger('content_cluster_id')->nullable()->index(); // canonical cluster
            }
            if (! Schema::hasColumn('posts', 'content_role')) {
                $table->string('content_role', 20)->nullable();          // pillar | spoke (null = not planned)
            }
            if (! Schema::hasColumn('posts', 'funnel_stage')) {
                $table->string('funnel_stage', 20)->nullable()->index(); // top | middle | bottom
            }
            if (! Schema::hasColumn('posts', 'primary_keyword')) {
                $table->string('primary_keyword')->nullable();
            }
            if (! Schema::hasColumn('posts', 'pillar_post_id')) {
                $table->unsignedBigInteger('pillar_post_id')->nullable()->index(); // a spoke points at its pillar post
            }
        });

        // Canonical cluster registry — stops cluster NAMES drifting across
        // research runs and gives each cluster's pillar a home + a shared
        // visual identity for thumbnails.
        if (! Schema::hasTable('content_clusters')) {
            Schema::create('content_clusters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id')->nullable()->index(); // multisite-aware (null = this install)
                $table->string('name');
                $table->string('slug')->index();
                $table->unsignedBigInteger('pillar_post_id')->nullable()->index();
                $table->string('primary_keyword')->nullable();
                $table->text('description')->nullable();
                $table->string('thumbnail_style')->nullable();  // per-cluster look (Phase 7)
                $table->string('brand_hint')->nullable();       // optional color/theme hint for thumbnails
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            foreach (['cluster', 'content_cluster_id', 'content_role', 'funnel_stage', 'primary_keyword', 'pillar_post_id'] as $col) {
                if (Schema::hasColumn('posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('content_clusters');
    }
};
