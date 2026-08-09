#!/usr/bin/env bash
#
# Hemdox BlogKit — safe, idempotent installer/updater for CyberPanel /
# OpenLiteSpeed (lsphp). Re-runnable: it only ADDS and fixes, never drops data.
# It performs the full deploy AND sets up the background processes (scheduler
# cron + queue worker) and permissions that a fresh CyberPanel site is missing.
#
# Zero-touch: it CREATES the MySQL database + user automatically (like
# `wp core install` / `wp db create`) — no phpMyAdmin, no manual .env editing.
#
# Simplest (fully automatic — DB name/user/password derived + generated):
#   DOMAIN=puffandpod.com ADMIN_EMAIL=you@x.com ADMIN_PASSWORD='StrongPass!' \
#   bash scripts/install.sh
#
# Bring your own DB credentials (created if missing):
#   DOMAIN=puffandpod.com \
#   DB_DATABASE=puff_db DB_USERNAME=puff_user DB_PASSWORD='secret' \
#   bash scripts/install.sh
#
# The DB is created using MySQL root, auto-detected in this order:
#   MYSQL_ROOT_PASSWORD=...  →  /etc/cyberpanel/mysqlPassword  →  root unix_socket.
# If none work, it prints exactly what to do and everything else still runs.
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

# Read/write .env values in place (idempotent).
set_env() { # set_env KEY VALUE
  local k="$1" v="$2"
  if grep -q "^${k}=" "$APP/.env"; then
    as_user sed -i "s|^${k}=.*|${k}=${v}|" "$APP/.env"
  else
    echo "${k}=${v}" | as_user tee -a "$APP/.env" >/dev/null
  fi
}
env_get() { grep "^$1=" "$APP/.env" 2>/dev/null | head -1 | cut -d= -f2- | sed 's/^"//;s/"$//'; }

# ── 3b. Database — create it automatically (like `wp db create`) ─────────────
# Zero-touch: if DB creds aren't given, reuse what's already in .env, else derive
# safe names from the domain and generate a strong password. Then create the
# database + user if they don't exist. No manual MySQL step, no phpMyAdmin.
SANITIZED="$(echo "$DOMAIN" | tr 'A-Z' 'a-z' | tr -c 'a-z0-9' '_' | sed 's/_\{1,\}/_/g;s/^_//;s/_$//')"
DB_HOST="${DB_HOST:-$(env_get DB_HOST)}"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-$(env_get DB_PORT)}"; DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-$(env_get DB_DATABASE)}"; DB_DATABASE="${DB_DATABASE:-blogkit_${SANITIZED}}"
DB_USERNAME="${DB_USERNAME:-$(env_get DB_USERNAME)}"; DB_USERNAME="${DB_USERNAME:-bk_${SANITIZED}}"
DB_PASSWORD="${DB_PASSWORD:-$(env_get DB_PASSWORD)}"
# MySQL identifier limits: database <= 64 chars, username <= 32.
DB_DATABASE="$(echo "$DB_DATABASE" | cut -c1-64)"
DB_USERNAME="$(echo "$DB_USERNAME" | cut -c1-32)"
AUTO_DB_PW=0
if [ -z "$DB_PASSWORD" ]; then
  DB_PASSWORD="$(openssl rand -hex 16 2>/dev/null || echo "Bk$(date +%s)$RANDOM")"
  AUTO_DB_PW=1
fi

# A MySQL admin client (root) for CREATE DATABASE/USER. CyberPanel keeps the root
# password in /etc/cyberpanel/mysqlPassword; fresh MariaDB often allows root via
# unix_socket. MYSQL_ROOT_PASSWORD=... overrides.
mysql_admin() {
  if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$@";
  elif [ -f /etc/cyberpanel/mysqlPassword ]; then mysql -uroot -p"$(cat /etc/cyberpanel/mysqlPassword)" "$@";
  else mysql -uroot "$@"; fi
}

if ! command -v mysql >/dev/null 2>&1; then
  echo "▸ ⚠ mysql client not found — skipping DB auto-create. Create '$DB_DATABASE' manually, then re-run."
