<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror each spoke post's funnel identity (stage/role/cluster) into the hub's
 * remote-post mirror, so the internal-link planner can apply funnel rules
 * (spoke→pillar, top/middle→money) to a SPOKE's own content — not just the
 * hub's. Additive + nullable: pulling this on a live site never touches data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_remote_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('network_remote_posts', 'funnel_stage')) {
                $table->string('funnel_stage')->nullable()->after('category_name');
            }
            if (! Schema::hasColumn('network_remote_posts', 'content_role')) {
                $table->string('content_role')->nullable()->after('funnel_stage');
            }
            if (! Schema::hasColumn('network_remote_posts', 'cluster')) {
                $table->string('cluster')->nullable()->after('content_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('network_remote_posts', function (Blueprint $table) {
            foreach (['funnel_stage', 'content_role', 'cluster'] as $col) {
                if (Schema::hasColumn('network_remote_posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
