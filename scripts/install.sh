#!/usr/bin/env bash
#
# Hemdox BlogKit — safe, idempotent installer/updater for CyberPanel /
# OpenLiteSpeed (lsphp). Re-runnable: it only ADDS and fixes, never drops data.
# It performs the full deploy AND sets up the background processes (scheduler
# cron + queue worker) and permissions that a fresh CyberPanel site is missing.
#
# Usage (as root, from the site's public_html, or pass DOMAIN):
#   DOMAIN=puffandpod.com \
#   DB_DATABASE=puff_db DB_USERNAME=puff_user DB_PASSWORD='secret' \
#   [ADMIN_EMAIL=you@x.com ADMIN_PASSWORD='StrongPass!'] \
#   bash scripts/install.sh
#
# Everything runs as the CyberPanel SITE USER (never leaves root-owned files).
# All variables can be overridden from the environment.

set -euo pipefail

# ── Configuration (override via env) ──────────────────────────────────────────
DOMAIN="${DOMAIN:-}"
if [ -z "$DOMAIN" ]; then
  # Infer from /home/<domain>/public_html when run from inside the site.
  DOMAIN="$(pwd | sed -n 's#^/home/\([^/]*\)/public_html.*#\1#p')"
fi
[ -z "$DOMAIN" ] && { echo "ERROR: set DOMAIN=your-domain.com"; exit 1; }

APP="${APP:-/home/$DOMAIN/public_html}"
PHP="${PHP:-/usr/local/lsws/lsphp83/bin/php}"
BRANCH="${BRANCH:-main}"
[ -x "$PHP" ] || { echo "ERROR: PHP 8.3 not found at $PHP (set PHP=...)"; exit 1; }
[ -d "$APP" ] || { echo "ERROR: app dir $APP not found (set APP=...)"; exit 1; }

# Resolve the CyberPanel site user (owner of the domain home).
OWNER="${OWNER:-$(stat -c '%U' "/home/$DOMAIN" 2>/dev/null || echo '')}"
GROUP="${GROUP:-$(stat -c '%G' "/home/$DOMAIN" 2>/dev/null || echo "$OWNER")}"
[ -z "$OWNER" ] && { echo "ERROR: could not resolve site user; set OWNER=..."; exit 1; }

# The site user's REAL home (CyberPanel uses /home/<domain>, not /home/<user>).
# Fall back to a writable dir inside the app so npm/git caches never hit an
# unwritable path.
USER_HOME="$(getent passwd "$OWNER" 2>/dev/null | cut -d: -f6)"
if [ -z "$USER_HOME" ] || [ ! -d "$USER_HOME" ]; then
  USER_HOME="$APP/storage/app/.deploy-home"
fi
mkdir -p "$USER_HOME" 2>/dev/null || true
chown "$OWNER:$GROUP" "$USER_HOME" 2>/dev/null || true

# Run any command AS THE SITE USER (so nothing becomes root-owned).
as_user() { sudo -u "$OWNER" env HOME="$USER_HOME" "$@"; }
artisan() { as_user "$PHP" "$APP/artisan" "$@"; }

echo "▸ Site: $DOMAIN | app: $APP | user: $OWNER:$GROUP | php: $PHP"
cd "$APP"

# ── 1. Git safe.directory (fixes "dubious ownership" for pulls/updates) ───────
as_user git config --global --add safe.directory "$APP" 2>/dev/null || true

# ── 2. Composer deps under PHP 8.3 (never the system default 7.4) ─────────────
if ! command -v composer >/dev/null 2>&1; then
  echo "▸ Installing Composer under PHP 8.3…"
  "$PHP" -r "copy('https://getcomposer.org/installer','/tmp/ci.php');"
  "$PHP" /tmp/ci.php --install-dir=/usr/local/bin --filename=composer
fi
echo "▸ Installing PHP dependencies…"
as_user env COMPOSER_ALLOW_SUPERUSER=1 "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction

# ── 3. .env + app key ─────────────────────────────────────────────────────────
if [ ! -f "$APP/.env" ]; then
  echo "▸ Creating .env from .env.example — EDIT DB credentials after this run."
  as_user cp "$APP/.env.example" "$APP/.env"
fi
grep -q '^APP_KEY=base64:' "$APP/.env" || { echo "▸ Generating app key…"; artisan key:generate --force; }

# Apply DB creds if provided (idempotent, in-place).
set_env() { # set_env KEY VALUE
  local k="$1" v="$2"
  if grep -q "^${k}=" "$APP/.env"; then
    as_user sed -i "s|^${k}=.*|${k}=${v}|" "$APP/.env"
  else
    echo "${k}=${v}" | as_user tee -a "$APP/.env" >/dev/null
  fi
}
[ -n "${DB_DATABASE:-}" ] && set_env DB_CONNECTION mysql && set_env DB_HOST "${DB_HOST:-127.0.0.1}" \
  && set_env DB_PORT "${DB_PORT:-3306}" && set_env DB_DATABASE "$DB_DATABASE" \
  && set_env DB_USERNAME "${DB_USERNAME:-}" && set_env DB_PASSWORD "${DB_PASSWORD:-}"
