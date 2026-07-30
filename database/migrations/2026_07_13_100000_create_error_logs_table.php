<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-only application error log. Deduped by fingerprint so a
        // recurring bug is ONE row with an occurrence counter, not thousands.
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 32)->unique(); // class|file|line|normalized-message
            $table->string('level', 20)->default('error');
            $table->string('exception_class')->nullable();
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('method', 10)->nullable();
            $table->text('url')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->longText('trace')->nullable();          // admin-only technical detail
            $table->unsignedInteger('occurrences')->default(1);
            $table->boolean('resolved')->default(false)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
