<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real-time IP blocklist — threat actors pulled from public feeds,
        // kept separate from manual/auto BlockedIp bans so a feed refresh
        // never wipes an admin's own blocks.
        Schema::create('threat_intel_ips', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('source')->default('feed');   // which feed supplied it
            $table->string('category')->nullable();       // attack type, when known
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('source');
        });

        // Unified intrusion / security event stream that powers the dashboard
        // and drives intrusion-alert emails. Distinct from firewall_logs
        // (raw hits) — this is the curated, severity-ranked, notifiable feed.
        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type');                       // login_new_location, auto_ban, malware, file_change, threat_ip, country_block, dependency_vuln, …
            $table->string('severity')->default('info');  // info | warning | high | critical
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('notified')->default(false);  // has an alert email gone out?
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();
            $table->index(['type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('threat_intel_ips');
    }
};
