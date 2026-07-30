<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_topic_ideas', function (Blueprint $table) {
            $table->json('compared_product_ids')->nullable()->after('link_targets');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('compared_product_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_topic_ideas', function (Blueprint $table) {
            $table->dropColumn('compared_product_ids');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('compared_product_ids');
        });
    }
};
