<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The funnel builder's WAITING AREA: researched, verified title
        // ideas parked until the admin selects them and sends them to the
        // blog writer. Everything the research decided is stored per idea
        // so the writer gets the full brief months later.
        Schema::create('blog_topic_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->index();       // research run that produced it
            $table->foreignId('writer_batch_id')->nullable()->index(); // blog batch it was sent to
            $table->foreignId('post_id')->nullable();                  // resulting article

            $table->string('title');
            $table->char('fingerprint', 32)->unique(); // normalized token-set hash — dedupe across runs
            $table->string('cluster')->index();
            $table->string('role', 20)->default('spoke');          // pillar | spoke
            $table->string('funnel_stage', 20)->index();           // top | middle
            $table->string('primary_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->text('pain_point')->nullable();
            $table->string('search_query')->nullable();
            $table->text('audience_need')->nullable();
            $table->text('angle')->nullable();
            $table->json('outline')->nullable();                    // table-of-contents idea
            $table->json('link_targets')->nullable();               // researched product/category/post URLs
            $table->unsignedTinyInteger('verified_rounds')->default(0);
            $table->string('status', 20)->default('waiting')->index(); // waiting|queued|written|dismissed
            $table->timestamps();
        });

        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->unsignedTinyInteger('funnel_rounds')->nullable(); // verification rounds for idea runs
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_topic_ideas');
        Schema::table('ai_import_batches', fn (Blueprint $table) => $table->dropColumn('funnel_rounds'));
    }
};
