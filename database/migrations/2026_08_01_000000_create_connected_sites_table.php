<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hub-side registry of the spoke installs this control panel manages. The
 * row `id` is the stable "site ID" used everywhere else (CSV site_ids column,
 * network publisher target selection). Only meaningful when the network
 * module is on and this install's role is 'hub'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_sites', function (Blueprint $table) {
            $table->id(); // the site ID (e.g. 2, 5, 34, 78) used in CSV + targeting
            $table->string('name');
            $table->string('base_url')->unique();   // https://site.example.com
            $table->string('api_key')->index();     // the spoke's public key
            $table->text('api_secret');             // shared HMAC secret (encrypted cast)
            $table->string('status')->default('pending')->index(); // pending|online|offline|error
            $table->timestamp('last_seen_at')->nullable();
            $table->string('remote_version')->nullable();
            $table->json('capabilities')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_sites');
    }
};
