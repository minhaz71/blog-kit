<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The phrase dictionary: every way a product/post can be mentioned.
        // kind: phrase (consecutive words) | set (order-independent token
        // set, stored sorted) | single (one unique token, review-only).
        Schema::create('link_targets', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 60);
            $table->unsignedBigInteger('target_id');
            $table->string('phrase', 180);
            $table->string('kind', 10)->default('phrase');
            $table->unsignedSmallInteger('weight')->default(50);
            $table->boolean('is_ambiguous')->default(false);
            $table->index(['kind', 'phrase']);
            $table->index(['target_type', 'target_id']);
        });

        // Suggestions the admin reviews. NOTHING auto-applies.
        Schema::create('link_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('source_field', 30)->default('description');
            $table->string('target_type', 60);
            $table->unsignedBigInteger('target_id');
            $table->string('anchor', 180);
            $table->unsignedSmallInteger('occurrence')->default(1);
            $table->string('sentence', 500)->nullable();
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('status', 12)->default('pending')->index();
            $table->timestamp('applied_at')->nullable();
            $table->char('fingerprint', 32)->unique(); // md5(source+target+anchor+occurrence)
            $table->timestamps();
            $table->index(['source_type', 'source_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_suggestions');
        Schema::dropIfExists('link_targets');
    }
};
