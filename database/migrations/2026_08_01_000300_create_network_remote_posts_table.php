<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: the hub's local mirror of posts pulled from each connected site,
 * so the admin can browse and filter all sites' posts from one table without
 * hitting every site on every page load. Refreshed by the pull job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_remote_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('connected_sites')->cascadeOnDelete();
            $table->unsignedBigInteger('remote_post_id');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('url')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->string('category_name')->nullable();
            $table->string('author_name')->nullable();
            $table->text('excerpt')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'remote_post_id']);
        });

        Schema::table('connected_sites', function (Blueprint $table) {
            $table->timestamp('posts_synced_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('connected_sites', function (Blueprint $table) {
            $table->dropColumn('posts_synced_at');
        });

        Schema::dropIfExists('network_remote_posts');
    }
};
