<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['posts', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes();
            });
        }

        foreach (['products', 'categories', 'posts', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('custom_css_file')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }

        foreach (['products', 'categories', 'posts', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('custom_css_file');
            });
        }
    }
};
