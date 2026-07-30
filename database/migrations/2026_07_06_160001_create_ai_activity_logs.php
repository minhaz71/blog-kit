<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_import_batches')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('ai_import_items')->nullOnDelete();
            $table->string('level')->default('info');   // info | success | warning | error
            $table->string('stage');                    // parse | write | review | image | publish | link | finalize
            $table->string('message', 1000);
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_activity_logs');
    }
};
