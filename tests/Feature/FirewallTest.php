<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\FirewallLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirewallTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_injection_query_string_is_blocked(): void
    {
        $response = $this->get('/?id=1%27%20OR%20%271%27=%271');
        $this->assertContains($response->status(), [403, 400], 'SQLi patterns should be blocked (got '.$response->status().').');

        $this->assertGreaterThan(0, FirewallLog::where('rule', 'sqli')->count(), 'Firewall log entry should be written.');
    }

    public function test_bad_bot_user_agent_is_blocked(): void
    {
        $response = $this->withHeaders(['User-Agent' => 'sqlmap/1.5'])->get('/');
        $this->assertContains($response->status(), [403, 400]);
    }

    public function test_blocked_ip_is_denied(): void
    {
        BlockedIp::create(['ip_address' => '127.0.0.1', 'reason' => 'test']);

        $response = $this->get('/');
        $this->assertContains($response->status(), [403, 400]);
    }

    public function test_sensitive_file_probe_is_blocked(): void
    {
        $response = $this->get('/.env');
        $this->assertContains($response->status(), [403, 400, 404]);
    }

    /**
     * Admin + Livewire payloads legitimately contain HTML/<script> (rich
     * editor content, Custom code fields). The firewall must NOT block or
     * auto-ban those - that locked the store owner out of their own site
     * ("product not saving" + every page erroring, with no error-log trace).
     */
    public function test_admin_and_livewire_payloads_are_exempt_from_pattern_blocking(): void
    {
        $payload = ['content' => '<script>alert(1)</script><table><tr><td>x</td></tr></table>'];
        $livewireUpdateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

        foreach (['/admin/products/1', '/'.$livewireUpdateUri] as $path) {
            $response = $this->post($path, $payload);
            $this->assertNotSame(403, $response->status(), "{$path} must not be firewall-blocked.");
        }

        $this->assertSame(0, \App\Models\FirewallLog::where('rule', 'xss')->count(), 'No firewall strike may be recorded for admin/livewire saves.');
        $this->assertSame(0, \App\Models\BlockedIp::count(), 'The admin IP must never be auto-banned by its own saves.');
    }

    public function test_public_route_payloads_are_still_inspected(): void
    {
        $response = $this->post('/contact', ['message' => '<script>alert(1)</script>']);
        $this->assertContains($response->status(), [403, 400], 'Public routes keep XSS payload blocking.');
    }
}
