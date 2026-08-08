<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give blog categories a two-level hierarchy (mother → sub) and menu controls,
 * mirroring the product Category tree, plus a link from each content cluster to
 * the sub-category it feeds. Powers the auto-taxonomy feature: clusters become
 * sub-categories grouped under AI-named mother categories, filed and menued
 * automatically.
 *
 * Additive + nullable + guarded — pulling on a live site cannot drop data or
 * break existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('post_categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')
                    ->constrained('post_categories')->nullOnDelete(); // null = mother, set = sub
            }
            if (! Schema::hasColumn('post_categories', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }
            if (! Schema::hasColumn('post_categories', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('post_categories', 'show_in_menu')) {
                $table->boolean('show_in_menu')->default(true);
            }
        });

        Schema::table('content_clusters', function (Blueprint $table) {
            if (! Schema::hasColumn('content_clusters', 'post_category_id')) {
                $table->unsignedBigInteger('post_category_id')->nullable()->index(); // the sub-category this cluster feeds
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_categories', function (Blueprint $table) {
            if (Schema::hasColumn('post_categories', 'parent_id')) {
                // Drop the FK before the column (SQLite-safe: guarded try).
                try {
                    $table->dropForeign(['parent_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('parent_id');
            }
            foreach (['sort_order', 'is_active', 'show_in_menu'] as $col) {
                if (Schema::hasColumn('post_categories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('content_clusters', function (Blueprint $table) {
            if (Schema::hasColumn('content_clusters', 'post_category_id')) {
                $table->dropColumn('post_category_id');
            }
        });
    }
};
