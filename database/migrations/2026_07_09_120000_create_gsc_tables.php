<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Latest Search Console page performance snapshot (replaced per sync).
        Schema::create('gsc_page_stats', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 5, 2)->default(0);      // percent
            $table->decimal('position', 6, 1)->nullable();
            $table->unsignedInteger('organic_sessions')->nullable(); // GA4
            $table->unsignedSmallInteger('period_days')->default(28);
            $table->timestamp('fetched_at');
            $table->index('clicks');
        });

        // Google index status per URL (URL Inspection API, quota-limited).
        Schema::create('index_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500)->unique();
            $table->string('verdict', 40);                 // PASS | NEUTRAL | FAIL …
            $table->string('coverage', 120)->nullable();   // "Submitted and indexed" …
            $table->timestamp('last_crawl_at')->nullable();
            $table->timestamp('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_statuses');
        Schema::dropIfExists('gsc_page_stats');
    }
};
