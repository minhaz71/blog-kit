<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner rule: CUSTOMERS only need a simple 6+ character password — no
 * symbols/mixed-case demands on shoppers. Staff accounts stay strict (10+,
 * enforced in StaffUserResource).
 */
class CustomerPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function registerWith(string $password)
    {
        return $this->post('/register', [
            'name' => 'Test Shopper',
            'email' => 'shopper@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_customer_can_register_with_a_simple_six_character_password(): void
    {
        $response = $this->registerWith('123456');

        $response->assertSessionDoesntHaveErrors('password');
        $this->assertNotNull(User::where('email', 'shopper@example.com')->first());
    }

    public function test_five_characters_is_still_too_short(): void
    {
        $response = $this->registerWith('12345');

        $response->assertSessionHasErrors('password');
        $this->assertNull(User::where('email', 'shopper@example.com')->first());
    }

    public function test_staff_form_still_requires_ten_characters(): void
    {
        // The staff password floor is a code-level rule on the Filament field —
        // assert it hasn't been quietly loosened.
        $source = file_get_contents(app_path('Filament/Resources/StaffUserResource.php'));

        $this->assertStringContainsString('->minLength(10)', $source);
    }
}
