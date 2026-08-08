<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real keyword & topic research — the new FRONT of the content pipeline. An
 * admin pastes up to 100 seed keywords; the research layer expands them, pulls
 * real volume/difficulty/SERP/People-Also-Ask (DataForSEO, with a free Google
 * fallback), clusters by SERP overlap, assigns funnel stage + intent, and the
 * chosen terms become blog_topic_ideas that flow into the existing writer →
 * linker → category machine.
 *
 * Two tables: a run (one research session) and its terms (the keyword universe
 * with evidence). Additive + guarded — pull-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keyword_research_runs')) {
            Schema::create('keyword_research_runs', function (Blueprint $table) {
                $table->id();
                $table->string('label')->nullable();          // niche / friendly name
                $table->json('seeds');                          // the pasted seed keywords (<=100)
                $table->string('provider')->default('auto');    // dataforseo | google | llm | auto
                $table->string('target_country')->nullable();   // e.g. "United Arab Emirates"
                $table->string('target_language')->nullable();  // e.g. "en"
                $table->unsignedSmallInteger('location_code')->nullable(); // DataForSEO location code
                $table->string('status', 20)->default('queued')->index(); // queued|researching|clustered|planned|failed
                $table->unsignedInteger('terms_count')->default(0);
                $table->unsignedInteger('clusters_count')->default(0);
                $table->text('notes')->nullable();              // last message / error
                $table->foreignId('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keyword_research_terms')) {
            Schema::create('keyword_research_terms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('run_id')->index();
                $table->string('keyword');
                $table->string('normalized')->index();          // lowercased/sorted tokens — dedupe
                $table->string('source', 20)->default('seed');  // seed|related|question|autocomplete
                $table->unsignedInteger('volume')->nullable();  // monthly searches
                $table->unsignedTinyInteger('difficulty')->nullable(); // 0-100
                $table->decimal('cpc', 8, 2)->nullable();
                $table->string('intent', 20)->nullable();       // informational|commercial|transactional|navigational
                $table->json('serp')->nullable();               // top-result URL snapshot (SERP-overlap clustering)
                $table->string('cluster')->nullable()->index();
                $table->string('role', 20)->nullable();         // pillar|spoke
                $table->string('funnel_stage', 20)->nullable(); // top|middle|bottom
                $table->float('opportunity')->nullable();       // ranking score
                $table->boolean('chosen')->default(true);
                $table->string('status', 20)->default('new')->index(); // new|planned|skipped
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_research_terms');
        Schema::dropIfExists('keyword_research_runs');
    }
};
