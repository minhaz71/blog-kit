<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table): void {
            $table->text('system_prompt')->nullable();
            $table->unsignedTinyInteger('competitor_count')->default(3);
            $table->string('output_format')->default('html_css'); // html_css | html_plain | html_classes
            $table->text('custom_classes')->nullable();
            $table->json('allowed_tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table): void {
            $table->dropColumn(['system_prompt', 'competitor_count', 'output_format', 'custom_classes', 'allowed_tags']);
        });
    }
};
