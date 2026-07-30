<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->string('kind')->default('product')->index()->after('name');
            $table->text('niche')->nullable()->after('prompt');
            $table->text('topic_ideas')->nullable()->after('niche');
            $table->unsignedSmallInteger('topic_count')->default(10)->after('topic_ideas');
            $table->foreignId('blog_category_id')->nullable()->after('topic_count')
                ->constrained('post_categories')->nullOnDelete();
        });

        Schema::table('ai_import_items', function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->after('product_id')
                ->constrained('posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_import_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_id');
        });

        Schema::table('ai_import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blog_category_id');
            $table->dropColumn(['kind', 'niche', 'topic_ideas', 'topic_count']);
        });
    }
};
