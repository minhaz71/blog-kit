<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->index(); // general, seo, security, payments, shipping, emails, performance
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['group', 'key']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // order_confirmed, order_processing, ...
            $table->string('name');
            $table->string('subject');
            $table->string('heading')->nullable();
            $table->longText('body'); // HTML with {{variables}}
            $table->string('recipient')->default('customer'); // customer, admin
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_name');
            $table->string('path', 700);
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->string('caption')->nullable();
            $table->string('folder')->default('/')->index();
            $table->string('webp_path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // database, files, full
            $table->string('path', 700);
            $table->string('disk')->default('local');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status')->default('completed'); // running, completed, failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('media');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('settings');
    }
};
