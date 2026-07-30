<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_link_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('label');                    // e.g. "Homepage"
            $table->string('url');                      // root-relative, e.g. "/"
            $table->json('anchor_phrases');             // ["TEREA Dubai", "TEREA UAE", ...]
            $table->unsignedTinyInteger('weight')->default(70);
            $table->unsignedSmallInteger('max_links')->default(15); // site-wide cap
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_link_targets');
    }
};
