<?php

namespace App\Support;

use App\Models\ConnectedSite;

/**
 * Version-compatibility check between THIS hub and a connected spoke. A hub on a
 * newer version can push payload fields (schema, taxonomies, new columns) an
 * older spoke can't store — so content may transfer only partially. This gates
 * network operations with a clear "update first" recommendation instead of
 * silently losing data.
 */
class NetworkCompat
{
    public const OK = 'ok';

    public const OUTDATED = 'outdated'; // spoke older than hub

    public const AHEAD = 'ahead';       // spoke newer than hub

    public const UNKNOWN = 'unknown';

    /**
     * @return array{status:string, ok:bool, message:string, hub:string, spoke:?string}
     */
    public static function check(ConnectedSite $site): array
    {
        $hub = Version::core();
        $spoke = trim((string) $site->remote_version);

        if ($spoke === '') {
            return self::result(self::UNKNOWN, true, "Version unknown for {$site->name} — run Test to fetch it.", $hub, null);
        }

        $cmp = version_compare($spoke, $hub);

        if ($cmp === 0) {
            return self::result(self::OK, true, "Up to date (v{$hub}).", $hub, $spoke);
        }

        if ($cmp < 0) {
            return self::result(
                self::OUTDATED, false,
                "{$site->name} is on v{$spoke}; this hub is on v{$hub}. Update the site first — newer fields may not transfer until it matches.",
                $hub, $spoke,
            );
        }

        return self::result(
            self::AHEAD, false,
            "{$site->name} is on v{$spoke}, newer than this hub (v{$hub}). Update this hub first.",
            $hub, $spoke,
        );
    }

    /** Filament badge color for a compat status. */
    public static function color(string $status): string
    {
        return match ($status) {
            self::OK => 'success',
            self::OUTDATED => 'danger',
            self::AHEAD => 'warning',
            default => 'gray',
        };
    }

    /** Short label for a compat status. */
    public static function label(string $status): string
    {
        return match ($status) {
            self::OK => 'up to date',
            self::OUTDATED => 'update needed',
            self::AHEAD => 'hub outdated',
            default => 'unknown',
        };
    }

    private static function result(string $status, bool $ok, string $message, string $hub, ?string $spoke): array
    {
        return compact('status', 'ok', 'message', 'hub', 'spoke');
    }
}
