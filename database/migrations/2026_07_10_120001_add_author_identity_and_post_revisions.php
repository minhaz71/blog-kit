<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Public author identity, fully decoupled from the login account:
            // the URL slug is random (never the login name/email) and the
            // byline name can differ from the account name.
            $table->string('public_slug', 24)->nullable()->unique()->after('name');
            $table->string('display_name')->nullable()->after('public_slug');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('last_edited_by')->nullable()->after('author_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // Who made the change that REPLACED this snapshot; null = AI/system.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->timestamp('created_at');
            $table->index(['post_id', 'created_at']);
        });

        // Every existing user gets a random public slug immediately.
        foreach (DB::table('users')->whereNull('public_slug')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update([
                'public_slug' => strtolower(Str::random(12)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_revisions');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['public_slug', 'display_name']);
        });
    }
};
