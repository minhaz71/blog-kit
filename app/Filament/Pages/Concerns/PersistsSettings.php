<?php

namespace App\Filament\Pages\Concerns;

use App\Models\AuditLog;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

/**
 * Mixed into Filament settings pages. Loads/saves flat `group.key` values into
 * the `settings` table, invalidates the group cache, and writes an audit trail.
 */
trait PersistsSettings
{
    /** Concrete pages return the group prefix ("general", "seo", ...). */
    abstract protected function settingsGroup(): string;

    /** Concrete pages list the keys they manage. */
    abstract protected function settingsKeys(): array;

    protected function loadSettings(): void
    {
        $group = $this->settingsGroup();
        $values = Setting::group($group);
        $data = [];
        foreach ($this->settingsKeys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        $this->form->fill($data);
    }

    public function save(): void
    {
        $group = $this->settingsGroup();
        $data = $this->form->getState();

        $old = [];
        foreach (array_keys($data) as $key) {
            $old[$key] = Setting::get("{$group}.{$key}");
        }

        foreach ($data as $key => $value) {
            Setting::set("{$group}.{$key}", $value);
        }

        Cache::forget("settings.{$group}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => "settings:{$group}",
            'old_values' => $old,
            'new_values' => $data,
            'ip_address' => request()->ip(),
        ]);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
