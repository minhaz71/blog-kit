<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broken internal links: when a product/post is deleted, every page that
 * still links to its URL is recorded here so the admin can fix or repoint
 * those links instead of shipping a dead 404.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broken_links', function (Blueprint $table) {
            $table->id();
            // The page that CONTAINS the broken link.
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            // The deleted target (kept so restoring it auto-resolves the report).
            $table->string('target_type', 60)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('url');            // the dead URL as it appears on the page
            $table->string('anchor')->nullable();
            $table->string('reason', 30)->default('deleted'); // deleted | unpublished
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'url'], 'broken_links_source_url_unique');
            $table->index(['target_type', 'target_id']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broken_links');
    }
};
