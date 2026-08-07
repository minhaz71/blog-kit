#!/usr/bin/env bash
# ── Hemdox BlogKit production update ─────────────────────────────────
# Run from the project root on the server:  bash deploy.sh
#
# Thin wrapper around the safe updater. `blogkit:update`:
#   1. runs the production-readiness pre-flight,
#   2. takes a FULL backup (database + files) as a restore point,
#   3. enters maintenance mode,
#   4. git pull → composer install --no-dev → additive migrate → rebuild caches/assets,
#   5. on ANY failure, rolls the code back and restores the database,
#   6. brings the site back up.
#
# The database, storage/ uploads and .env are never dropped; migrations are
# additive only (destructive ones are blocked in production).
set -euo pipefail

php artisan blogkit:update "$@"

echo
echo "✓ Done. If you use page/LiteSpeed/Cloudflare caching, click 'Purge All'"
echo "  in Admin → Performance & cache so visitors get the new version."
