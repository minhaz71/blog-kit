<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\GmailOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GmailMailSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    protected function configureClient(): void
    {
        Setting::set('emails.gmail_client_id', 'client-123.apps.googleusercontent.com');
        Setting::set('emails.gmail_client_secret', 'secret-xyz');
    }

    public function test_connect_redirects_to_google_consent_with_offline_access(): void
    {
        $this->configureClient();

        $response = $this->actingAs($this->staff())->get('/hmmail/connect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $location);
        $this->assertStringContainsString('client-123.apps.googleusercontent.com', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertStringContainsString(urlencode('gmail.send'), $location);
        $this->assertStringContainsString(urlencode(url('/hmmail/callback')), $location);
    }

    public function test_oauth_endpoints_are_staff_only(): void
    {
        $this->configureClient();

        // Guests are pushed to login; plain customers are rejected.
        $this->get('/hmmail/connect')->assertRedirect();

        $customer = User::factory()->create(['is_active' => true]); // no role
        $this->actingAs($customer)->get('/hmmail/connect')->assertForbidden();
        $this->actingAs($customer)->get('/hmmail/callback?code=x&state=y')->assertForbidden();
    }

    public function test_callback_stores_refresh_token_and_account_email(): void
    {
        $this->configureClient();

        $idPayload = rtrim(strtr(base64_encode(json_encode(['email' => 'shop@tereahub.ae'])), '+/', '-_'), '=');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'at-1',
                'refresh_token' => 'rt-1',
                'id_token' => 'h.'.$idPayload.'.s',
            ]),
        ]);

        $staff = $this->staff();

        $this->actingAs($staff)
            ->withSession(['hmmail.state' => 'state-abc'])
            ->get('/hmmail/callback?code=auth-code&state=state-abc')
            ->assertRedirect();

        $this->assertTrue(GmailOAuth::connected());
        $this->assertSame('rt-1', setting('emails.gmail_refresh_token'));
        $this->assertSame('shop@tereahub.ae', GmailOAuth::connectedEmail());

        // Forged/expired state must be rejected and store nothing.
        GmailOAuth::disconnect();
        $this->actingAs($staff)
            ->withSession(['hmmail.state' => 'real-state'])
            ->get('/hmmail/callback?code=auth-code&state=WRONG')
            ->assertRedirect();
        $this->assertFalse(GmailOAuth::connected());
    }

    public function test_mail_sends_through_the_gmail_api_transport(): void
    {
        $this->configureClient();
        Setting::set('emails.gmail_refresh_token', 'rt-live');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token']),
            'gmail.googleapis.com/*' => Http::response(['id' => 'msg-1'], 200),
        ]);

        config(['mail.default' => 'gmail', 'mail.mailers.gmail' => ['transport' => 'gmail']]);

        Mail::raw('Order #1001 confirmed — thank you!', fn ($m) => $m->to('buyer@example.com')->subject('Order confirmed'));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'gmail.googleapis.com/gmail/v1/users/me/messages/send')) {
                return false;
            }

            // Bearer token + base64url RFC-2822 payload containing our mail.
            $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

            return $request->hasHeader('Authorization', 'Bearer fresh-token')
                && str_contains($raw, 'buyer@example.com')
                && str_contains($raw, 'Order confirmed');
        });
    }
}
