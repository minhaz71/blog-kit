<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('ai_import_batches')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('ai_import_items')->nullOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('purpose')->default('write'); // write | review
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->decimal('cost', 10, 6)->default(0);
            $table->timestamps();
            $table->index(['provider', 'model']);
            $table->index('created_at');
        });

        Schema::table('ai_import_batches', function (Blueprint $table): void {
            $table->string('target_country')->nullable();
            $table->string('target_city')->nullable();
            $table->string('target_language')->nullable();
            $table->string('audience_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::table('ai_import_batches', function (Blueprint $table): void {
            $table->dropColumn(['target_country', 'target_city', 'target_language', 'audience_note']);
        });
    }
};
