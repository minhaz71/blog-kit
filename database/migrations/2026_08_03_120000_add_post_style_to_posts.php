<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-post layout style override. Nullable: null means "use the site default"
 * (Admin → Appearance → Blog post style). Additive and non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'post_style')) {
                $table->string('post_style')->nullable()->after('show_toc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (Schema::hasColumn('posts', 'post_style')) {
                $table->dropColumn('post_style');
            }
        });
    }
};
