<?php

namespace App\Services\Network;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * This install's OWN network credentials — the key + shared secret a hub uses
 * to address it as a spoke. Stored in settings (secret encrypted). Displayed
 * once on the Network settings page so the operator can paste both into the
 * hub's "Add site" form; the spoke keeps the secret to verify inbound
 * signatures.
 */
class NetworkIdentity
{
    public static function key(): ?string
    {
        return Setting::get('network.api_key');
    }

    public static function secret(): ?string
    {
        return Setting::get('network.api_secret');
    }

    /** Ensure a credential pair exists; returns [key, secret]. */
    public static function ensure(): array
    {
        if (! self::key() || ! self::secret()) {
            return self::regenerate();
        }

        return [self::key(), self::secret()];
    }

    /** Rotate (or first-time create) the credential pair. Invalidates the old one. */
    public static function regenerate(): array
    {
        $key = 'bk_'.Str::lower(Str::random(24));
        $secret = 'sk_'.Str::random(48);

        Setting::set('network.api_key', $key);
        Setting::set('network.api_secret', $secret); // encrypted at the Setting layer if configured

        return [$key, $secret];
    }
}
