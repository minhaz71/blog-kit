<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate content mode for a blog batch: injects the affiliate/FTC rulebook
 * + a disclosure into the writer, and turns on mechanical rel="sponsored
 * nofollow" enforcement at publish. Per-row affiliate_links (CSV) supplies the
 * actual products/URLs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->boolean('affiliate_mode')->default(false)->after('image_style');
            $table->text('affiliate_disclosure')->nullable()->after('affiliate_mode');
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->dropColumn(['affiliate_mode', 'affiliate_disclosure']);
        });
    }
};
