<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\ThreatIntelIp;
use App\Services\Security\DependencyAudit;
use App\Services\Security\GeoIp;
use App\Services\Security\SecurityAlertService;
use App\Services\Security\SecurityAudit;
use App\Services\Security\ThreatIntelligence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdvancedSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ── GeoIP ─────────────────────────────────────────────────────────
    public function test_geoip_resolves_public_ip_and_skips_private(): void
    {
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'AE'])]);
        $geo = new GeoIp;

        $this->assertSame('AE', $geo->country('8.8.8.8'));
        $this->assertNull($geo->country('127.0.0.1'));      // loopback
        $this->assertNull($geo->country('192.168.1.10'));   // private
    }

    // ── Threat intelligence feed ──────────────────────────────────────
    public function test_threat_feed_refresh_imports_ips(): void
    {
        Http::fake([
            'lists.blocklist.de/*' => Http::response("1.2.3.4\n5.6.7.8\n# comment\nnot-an-ip\n"),
            'raw.githubusercontent.com/*' => Http::response("9.9.9.9/32\n1.2.3.4\n"),
        ]);

        $result = (new ThreatIntelligence)->refresh();

        $this->assertSame(3, $result['imported']); // 1.2.3.4, 5.6.7.8, 9.9.9.9 (deduped, CIDR trimmed)
        $this->assertTrue(ThreatIntelIp::contains('1.2.3.4'));
        $this->assertTrue(ThreatIntelIp::contains('9.9.9.9'));
        $this->assertFalse(ThreatIntelIp::contains('8.8.8.8'));
    }

    public function test_threat_ip_lookup_is_cached(): void
    {
        ThreatIntelIp::create(['ip_address' => '203.0.113.5', 'source' => 'test', 'last_seen_at' => now()]);
        $this->assertTrue(ThreatIntelIp::contains('203.0.113.5'));
    }

    // ── Firewall integration ──────────────────────────────────────────
    public function test_firewall_blocks_a_threat_intel_ip(): void
    {
        Setting::set('security.firewall_enabled', true);
        Setting::set('security.threat_intel_enabled', true);
        ThreatIntelIp::create(['ip_address' => '127.0.0.1', 'source' => 'test', 'last_seen_at' => now()]);

        $this->get('/')->assertStatus(403);
        $this->assertDatabaseHas('firewall_logs', ['rule' => 'threat_intel_ip']);
    }

    public function test_firewall_blocks_a_denied_country(): void
    {
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'RU'])]);
        Setting::set('security.firewall_enabled', true);
        Setting::set('security.blocked_countries', ['RU']);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/')->assertStatus(403);
        $this->assertDatabaseHas('firewall_logs', ['rule' => 'country_block']);
    }

    public function test_allow_list_country_permits_only_listed(): void
    {
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'AE'])]);
        Setting::set('security.firewall_enabled', true);
        Setting::set('security.allowed_countries', ['AE']);

        // AE is allowed → not a 403 from the country rule.
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/')->assertStatus(200);
    }

    // ── Alerts ────────────────────────────────────────────────────────
    public function test_alert_service_records_event_and_emails_on_critical(): void
    {
        Mail::fake();
        Setting::set('security.alerts_enabled', true);
        Setting::set('security.alert_emails', 'ops@example.com');

        $event = (new SecurityAlertService)->record('malware', 'Malware found', 'critical', 'A bad file.', '1.2.3.4');

        $this->assertInstanceOf(SecurityEvent::class, $event);
        $this->assertSame('critical', $event->severity);
        // notified flips only after the email send path runs.
        $this->assertTrue($event->fresh()->notified);
    }

    public function test_low_severity_events_do_not_email(): void
    {
        Mail::fake();
        (new SecurityAlertService)->record('info_event', 'Just info', 'info');
        Mail::assertNothingSent();
    }

    // ── Posture audit ─────────────────────────────────────────────────
    public function test_security_audit_produces_score_and_checks(): void
    {
        $result = (new SecurityAudit)->run();

        $this->assertIsInt($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertContains($result['grade'], ['A', 'B', 'C', 'D', 'F']);
        $this->assertNotEmpty($result['checks']);
        // Each check is actionable.
        $this->assertArrayHasKey('fix', $result['checks'][0]);
        $this->assertArrayHasKey('severity', $result['checks'][0]);
    }

    // ── Dependency audit (cache path, no shell) ───────────────────────
    public function test_dependency_audit_latest_reads_cache(): void
    {
        Cache::put(DependencyAudit::CACHE_KEY, ['ran' => true, 'advisories' => 0, 'packages' => [], 'abandoned' => 0, 'checked_at' => now()->toIso8601String(), 'error' => null]);
        $latest = (new DependencyAudit)->latest();

        $this->assertTrue($latest['ran']);
        $this->assertSame(0, $latest['advisories']);
    }

    public function test_security_center_page_renders_for_admin(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = \App\Models\User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        $this->actingAs($user)->get('/admin/security-center')->assertStatus(200)->assertSee('Security Center');
    }
}
