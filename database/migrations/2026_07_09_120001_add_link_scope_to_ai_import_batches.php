<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            // What the writer may internally link to:
            // 'ecommerce' = products + product categories + posts + blog categories + home page
            // 'blog_only' = posts + blog categories only (content-site mode)
            $table->string('link_scope')->default('ecommerce')->after('link_catalog');
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->dropColumn('link_scope');
        });
    }
};
