<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the hub writes an article for a CONNECTED SITE ONLY, it keeps a hidden
 * DRAFT locally (so it never shows on the hub's own blog) — but the copy pushed
 * to the spoke must carry the REAL publish intent (published, or scheduled for a
 * future date) so it actually goes live there, on schedule. These nullable
 * columns hold that intended status/date for the network payload, independent
 * of the hub's local draft status.
 *
 * Additive + nullable + guarded — pull-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'push_status')) {
                $table->string('push_status', 20)->nullable(); // published|scheduled — the status to send to spokes
            }
            if (! Schema::hasColumn('posts', 'push_published_at')) {
                $table->timestamp('push_published_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            foreach (['push_status', 'push_published_at'] as $col) {
                if (Schema::hasColumn('posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
