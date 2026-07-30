<?php

namespace Tests\Feature;

use App\Services\Security\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_generated_secret_is_base32_alphabet(): void
    {
        $svc = new TotpService;
        $secret = $svc->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertGreaterThanOrEqual(20, strlen($secret));
    }

    public function test_verify_accepts_current_code_and_rejects_wrong_code(): void
    {
        $svc = new TotpService;
        $secret = $svc->generateSecret();

        $step = (int) floor(time() / 30);
        $good = $svc->generateCode($secret, $step);
        $this->assertTrue($svc->verify($secret, $good));
        $this->assertFalse($svc->verify($secret, '000000'));
    }

    public function test_verify_tolerates_one_step_drift(): void
    {
        $svc = new TotpService;
        $secret = $svc->generateSecret();
        $step = (int) floor(time() / 30);

        $prev = $svc->generateCode($secret, $step - 1);
        $next = $svc->generateCode($secret, $step + 1);

        $this->assertTrue($svc->verify($secret, $prev, window: 1));
        $this->assertTrue($svc->verify($secret, $next, window: 1));
    }

    public function test_provisioning_uri_is_well_formed(): void
    {
        $svc = new TotpService;
        $uri = $svc->provisioningUri('JBSWY3DPEHPK3PXP', 'user@example.com', 'ShopKit');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('ShopKit', $uri);
        $this->assertStringContainsString('user%40example.com', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
    }
}
