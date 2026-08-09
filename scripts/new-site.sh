#!/usr/bin/env bash
#
# Hemdox BlogKit — one-command provisioner for a BRAND-NEW CyberPanel domain.
# Goes from an empty site to a live BlogKit: clone the code, then run the full
# installer (which auto-creates the database + user + password, writes .env,
# migrates, seeds, sets the vHost docRoot, cron, permissions — see install.sh).
#
# PREREQUISITE: create the website in CyberPanel first (so /home/<domain>/
# public_html and its vHost exist). Then, as ROOT:
#
#   DOMAIN=vapguide.com ADMIN_EMAIL=you@vapguide.com ADMIN_PASSWORD='Strong!' \
#   bash scripts/new-site.sh
#
# Or straight from GitHub (public repo):
#   curl -fsSL https://raw.githubusercontent.com/minhaz71/blog-kit/main/scripts/new-site.sh \
#     | DOMAIN=vapguide.com ADMIN_EMAIL=you@vapguide.com ADMIN_PASSWORD='Strong!' bash
#
# Everything is scoped to THIS domain only (see install.sh "DOMAIN ISOLATION").
set -euo pipefail

DOMAIN="${DOMAIN:-}"
[ -z "$DOMAIN" ] && { echo "ERROR: set DOMAIN=your-domain.com"; exit 1; }

REPO="${REPO:-https://github.com/minhaz71/blog-kit.git}"
BRANCH="${BRANCH:-main}"
APP="${APP:-/home/$DOMAIN/public_html}"

[ "$(id -u)" = "0" ] || { echo "ERROR: run as root (needs to create the DB and set the vHost)."; exit 1; }
[ -d "$APP" ] || { echo "ERROR: $APP not found — add the website '$DOMAIN' in CyberPanel first, then re-run."; exit 1; }

# Run git/composer as the SITE USER so nothing ends up root-owned.
OWNER="$(stat -c '%U' "/home/$DOMAIN" 2>/dev/null || echo '')"
GROUP="$(stat -c '%G' "/home/$DOMAIN" 2>/dev/null || echo "$OWNER")"
[ -z "$OWNER" ] && { echo "ERROR: could not resolve the site user for /home/$DOMAIN"; exit 1; }
USER_HOME="$(getent passwd "$OWNER" | cut -d: -f6)"; [ -d "$USER_HOME" ] || USER_HOME="$APP"
as_user() { sudo -u "$OWNER" env HOME="$USER_HOME" "$@"; }

echo "▸ Provisioning $DOMAIN  (app: $APP, user: $OWNER, repo: $REPO@$BRANCH)"

# ── 1. Get the code into public_html ─────────────────────────────────────────
if [ -d "$APP/.git" ]; then
  echo "▸ Repo already present — updating to origin/$BRANCH…"
  as_user git config --global --add safe.directory "$APP" 2>/dev/null || true
  as_user git -C "$APP" fetch origin "$BRANCH" --quiet
  as_user git -C "$APP" checkout "$BRANCH" --quiet
  as_user git -C "$APP" reset --hard "origin/$BRANCH"
else
  echo "▸ Cloning BlogKit into $APP…"
  TMP="$APP/.bk-clone.$$"
  as_user git clone --branch "$BRANCH" --depth 1 "$REPO" "$TMP"
  # Move everything (incl. dotfiles) up, then remove CyberPanel's default page.
  as_user bash -c "shopt -s dotglob nullglob; mv '$TMP'/* '$APP'/ && rmdir '$TMP'"
  # CyberPanel drops a placeholder index.html at the docroot; BlogKit serves from
  # public/, so the stray file is harmless, but remove it to keep things clean.
  [ -f "$APP/index.html" ] && ! [ -d "$APP/public" ] || rm -f "$APP/index.html" 2>/dev/null || true
fi

# ── 2. Full install (DB create + .env + migrate + vHost + cron + go live) ─────
echo "▸ Running the installer…"
cd "$APP"
# Pass through DOMAIN + any DB_*/ADMIN_*/MYSQL_ROOT_PASSWORD the caller supplied.
bash "$APP/scripts/install.sh"

echo
echo "✅ $DOMAIN is provisioned. Visit https://$DOMAIN and log in at https://$DOMAIN/admin"
