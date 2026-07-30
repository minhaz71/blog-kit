<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('csv_path');
            $table->text('prompt');
            $table->string('provider')->default('anthropic'); // openai | anthropic | gemini
            $table->string('model')->nullable();
            $table->string('drive_folder')->nullable();
            $table->unsignedTinyInteger('review_passes')->default(3);
            $table->string('publish_mode')->default('draft');  // draft | publish
            $table->string('price_mode')->default('csv');      // csv | ai
            $table->string('status')->default('pending');      // pending | processing | linking | completed | failed
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('done_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_import_batches')->cascadeOnDelete();
            $table->json('row');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending | writing | reviewing | images | published | linked | failed
            $table->unsignedTinyInteger('passes_done')->default(0);
            $table->json('ai_output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_import_items');
        Schema::dropIfExists('ai_import_batches');
    }
};
