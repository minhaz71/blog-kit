# Hemdox BlogKit — Installation Guide (CyberPanel / OpenLiteSpeed)

A step-by-step install for a fresh domain on **CyberPanel** (OpenLiteSpeed + lsphp).
It also lists every error we hit on the first deploy and the exact fix, so they
don't happen again. Generic LEMP/LAMP notes are at the bottom.

## Requirements

- **PHP 8.3** (CyberPanel: `lsphp83`) with extensions: `mysqlnd, gd, mbstring, xml, intl, bcmath, zip, curl, process, opcache`
- **MySQL / MariaDB** and `mysqldump` (for backups)
- **Node.js 20+** and npm (to build the CSS/JS)
- **Composer** (installed under PHP 8.3)
- Git

Handy variables used throughout (edit the DB values):

```bash
export DOMAIN=example.com
export APP=/home/$DOMAIN/public_html
export PHP=/usr/local/lsws/lsphp83/bin/php
```

---

## 1. CyberPanel UI (before SSH)

1. **Websites → Create Website** — your domain, **PHP 8.3**.
2. **Databases → Create Database** — note the DB **name / user / password**.
3. (After deploy) **SSL → Issue SSL** (Let's Encrypt).

---

## 2. Get the code

CyberPanel creates an empty `public_html`; replace it with the repo:

```bash
cd /home/$DOMAIN && rm -rf public_html \
  && git clone -b main https://github.com/minhaz71/blog-kit.git public_html \
  && cd $APP
```

> Deploying a feature branch? swap `-b main` for `-b <branch-name>`.

---

## 3. PHP dependencies (Composer) — run it WITH PHP 8.3

> ⚠️ **Most common mistake:** running bare `composer` uses the server's *default*
> PHP (often 7.4) and fails with dozens of "requires php ^8.x" errors. Always
> invoke Composer through the 8.3 binary.

Install Composer once (under 8.3), then install deps:

```bash
cd $APP
command -v composer >/dev/null || { $PHP -r "copy('https://getcomposer.org/installer','/tmp/ci.php');" && $PHP /tmp/ci.php --install-dir=/usr/local/bin --filename=composer; }
COMPOSER_ALLOW_SUPERUSER=1 $PHP $(command -v composer) install --no-dev --optimize-autoloader
```

> A "package:discover … database.sqlite does not exist" error at the END of
> `composer install` is **harmless here** — it's the post-install hook booting the
> app before `.env` exists. It goes away after step 4. The dependencies are
> already installed (you'll see "Generating optimized autoload files").

---

## 4. Environment (`.env`)

```bash
cd $APP && cp .env.example .env && nano .env
```

Set at least these (use **file** cache/session so nothing depends on DB tables
before you migrate):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

CACHE_STORE=file
SESSION_DRIVER=file

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

Generate the app key:

```bash
cd $APP && $PHP artisan key:generate
```

---

## 5. Create the runtime storage folders  ← don't skip

> ⚠️ **This one 500s the whole site if skipped.** Git does not store empty
> directories, so `storage/framework/{sessions,cache,views}` are missing after a
> clone. File-based sessions then fail with
> `file_put_contents(.../storage/framework/sessions/...): No such file or directory`.

```bash
cd $APP && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/app/public bootstrap/cache
```

---

## 6. Migrate, seed, storage link, caches

```bash
cd $APP && $PHP artisan migrate --force --seed
cd $APP && $PHP artisan storage:link
cd $APP && $PHP artisan optimize:clear
```

`--seed` creates roles/permissions, default settings, email templates, content
blocks, and demo blog posts. Delete the demo posts later from the admin.

---

## 7. Build front-end assets (CSS/JS)

> ⚠️ The compiled `public/build` in the repo can lag behind template changes, so
> the design looks broken until you rebuild on the server.

Install Node 20 if missing, then build:

```bash
node -v || { curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - && dnf install -y nodejs; }
cd $APP && npm ci && npm run build
```

If `npm run build` is "Killed" (low RAM):

```bash
cd $APP && NODE_OPTIONS=--max-old-space-size=2048 npm run build
```

> ⚠️ **ALWAYS flush caches right after building.** `@vite` bakes the asset
> *hash* (e.g. `app-mSCKIL2S.css`) into the page HTML. This app caches rendered
> guest pages (GuestPageCache) and LiteSpeed caches HTML at the edge. A rebuild
> makes a **new** hash and **deletes the old file**, so any cached page then
> points at a missing file → **CSS 404 → the whole site looks unstyled**. After
> every build run:
>
> ```bash
> cd $APP && chown -R $OWNER:$GROUP public/build \
>   && sudo -u $OWNER $PHP artisan optimize:clear \
>   && sudo -u $OWNER $PHP artisan cache:clear \
>   && (rm -rf /usr/local/lsws/cachedata/* 2>/dev/null; systemctl restart lsws)
> ```
> (If `/usr/local/lsws/cachedata/` doesn't exist, flush via
> CyberPanel → Manage → LiteSpeed Cache → Flush.) Then reload in an **incognito**
> window to bypass your browser cache.

Verify manifest ↔ built file ↔ served HTML all agree (all three must show the
same hash, and the file must be `200`):

```bash
cd $APP && CSS=$($PHP -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true); echo $m["resources/css/app.css"]["file"];'); echo "manifest: $CSS"; curl -sk -o /dev/null -w "file=%{http_code}\n" -H "Host: $DOMAIN" "https://127.0.0.1/build/$CSS"; echo -n "page refs: "; curl -sk "https://$DOMAIN/" | grep -oE 'assets/app-[A-Za-z0-9_]+\.css' | head -1
```

---

## 8. Create the admin user

> ⚠️ If the password contains `!`, bash tries history expansion and you get
> `event not found`. **Wrap the whole `--execute` in SINGLE quotes.**

```bash
cd $APP && $PHP artisan tinker --execute='$u=\App\Models\User::updateOrCreate(["email"=>"you@example.com"],["name"=>"Admin","password"=>"YourStrongPass!","is_active"=>true,"email_verified_at"=>now()]); $u->assignRole("Super Admin"); echo "admin ready: ".$u->email;'
```

---

## 9. Ownership & permissions  ← required after any root command

> ⚠️ Running `artisan`/`composer`/`npm` as **root** leaves root-owned files that
> the site's PHP user (e.g. `puffa5892`) can't write → "save spinner", cache
> errors, session 500s. Always hand ownership back to the CyberPanel site user.

Find the site user (owner of the domain home), then fix everything:

```bash
OWNER=$(stat -c '%U' /home/$DOMAIN); GROUP=$(stat -c '%G' /home/$DOMAIN); echo "site user = $OWNER:$GROUP"
chown -R "$OWNER":"$GROUP" "$APP"
chmod -R 775 "$APP/storage" "$APP/bootstrap/cache"
```

To avoid re-rooting files, run future artisan as the site user:
`sudo -u <siteuser> $PHP artisan <cmd>`

---

## 10. Point the document root at `/public`

Laravel serves from `public/`, not the repo root.

CyberPanel → **Websites → List → your domain → Manage → vHost Conf**, set:

```
docRoot                   $VH_ROOT/public_html/public
```

Save, then restart LiteSpeed:

```bash
systemctl restart lsws
```

---

## 11. Scheduler cron (scheduled publishing, sitemap, daily backups)

```bash
( crontab -l 2>/dev/null; echo "* * * * * $PHP $APP/artisan schedule:run >> /dev/null 2>&1" ) | crontab -
```

---

## 12. Done — verify

Open `https://example.com` (blog homepage) and `https://example.com/admin`
(log in with the admin from step 8) → **Appearance** to choose the color theme
and the header / footer / home / catalogue / post / TOC designs.

Optional:
- **AI writer + thumbnails:** add provider keys (Anthropic / OpenAI / Gemini +
  fal.ai) in **Admin → AI settings**.
- **E-commerce:** ships disabled; enable it in **Admin → System → Modules**.

---

## Redeploy (pulling new changes later)

```bash
cd $APP && git pull \
  && COMPOSER_ALLOW_SUPERUSER=1 $PHP $(command -v composer) install --no-dev --optimize-autoloader \
  && npm ci && npm run build \
  && $PHP artisan migrate --force \
  && sudo -u $OWNER $PHP artisan blogkit:backfill-clusters \
  && sudo -u $OWNER $PHP artisan blogkit:build-categories \
  && chown -R $OWNER:$GROUP "$APP" \
  && sudo -u $OWNER $PHP artisan optimize:clear \
  && sudo -u $OWNER $PHP artisan cache:clear \
  && (rm -rf /usr/local/lsws/cachedata/* 2>/dev/null; systemctl restart lsws)
```

> `blogkit:backfill-clusters` is idempotent — it stamps cluster/funnel metadata
> onto posts published before those columns existed and stitches pillar↔spoke
> links. Safe to run every deploy; a no-op once everything is backfilled. Tune
> the funnel/cluster behaviour in **Admin → SEO → Content strategy**.

The `cache:clear` + LiteSpeed flush at the end are **required after a rebuild**
(otherwise cached HTML serves the old asset hash → 404 → unstyled). Then
hard-refresh in incognito. If `/usr/local/lsws/cachedata/` doesn't exist, flush
via CyberPanel → Manage → LiteSpeed Cache → Flush.

---

## Troubleshooting — errors we actually hit

| Symptom | Cause | Fix |
|---|---|---|
| `Root composer.json requires php ^8.3 but your php version (7.4.x)` | `composer` ran under default PHP 7.4 | Run `$PHP $(command -v composer) install …` (step 3) |
| `database.sqlite does not exist … SQL: select * from "cache"` | `.env` missing/wrong → fell back to sqlite + DB cache | Set `.env` (mysql + `CACHE_STORE=file`), then `optimize:clear` (step 4) |
| `-bash: !',...: event not found` | `!` in a double-quoted command triggers bash history | Single-quote the whole `--execute` (step 8) |
| `file_put_contents(.../storage/framework/sessions/…): No such file or directory` | Empty storage dirs not created by git clone | `mkdir -p storage/framework/{sessions,views,cache/data}` (step 5) |
| 500 "Something went wrong", nothing new in `laravel.log` | Old error was cached / log written by root | Temporarily `APP_DEBUG=true` + `optimize:clear`, reload, read error, revert |
| Site loads but design is broken / unstyled; a CSS/JS request 404s (`app-XXXX.css`) | Cached HTML (app GuestPageCache **and** LiteSpeed) still points at an **old asset hash** that a later `npm run build` deleted | Rebuild, then flush BOTH caches: `optimize:clear` + `cache:clear` + `rm -rf /usr/local/lsws/cachedata/*` + `systemctl restart lsws`; reload in incognito. Do this after EVERY build (step 7). |
| Design still stale after rebuild + app cache clear | LiteSpeed edge cache serving old HTML | CyberPanel → LiteSpeed Cache → Flush (or `rm -rf /usr/local/lsws/cachedata/*; systemctl restart lsws`) |
| Logo/name shows **"Laravel"** instead of the site name | Fresh DB has no `general.site_name`; falls back to `APP_NAME` | Set it in Admin → General settings → Site name, or `APP_NAME="Your Blog"` in `.env` + `optimize:clear` |
| `storage:link` → "The [public/storage] link already exists" | Symlink was already created | Harmless — ignore |
| Save button spins / permission denied writing cache | Files owned by root | `chown -R <siteuser>` + `chmod -R 775 storage bootstrap/cache` (step 9) |
| 403 / 404 on every page | Document root not pointing at `/public` | Set `docRoot $VH_ROOT/public_html/public` (step 10) |
| `Cannot load Zend OPcache - it was already loaded` | Duplicate opcache line in the CLI php.ini | Harmless — ignore |
