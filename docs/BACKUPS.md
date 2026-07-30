# Backups & Data-Loss Protection

How ShopKit protects your database from accidental wipes, takes daily backups, and ships them to
free cloud storage. Read this once, do the ~10-minute cloud setup, and you're covered.

---

## 1. The safety guard (already active — nothing to configure)

Any Artisan command that **erases or rewinds** the database is intercepted **before it runs** and
forced to take a fresh backup first. If the backup can't be produced, the destructive command is
**aborted** — the database is never wiped without a recoverable snapshot.

Guarded commands: `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`,
`db:wipe`. Works no matter who runs them — you, a script, CI, or an AI agent.

```
$ php artisan migrate:fresh
🛡  Safety guard: "migrate:fresh" will erase data — taking a database backup first…
   Backup created: backups/backup-database-2026_07_08_012045.zip (44 KB)
✅ Backup complete — safe to proceed. Restore with: php artisan backup:restore --latest
```

- Implementation: `app/Support/DatabaseSafetyGuard.php`, registered in `AppServiceProvider::boot()`.
- Skipped automatically during the test suite (tests use in-memory SQLite).
- **Escape hatch** (only when you knowingly want to skip, e.g. first-run setup on an empty DB):
  `SHOPKIT_ALLOW_UNSAFE_WIPE=1 php artisan migrate:fresh`.

> **Golden rule:** on a database with real data, never run `migrate:fresh`/`db:wipe`. Use
> `php artisan migrate` — it only applies *new* migrations and never drops existing tables/data.

---

## 2. Backup commands — portable snapshots with a compatibility gate

Every archive is **self-describing**: it embeds a `manifest.json` recording the environment that
produced it (PHP / Laravel / ShopKit / MySQL versions, required PHP extensions, the full
ran-migrations list, row counts, an APP_KEY fingerprint, and a SHA-256 checksum of the dump).
The dump carries the **complete schema + data**, so restoring **never needs migrations** — a full
snapshot rebuilds the site as-is (products, orders, AI batches/usage, API keys, every setting).

| Command | What it does |
|---|---|
| `php artisan backup:run --type=database` | DB dump + manifest → `storage/app/backups/backup-database-*.zip` |
| `php artisan backup:run --type=full` | DB **+** uploaded files (`storage/app/public`) **+** AI import CSVs |
| `php artisan backup:restore --latest` | **Check compatibility → safety-backup current DB → restore DB → restore files → run any newer migrations → clear caches → verify row counts** |
| `php artisan backup:restore --path=backups/….zip` | Same, for a specific archive |
| `php artisan backup:restore … --skip-checks` | Override a failed gate (dangerous; needed for pre-manifest archives) |
| `php artisan backup:restore … --no-files` / `--no-safety-backup` | Partial-restore switches |
| `php artisan backup:prune --keep-days=30` | Delete local backups older than N days |

