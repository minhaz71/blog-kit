<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (two-way sync): store the spoke's current content hash on both the
 * mirror row and the push link so the hub can detect when a spoke post has
 * been edited independently of what the hub last pushed (a conflict).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_remote_posts', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('excerpt');
        });

        Schema::table('network_post_links', function (Blueprint $table) {
            // The spoke's current content hash at the last pull (vs content_hash
            // = what the hub last pushed). Divergence ⇒ conflict.
            $table->string('remote_hash', 64)->nullable()->after('content_hash');
            $table->timestamp('conflict_detected_at')->nullable()->after('last_pushed_at');
        });
    }

    public function down(): void
    {
        Schema::table('network_remote_posts', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });

        Schema::table('network_post_links', function (Blueprint $table) {
            $table->dropColumn(['remote_hash', 'conflict_detected_at']);
        });
    }
};
