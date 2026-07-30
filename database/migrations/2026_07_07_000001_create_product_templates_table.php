<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            // Ordered list of typed blocks (Filament Builder output).
            $table->json('blocks')->nullable();
            // Global template settings: schema toggles, container width, image quality.
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            // Optional per-product override; null → the default template.
            $table->foreignId('product_template_id')->nullable()->after('status')
                ->constrained('product_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_template_id');
        });

        Schema::dropIfExists('product_templates');
    }
};
