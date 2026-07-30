<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit + undo backbone for the site-wide Find & Replace tool. Each run stores
 * a batch (what was searched/replaced, by whom, where) plus a per-field
 * snapshot of the BEFORE value, so any run can be reverted exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_replace_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('find');
            $table->text('replace');
            $table->boolean('case_sensitive')->default(true);
            $table->boolean('whole_word')->default(false);
            $table->json('scopes')->nullable();          // scope keys searched
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedInteger('occurrences_count')->default(0);
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('content_replace_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('content_replace_batches')->cascadeOnDelete();
            $table->string('table_name');
            $table->string('column_name');
            $table->unsignedBigInteger('record_id');
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamps();

            $table->index(['batch_id']);
            $table->index(['table_name', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_replace_items');
        Schema::dropIfExists('content_replace_batches');
    }
};
