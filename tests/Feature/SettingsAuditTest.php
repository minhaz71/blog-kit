<?php

namespace Tests\Feature;

use App\Filament\Pages\GeneralSettings;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_settings_writes_an_audit_log_with_subject(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        Livewire::test(GeneralSettings::class)
            ->fillForm([
                'site_name' => 'ShopKit',
                'currency' => 'AED',
                'currency_symbol' => 'AED',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $log = AuditLog::latest('id')->first();

        $this->assertNotNull($log, 'Saving settings should write an audit log.');
        $this->assertSame('settings_changed', $log->action);
        $this->assertSame('settings:general', $log->subject);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('AED', setting('general.currency'));
    }
}
