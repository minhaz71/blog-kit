<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Public author identity for E-E-A-T bylines + Person schema.
            $table->string('job_title')->nullable()->after('name');
            $table->text('bio')->nullable()->after('job_title');
            $table->string('avatar')->nullable()->after('bio');
            $table->json('social_links')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'bio', 'avatar', 'social_links']);
        });
    }
};
