<?php

namespace App\Services\Security;

use App\Models\ThreatIntelIp;
use Illuminate\Support\Facades\Http;

/**
 * Real-time IP blocklist, refreshed from free public threat feeds (the
 * open-source analog of Wordfence's premium IP blocklist of known threat
 * actors). Feeds list IPs seen attacking servers worldwide; the firewall
 * blocks any match. Refreshed on a schedule by security:update-blocklist.
 */
class ThreatIntelligence
{
    /**
     * Free, no-key, plain-text IP feeds (one IP per line, '#' comments).
     * blocklist.de = attackers reported in the last 48h; FireHOL level-1 =
     * IPs that should never appear in legitimate traffic.
     */
    public const FEEDS = [
        'blocklist.de' => 'https://lists.blocklist.de/lists/all.txt',
        'firehol_level1' => 'https://raw.githubusercontent.com/firehol/blocklist-ipsets/master/firehol_level1.netset',
    ];

    /** Safety cap so a runaway feed can't exhaust the table/memory. */
    public const MAX_IPS = 50000;

    /** @return array{imported:int, feeds:array<string,int>, skipped:int} */
    public function refresh(): array
    {
        $collected = [];
        $perFeed = [];

        foreach (self::FEEDS as $name => $url) {
            $count = 0;

            try {
                $response = Http::timeout(30)->get($url);

                if (! $response->ok()) {
                    $perFeed[$name] = 0;

                    continue;
                }

                foreach (preg_split('/\r?\n/', (string) $response->body()) as $line) {
                    $line = trim($line);

                    // Skip comments/blank; take the IP (drop any CIDR suffix —
                    // we match exact IPs, and single-host /32 entries are common).
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }

                    $ip = strtok($line, '/');

                    if (filter_var($ip, FILTER_VALIDATE_IP) && ! isset($collected[$ip])) {
                        $collected[$ip] = $name;
                        $count++;

                        if (count($collected) >= self::MAX_IPS) {
                            break 2;
                        }
                    }
                }
            } catch (\Throwable) {
                // A single feed being down must not abort the whole refresh.
            }

            $perFeed[$name] = $count;
        }

        if ($collected === []) {
            return ['imported' => 0, 'feeds' => $perFeed, 'skipped' => 0];
        }

        // Replace the feed-sourced set atomically-ish: upsert new, prune stale.
        $now = now();
        $rows = [];
        foreach ($collected as $ip => $source) {
            $rows[] = ['ip_address' => $ip, 'source' => $source, 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            ThreatIntelIp::upsert($chunk, ['ip_address'], ['source', 'last_seen_at', 'updated_at']);
        }

        // Drop IPs no longer on any feed (they've aged out / reformed).
        $skipped = ThreatIntelIp::where('last_seen_at', '<', $now)->delete();

        ThreatIntelIp::flushCache();

        return ['imported' => count($collected), 'feeds' => $perFeed, 'skipped' => $skipped];
    }
}
