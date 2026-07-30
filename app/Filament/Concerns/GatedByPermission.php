<?php

namespace App\Filament\Concerns;

use App\Support\AdminAccess;

/**
 * Gates a Filament resource/page by its AdminAccess permission. Because
 * Filament derives navigation visibility from canAccess() and enforces it on
 * page mount, applying this trait both hides the item from users who lack the
 * permission and returns 403 if they hit the URL directly. Super Admin always
 * passes via the Gate::before bypass.
 *
 * A class that declares its own canAccess() overrides this (PHP trait
 * precedence), so bespoke gates (e.g. SystemUpdates) still win.
 */
trait GatedByPermission
{
    public static function canAccess(): bool
    {
        return AdminAccess::allows(static::class);
    }
}
