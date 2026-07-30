<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Customer-facing auth: password reset delivery, registration, and the
 * "no account yet — please register" guidance for unknown emails.
 */
class CustomerAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_to_an_existing_customer(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'shopper@example.com', 'is_active' => true]);

        $this->post('/forgot-password', ['email' => 'shopper@example.com'])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_for_unknown_email_guides_to_register(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('no_account', true)
            ->assertSessionHasInput('email', 'nobody@example.com');

        Notification::assertNothingSent();
    }

    public function test_login_with_unknown_email_guides_to_register(): void
    {
        $this->post('/login', ['email' => 'ghost@example.com', 'password' => 'whatever123'])
            ->assertSessionHas('no_account', true);

        $this->assertGuest();
    }

    public function test_login_with_wrong_password_stays_neutral(): void
    {
        User::factory()->create([
            'email' => 'real@example.com',
            'password' => 'correct-horse-battery',
            'is_active' => true,
        ]);

        $response = $this->post('/login', ['email' => 'real@example.com', 'password' => 'wrong-password']);

        // Existing account → generic credentials error, NOT the register CTA.
        $response->assertSessionHasErrors('email');
        $response->assertSessionMissing('no_account');
        $this->assertGuest();
    }

    public function test_customer_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Shopper',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('account.dashboard'));
        $this->assertauthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'name' => 'New Shopper']);
    }

    public function test_register_prefills_email_from_query(): void
    {
        $this->get(route('register', ['email' => 'prefill@example.com']))
            ->assertOk()
            ->assertSee('value="prefill@example.com"', false);
    }
}
