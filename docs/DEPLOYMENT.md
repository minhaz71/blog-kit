# Deployment & Updates (GitHub → VPS)

How to put Terea Hub / ShopKit on a server and update it safely from GitHub.
Data is never dropped: every update takes a full backup first, runs only
additive migrations, and rolls back automatically if anything fails.

## Versioning

- **`version.json`** at the repo root is the single source of truth — the
  core `shopkit` version plus a version per tool (AI writers, link agent,
  backup, security, SEO, etc.). Bump these when you ship changes.
- **`CHANGELOG.md`** documents each release; the admin **Security → Updates**
  page shows the latest entries.
- Admin → Updates shows the running version, every tool version, the git
  commit, and "N updates available".

## One-time: put the project on GitHub

The working copy is not a git repo yet. From the project root:

```bash
git init
git add .
git commit -m "ShopKit 1.1.0"
git branch -M main
git remote add origin git@github.com:YOUR-USER/YOUR-REPO.git
git push -u origin main
```

`.gitignore` already excludes `.env`, `vendor/`, `node_modules/`, keys and
(by default) `public/build`. **Never commit `.env`.**

### Assets: pick ONE
- **Build in CI (recommended if the VPS has no Node):** un-ignore build output
  (`git rm --cached -r public/build` is not needed since it's just ignored —
  instead remove the `/public/build` line from `.gitignore`), and keep
  `.github/workflows/build-assets.yml`, which compiles and commits the bundle
  on every push. The VPS then only needs `git pull`.
- **Build on the server:** leave `public/build` ignored and delete the
  workflow. `shopkit:update` runs `npm ci && npm run build` automatically when
  Node is present on the VPS.

## One-time: set up the VPS

```bash
cd /var/www
git clone git@github.com:YOUR-USER/YOUR-REPO.git tereahub
cd tereahub
cp .env.example .env          # then edit — see the production values below
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
npm ci && npm run build       # skip if you build in CI
```

**Production `.env` must have:**
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
LOG_LEVEL=error
DB_USERNAME=tereahub           # a dedicated user, NOT root
DB_PASSWORD=a-strong-password
# do NOT set SHOPKIT_ALLOW_UNSAFE_WIPE / SHOPKIT_ALLOW_DESTRUCTIVE
BACKUP_CLOUD_REMOTE=gdrive:TereaHub-Backups   # see docs/BACKUPS.md
```

Run `php artisan shopkit:preflight` — it must pass with no critical issues.

**Scheduler** (backups, scheduled posts, trash purge, cloud sync) — add one cron line:
```
* * * * * cd /var/www/tereahub && php artisan schedule:run >> /dev/null 2>&1
```

## Updating (the normal flow)

1. Develop locally, bump `version.json` + `CHANGELOG.md`, commit, `git push`.
2. On the server, either:
   - **Admin button:** Security → Updates → **Update ShopKit** (Super Admin).
     Runs the updater in the background; watch `storage/logs/background.log`.
   - **SSH (equivalent, most reliable):** `bash deploy.sh` — or directly
     `php artisan shopkit:update`. Preview with `php artisan shopkit:update --dry-run`.

Either path: **backup → maintenance mode → pull → composer → additive migrate
→ rebuild → up**, with automatic rollback (code reset + DB restore) on failure.

## Safety guarantees

- **Mandatory backup** before every update (skippable only with `--skip-backup`, never in prod).
- **Additive migrations only.** `migrate:fresh`, `db:wipe`, `migrate:refresh/reset`,
  `migrate:rollback` are **hard-blocked when `APP_ENV=production`** (override for a
  single command only with `SHOPKIT_ALLOW_DESTRUCTIVE=1`, then unset it).
- **Auto-rollback**: any failed step resets the code to the previous commit and
  restores the database from the pre-update backup.
- **Custom HTML/CSS/JS** editing is restricted to Super Admins (stored-XSS surface).

## Security checklist (run `shopkit:preflight`)

APP_ENV=production · APP_DEBUG=false · HTTPS APP_URL · non-root DB user ·
APP_KEY set · backups writable + cloud remote set · `SHOPKIT_ALLOW_*` unset ·
git checkout present. If you run Adminer/phpMyAdmin on the box, bind it to
`127.0.0.1` and reach it via SSH tunnel — never expose it publicly.