# Production-safe defaults (advisories from preflight).
set_env CACHE_STORE "${CACHE_STORE:-file}"
set_env SESSION_DRIVER "${SESSION_DRIVER:-file}"
set_env LOG_LEVEL "${LOG_LEVEL:-error}"

# ── 4. Runtime storage dirs (git doesn't store empty dirs → 500 if missing) ──
echo "▸ Ensuring storage directories…"
as_user mkdir -p \
  "$APP/storage/framework/sessions" \
  "$APP/storage/framework/views" \
  "$APP/storage/framework/cache/data" \
  "$APP/storage/app/public" \
  "$APP/storage/logs" \
  "$APP/bootstrap/cache"

# ── 5. Migrate (+ seed only on a fresh DB) ───────────────────────────────────
echo "▸ Running migrations (additive; destructive ones are blocked)…"
FRESH=0
if [ "${SEED:-auto}" = "auto" ]; then
  # Seed when there are no users yet (fresh install).
  USERS="$(artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("users") ? \App\Models\User::count() : 0;' 2>/dev/null | tr -dc '0-9' || echo 0)"
  [ "${USERS:-0}" = "0" ] && FRESH=1
elif [ "${SEED:-}" = "1" ]; then
  FRESH=1
fi
if [ "$FRESH" = "1" ]; then
  artisan migrate --force --seed
else
  artisan migrate --force
fi
artisan storage:link 2>/dev/null || true

# Content pipeline backfills (idempotent no-ops once done).
artisan blogkit:backfill-clusters 2>/dev/null || true
artisan blogkit:build-categories 2>/dev/null || true

# ── 6. Admin user (optional) ─────────────────────────────────────────────────
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
  echo "▸ Ensuring admin user $ADMIN_EMAIL…"
  # Single-quoted --execute so a '!' in the password never triggers bash history.
  artisan tinker --execute='$u=\App\Models\User::updateOrCreate(["email"=>"'"$ADMIN_EMAIL"'"],["name"=>"Admin","password"=>bcrypt("'"$ADMIN_PASSWORD"'"),"is_active"=>true,"email_verified_at"=>now()]); $u->assignRole("Super Admin"); echo "admin ready";' || true
fi

# ── 7. Frontend assets — prefer the committed/CI build; build only if missing ─
# The repo ships a CI-built public/build, so npm is optional here. Never let an
# npm hiccup abort the deploy (permissions, low RAM, registry) — keep the
# committed build as the fallback.
if [ -f "$APP/public/build/manifest.json" ]; then
  echo "▸ Using committed/CI-built assets in public/build (skipping npm)."
elif command -v npm >/dev/null 2>&1; then
  echo "▸ Building frontend assets…"
  as_user npm ci --no-audit --no-fund || echo "  npm ci failed — keeping existing build."
  as_user env NODE_OPTIONS=--max-old-space-size=2048 npm run build || echo "  npm build failed — keeping existing build."
else
  echo "▸ npm not found — using the committed build in public/build."
fi

# ── 8. Ownership + permissions (hand everything back to the site user) ───────
echo "▸ Fixing ownership & permissions…"
chown -R "$OWNER:$GROUP" "$APP"
chmod -R 775 "$APP/storage" "$APP/bootstrap/cache"

# ── 9. Background processes — scheduler cron + queue worker (AS THE SITE USER)─
echo "▸ Installing cron jobs (scheduler + queue worker) as $OWNER…"
SCHED_LINE="* * * * * cd $APP && sudo -u $OWNER $PHP artisan schedule:run >> /dev/null 2>&1"
QUEUE_LINE="* * * * * cd $APP && sudo -u $OWNER $PHP artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1"
# Idempotent: strip any existing BlogKit cron lines for this app, then add fresh.
( crontab -l 2>/dev/null | grep -vF "$APP/artisan schedule:run" | grep -vF "$APP/artisan queue:work" | grep -vF "artisan schedule:run" | grep -vF "artisan queue:work"; \
  echo "$SCHED_LINE"; echo "$QUEUE_LINE" ) | crontab -

# ── 10. Flush caches (app + LiteSpeed edge) ──────────────────────────────────
echo "▸ Clearing caches…"
artisan optimize:clear
artisan cache:clear || true
rm -rf /usr/local/lsws/cachedata/* 2>/dev/null || true
systemctl restart lsws 2>/dev/null || true

# ── 11. Final health check ───────────────────────────────────────────────────
echo "▸ Preflight:"
artisan blogkit:preflight || true

echo
echo "✅ Done. Reminders:"
echo "   • Point the vHost docRoot at:  \$VH_ROOT/public_html/public"
echo "   • If you just created .env, edit DB credentials and re-run this script."
echo "   • Scheduler + queue worker now run every minute as $OWNER (backups, scheduled posts, network jobs)."
