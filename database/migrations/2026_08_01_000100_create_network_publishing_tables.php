<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (network publishing).
 *  - posts.network_origin: on a SPOKE, marks a post as network-managed and
 *    records which hub + hub-post it came from ("<hub_key>:<hub_post_id>"),
 *    so a re-push updates the same post instead of duplicating it.
 *  - network_post_links: on a HUB, one row per (local post × target site)
 *    recording the remote post id, sync status, and a content hash for
 *    change detection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('network_origin')->nullable()->index()->after('status');
        });

        Schema::create('network_post_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete(); // hub-local post
            $table->foreignId('site_id')->constrained('connected_sites')->cascadeOnDelete();
            $table->unsignedBigInteger('remote_post_id')->nullable();
            $table->string('remote_slug')->nullable();
            $table->string('remote_url')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('status')->default('pending')->index(); // pending|synced|failed
            $table->timestamp('last_pushed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_post_links');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('network_origin');
        });
    }
};
