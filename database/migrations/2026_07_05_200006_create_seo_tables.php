<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic SEO metadata: products, categories, posts, pages, brands
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->morphs('metable');
            $table->string('title')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('focus_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('nofollow')->default(false);
            $table->boolean('noarchive')->default(false);
            $table->boolean('nosnippet')->default(false);
            $table->integer('max_snippet')->nullable();
            $table->string('max_image_preview')->nullable(); // none, standard, large
            $table->integer('max_video_preview')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_title')->nullable();
            $table->string('twitter_description', 500)->nullable();
            $table->string('twitter_image')->nullable();
            $table->string('schema_type')->nullable(); // override default schema type
            $table->json('schema_overrides')->nullable();
            $table->boolean('schema_enabled')->default(true);
            $table->unsignedTinyInteger('seo_score')->default(0);
            $table->json('seo_analysis')->nullable(); // per-check results
            $table->timestamps();
            $table->unique(['metable_type', 'metable_id']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index();
            $table->string('target')->nullable(); // null for 410
            $table->unsignedSmallInteger('status_code')->default(301); // 301, 302, 307, 410
            $table->boolean('is_regex')->default(false);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url')->index();
            $table->string('referrer', 1000)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->unsignedBigInteger('hits')->default(1);
            $table->timestamp('last_hit_at');
            $table->foreignId('redirect_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // When a slug changes, keep history so old URLs 301 to the new slug
        Schema::create('slug_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('sluggable');
            $table->string('old_slug')->index();
            $table->timestamps();
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query')->index();
            $table->unsignedInteger('results_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('slug_histories');
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('seo_meta');
    }
};
