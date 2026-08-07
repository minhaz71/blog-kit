# Security System

Hemdox BlogKit ships a Wordfence-style, multi-layer security system — an application firewall, real-time
threat intelligence, login/brute-force protection, malware + integrity scanning, dependency CVE
monitoring, intrusion alerts, and a posture-scoring dashboard. Everything is self-hosted and uses
free data sources; there is no paid tier or external service dependency.

Admin home: **Admin → Security → Security Center** (`/admin/security-center`).

---

## 1. Application firewall  `app/Http/Middleware/Firewall.php`

Runs on every web request (prepended globally). Blocks, in order:

1. **Banned IPs** — manual + automatic bans (`blocked_ips`).
2. **Threat-intel IPs** — the real-time blocklist (see §2).
3. **Country policy** — allow-list (only these countries) or deny-list, via GeoIP (§3).
4. **Bad user agents** — known scanners (sqlmap, nikto, wpscan…), empty UAs, custom blocks, fake-Googlebot (reverse-DNS verified).
5. **Scanner paths** — `wp-*`, `.env`, `.git`, `phpmyadmin`, `adminer.php`, … (instant log + strike).
6. **Payload inspection** — SQLi / XSS / path-traversal regex over query string + inputs.
7. **Repeated-404 scanning** — temporary ban after N 404s in 10 minutes.

Strike-based escalation: repeat offenders are auto-banned (`strikes_before_ban`, default 3) and a
high-severity intrusion alert fires. Every hit is written to `firewall_logs`.

## 2. Real-time threat intelligence  `ThreatIntelligence`, `ThreatIntelIp`

The open-source analog of Wordfence's premium IP blocklist. `security:update-blocklist` pulls free,
no-key public feeds — **blocklist.de** (attackers seen in the last 48h) and **FireHOL level-1**
(IPs that should never appear in legitimate traffic) — dedupes, stores up to 50k IPs in
`threat_intel_ips`, and prunes aged-out entries. The firewall checks it via an O(1) cached lookup
(`ThreatIntelIp::contains`). Scheduled daily at 03:30.

## 3. Country blocking / GeoIP  `GeoIp`

Resolves IP → ISO country via the free ip-api.com endpoint, cached 30 days per IP, degrading to
"no block" on failure (never breaks a request). Private/loopback IPs are never geo-filtered. Set
**allowed** countries (allow-list, authoritative) or **blocked** countries in Security settings.

## 4. Login & brute-force protection  `LoginSecurityService`

Attempt logging (`login_attempts`), IP lockout after `max_login_attempts` failures in 15 min,
common-username blocking (admin/root/…), strong-password enforcement, TOTP two-factor
(`TotpService`), and reCAPTCHA (`VerifyRecaptcha` middleware).

## 5. Malware & file-integrity scanning  `MalwareScanner`

`security:scan` (daily 03:00) detects PHP in uploads, dangerous-function abuse, obfuscation
markers, and — against a SHA-256 baseline (`file_hashes`) — any changed/new core file. Findings go
to `file_scan_results`; high/critical findings raise a critical intrusion alert. Rebuild the trusted
baseline from the Security Center after a known-good deploy.

## 6. Dependency vulnerability monitoring  `DependencyAudit`

`security:audit-dependencies` (weekly) runs `composer audit` against the Packagist security
database and reports any dependency with a published CVE — the analog of Wordfence's plugin/theme
vulnerability monitoring. Results are cached for the dashboard; advisories raise a high-severity alert.

## 7. Intrusion alerts  `SecurityAlertService`, `SecurityEvent`

Curated, severity-ranked events (`security_events`) power the dashboard feed and drive **email
alerts** on high/critical events (auto-bans, threat-IP hits, malware, dependency CVEs). Recipients =
`security.alert_emails` (comma-separated) or all Super Admins. Emails are throttled to one per event
type per 10 minutes so an attack flood can't flood the inbox.

## 8. Posture audit + score  `SecurityAudit`

A one-click hardening audit (like a Wordfence analyst's review): ~16 weighted checks (HTTPS,
`APP_DEBUG` off, app key, firewall on, strong passwords, admin 2FA, no default admin account,
reCAPTCHA, threat-feed freshness, recent scan, integrity baseline, no unresolved malware, alerts on,
recent backup) → **0-100 score + A-F grade** with per-item fix guidance.

## 9. Security Center dashboard  `/admin/security-center`

Live score ring, blocked-attack stats (24h/7d), threat-IP count, active bans, failed logins, the
audit checklist (failures first, with fixes), top attacking IPs + attack types, the event feed,
threat-feed + dependency status, and one-click **Run malware scan / Update threat blocklist / Scan
dependencies / Rebuild integrity baseline** actions.

---

## Scheduled jobs (`routes/console.php`)

| When | Command |
|---|---|
| Daily 03:00 | `security:scan` (malware + integrity) |
| Daily 03:30 | `security:update-blocklist` (threat feeds) |
| Weekly Mon 03:45 | `security:audit-dependencies` (CVEs) |

Requires the scheduler running (`* * * * * php artisan schedule:run`). The firewall + alerts work
regardless of the scheduler.

## Settings (Admin → Security → Security settings)

Firewall toggle · max login attempts / lockout · strong passwords · block common usernames · 2FA ·
reCAPTCHA keys · **threat-intel toggle** · **blocked / allowed countries** · **intrusion alerts +
recipients**.

## Tables

`blocked_ips`, `firewall_logs`, `login_attempts`, `audit_logs`, `file_scan_results`, `file_hashes`,
`threat_intel_ips`, `security_events`.

## Tests

`tests/Feature/AdvancedSecurityTest.php` (GeoIP, threat feed, firewall threat/country blocking,
alerts, posture audit, dependency audit, dashboard render) + `FirewallTest`. All provider HTTP is
faked — tests never hit the network.