elif mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e 'SELECT 1' "$DB_DATABASE" >/dev/null 2>&1; then
  echo "▸ Database '$DB_DATABASE' already reachable as '$DB_USERNAME' — skipping create."
elif mysql_admin -e 'SELECT 1' >/dev/null 2>&1; then
  echo "▸ Creating database '$DB_DATABASE' and user '$DB_USERNAME'…"
  # IF NOT EXISTS keeps it re-runnable; ALTER USER re-syncs the password so .env
  # and MySQL never drift. Passwords are hex (no quoting hazards).
  mysql_admin <<SQL || echo "  ⚠ DB creation reported an error — check MySQL and re-run."
CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USERNAME'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
else
  echo "▸ ⚠ Could not reach MySQL as admin to auto-create the database."
  echo "    Re-run with MYSQL_ROOT_PASSWORD='...' (or create '$DB_DATABASE' in CyberPanel first)."
fi

# Write the resolved DB creds into .env so Laravel connects.
set_env DB_CONNECTION mysql
set_env DB_HOST "$DB_HOST"
set_env DB_PORT "$DB_PORT"
set_env DB_DATABASE "$DB_DATABASE"
set_env DB_USERNAME "$DB_USERNAME"
set_env DB_PASSWORD "$DB_PASSWORD"

# App URL + production posture (derive the URL from the domain if not given).
set_env APP_URL "${APP_URL:-https://$DOMAIN}"
set_env APP_ENV "${APP_ENV:-production}"
set_env APP_DEBUG "${APP_DEBUG:-false}"

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

# ── 9b. Web server docRoot — point the vHost at Laravel's public/ (auto) ─────
# CyberPanel/OpenLiteSpeed serves $VH_ROOT/public_html by default, but a Laravel
# app's front controller lives in public_html/public. Point the vHost there
# automatically (idempotent + backed up) so there is NO manual vHost editing in
# the panel — the WordPress-simple experience, on BlogKit's own stack.
# Opt out with SKIP_VHOST=1 if you manage the vHost yourself.
if [ "${SKIP_VHOST:-0}" != "1" ]; then
  VHOST_CONF="${VHOST_CONF:-/usr/local/lsws/conf/vhosts/$DOMAIN/vhost.conf}"
  if [ ! -f "$VHOST_CONF" ]; then
    echo "▸ vHost conf not found ($VHOST_CONF) — set the site docRoot to public_html/public in CyberPanel."
  elif grep -qE "^[[:space:]]*docRoot[[:space:]]+${APP}/public([[:space:]]|$)" "$VHOST_CONF"; then
    echo "▸ vHost docRoot already points at public/ — no change."
  elif grep -qE "^[[:space:]]*docRoot[[:space:]]" "$VHOST_CONF"; then
    echo "▸ Pointing vHost docRoot at $APP/public…"
    cp -a "$VHOST_CONF" "$VHOST_CONF.bak.$(date +%s)"
    # Replace whatever docRoot currently is with the app's public/ dir.
    sed -i -E "s#^([[:space:]]*docRoot[[:space:]]+).*#\1${APP}/public#" "$VHOST_CONF"
    NEED_LSWS_RESTART=1
  else
    echo "▸ ⚠ No docRoot line in $VHOST_CONF — set docRoot to $APP/public in CyberPanel manually."
  fi
fi

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
if [ "${SKIP_VHOST:-0}" = "1" ]; then
  echo "   • SKIP_VHOST set — point the vHost docRoot at $APP/public yourself."
else
  echo "   • vHost docRoot auto-pointed at $APP/public (a .bak was saved next to vhost.conf)."
fi
echo "   • Database '$DB_DATABASE' (user '$DB_USERNAME') is created and wired into .env."
if [ "${AUTO_DB_PW:-0}" = "1" ]; then
  echo "   • Generated DB password (saved in .env): $DB_PASSWORD"
fi
echo "   • Scheduler + queue worker now run every minute as $OWNER (backups, scheduled posts, network jobs)."