**The compatibility gate blocks the restore** (before anything is touched) when: the archive is
corrupt (checksum mismatch) · target PHP is older than the backup's · a required PHP extension is
missing · Laravel major is older · the backup came from a **newer ShopKit** or contains migrations
this codebase doesn't know · DB driver differs · no `mysql` client. It **warns** on: newer
PHP/Laravel · older MySQL major · a different APP_KEY (encrypted columns won't decrypt).

Admin UI (`Admin → System → Backups`): **Back up now** buttons, per-row **Download / Check /
Restore / Delete**, and **Import backup file** — upload a zip from any other ShopKit server; it is
checked and restored in one step.

Dumps use `--single-transaction --set-gtid-purged=OFF --no-tablespaces --routines --triggers
--add-drop-table` so they're consistent, lock-free, and import cleanly.

---

## 3. Automatic schedule (already wired)

In `routes/console.php` (runs when the Laravel scheduler is active — see §6):

| When | Command |
|---|---|
| Daily 01:00 | `backup:run --type=database` |
| Sundays 01:15 | `backup:run --type=full` |
| Daily 02:15 | `backup:prune --keep-days=30` |

That gives you 30 rolling daily DB snapshots on disk. **On-disk isn't enough** — if the laptop/server
dies, they die with it. §4 ships them off-machine, for free.

---

## 4. Free off-machine cloud backup

Pick one. **rclone → Google Drive is recommended** (you asked for Drive; it's the simplest reliable
path and 15 GB is free). All options below cost nothing at backup volumes.

### Option A — rclone → Google Drive  ⭐ recommended (free 15 GB)

`rclone` is a free, open-source sync tool. It handles Google's OAuth for you and uploads the whole
backups folder with one command.

**One-time setup (~10 min):**

```bash
# 1. Install rclone
brew install rclone

# 2. Configure a Google Drive remote (opens your browser to authorize)
rclone config
#   n) New remote
#   name>  gdrive
#   Storage>  drive          (type the number for "Google Drive")
#   client_id / client_secret>  (press Enter to use defaults — fine for personal use)
#   scope>  1                 (full access) — or 2 for drive.file (app-created files only)
#   Edit advanced config>  n
#   Use auto config>  y       → browser opens → sign in → Allow
#   Configure as team drive>  n
#   y)  Yes this is OK   →   q) Quit config

# 3. Test it
rclone lsd gdrive:            # lists your Drive folders — confirms auth works
```

**Upload the backups (run manually to verify):**

```bash
cd "/Users/minhaz/ecommerce site"
rclone copy storage/app/backups "gdrive:TereaHub-Backups" --progress
```

**Automate it — just set two env values.** The daily upload + cloud retention is already wired
(`backup:cloud-sync` runs at 02:30 in `routes/console.php`); it stays dormant until you point it
at a remote. Add to `.env`:

```dotenv
BACKUP_CLOUD_REMOTE="gdrive:TereaHub-Backups"   # rclone remote:folder
BACKUP_CLOUD_RETAIN_DAYS=30                      # delete cloud copies older than this
```

That's it. From then on, every night:

1. `backup:run` writes the day's archive to `storage/app/backups` (DB daily, full weekly).
2. `backup:cloud-sync` uploads new archives to `gdrive:TereaHub-Backups` **and deletes cloud copies
   older than `BACKUP_CLOUD_RETAIN_DAYS`** — so old backups are pruned automatically off-machine too.

Run it by hand any time to check:

```bash
php artisan backup:cloud-sync            # uses .env settings
php artisan backup:cloud-sync --mirror   # mirror mode: also removes cloud files you pruned locally
```

> `backup:cloud-sync` no-ops safely if `BACKUP_CLOUD_REMOTE` is empty or rclone isn't installed, so
> it never breaks the nightly schedule. Requires the one-time `rclone config` above on the server.

### Option B — Backblaze B2 (free 10 GB, S3-compatible, very reliable for backups)

B2 is purpose-built for backups. Because it's S3-compatible, Laravel can upload natively — no rclone.

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

`.env`:
```
B2_KEY_ID=...
B2_APP_KEY=...
B2_REGION=us-west-004
B2_BUCKET=shopkit-backups
B2_ENDPOINT=https://s3.us-west-004.backblazeb2.com
```

`config/filesystems.php` → add a disk:
```php
'b2' => [
    'driver' => 's3',
    'key' => env('B2_KEY_ID'),
    'secret' => env('B2_APP_KEY'),
    'region' => env('B2_REGION'),
    'bucket' => env('B2_BUCKET'),
    'endpoint' => env('B2_ENDPOINT'),
    'use_path_style_endpoint' => true,
],
```

Then a scheduled upload (a tiny command or closure) that does
`Storage::disk('b2')->put('backups/'.basename($zip), file_get_contents($zip))` for the day's archive.
The same disk pattern works for **Cloudflare R2** (10 GB free) and **AWS S3** — only the endpoint/keys change.

### Option C — rclone → Dropbox (free 2 GB)

Identical to Option A but choose `dropbox` as the storage type in `rclone config`. Smallest free
tier; fine for DB-only dumps (tens of KB–few MB each).

---

## 5. Disaster-recovery drill (do this once so you trust it)

```bash
php artisan backup:run --type=database          # make a snapshot
php artisan backup:restore --latest --force      # restore it
php artisan tinker --execute='echo App\Models\Product::count();'   # sanity check
```

To restore on a **fresh machine**: install the app, `php artisan migrate`, copy a backup zip into
`storage/app/backups/`, then `php artisan backup:restore --path=backups/<file>.zip`. Download the
latest from Drive first: `rclone copy gdrive:ShopKit-Backups storage/app/backups`.

---

## 6. Making the schedule actually run

Scheduled backups only fire if Laravel's scheduler is running.

- **Local dev (this Mac):** run `php artisan schedule:work` in a terminal, or add one cron line:
  ```
  * * * * * cd "/Users/minhaz/ecommerce site" && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Production (LiteSpeed/cPanel):** add the same `* * * * * … schedule:run` cron entry.

Without this, the daily/weekly jobs won't run — but the **pre-wipe safety guard in §1 works
regardless**, because it triggers on the command itself, not on a schedule.

---

## 7. Recovering from MySQL binary logs (last resort)

This server has binary logging **on** (`log_bin=ON`), so even data created *between* backups is
often recoverable from `/opt/homebrew/var/mysql/binlog.*`:

```bash
# See what a binlog contains (row changes as readable pseudo-SQL)
mysqlbinlog --base64-output=DECODE-ROWS -v /opt/homebrew/var/mysql/binlog.000058 | less
```

It's fiddly (ROW format, and schema changes across the log complicate replay), so treat it as the
safety net *behind* the backups above — not the primary plan.

---

*Files: `app/Support/DatabaseSafetyGuard.php`, `app/Console/Commands/BackupRunCommand.php`,
`BackupRestoreCommand.php`, `BackupPruneCommand.php`, schedule in `routes/console.php`.*
