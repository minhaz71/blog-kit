<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_renders_the_qr_as_inline_svg_not_text(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/two-factor');

        $response->assertOk();
        // A real inline <svg> element must be present…
        $response->assertSee('<svg', escape: false);
        // …and NOT the base64 data-URI string the QR library emits by
        // default, which the view would print as literal text (the bug).
        $response->assertDontSee('data:image/svg+xml;base64', escape: false);
        // Manual-entry fallback still shown.
        $response->assertSee('Enter this secret manually', escape: false);
    }
}
