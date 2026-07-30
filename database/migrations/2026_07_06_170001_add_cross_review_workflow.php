<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table): void {
            // Reviewer runs on a SEPARATE (cheap) provider from the writer.
            $table->string('reviewer_provider')->default('openai')->after('model');
            $table->string('reviewer_model')->nullable()->after('reviewer_provider');
            // Do not publish until the reviewer's blocking issues are resolved.
            $table->boolean('require_approval')->default(true)->after('publish_mode');
        });

        Schema::table('ai_import_items', function (Blueprint $table): void {
            $table->text('review_summary')->nullable();      // last critique digest
            $table->unsignedTinyInteger('open_issues')->default(0);
            $table->string('preview_url')->nullable();        // final content link
        });

        // Saved, reusable fixing instructions (per item + a rolling batch digest).
        Schema::create('ai_fix_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_import_batches')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('ai_import_items')->nullOnDelete();
            $table->string('scope')->default('item');   // item | batch
            $table->string('label')->nullable();
            $table->text('instructions');
            $table->unsignedSmallInteger('issue_count')->default(0);
            $table->unsignedInteger('reused_count')->default(0);
            $table->timestamps();
            $table->index(['batch_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_fix_prompts');
        Schema::table('ai_import_items', function (Blueprint $table): void {
            $table->dropColumn(['review_summary', 'open_issues', 'preview_url']);
        });
        Schema::table('ai_import_batches', function (Blueprint $table): void {
            $table->dropColumn(['reviewer_provider', 'reviewer_model', 'require_approval']);
        });
    }
};
