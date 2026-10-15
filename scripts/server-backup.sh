#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT=/opt/buksza
[[ $EUID == 0 ]] || { printf 'A mentéshez sudo szükséges.\n' >&2; exit 1; }
[[ -s "$ROOT/data/database.sqlite" ]] || exit 0
exec 8>/run/buksza-backup.lock
flock -n 8 || exit 0
TARGET="$ROOT/backups/$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM"
mkdir -p "$TARGET"
sqlite3 "$ROOT/data/database.sqlite" '.timeout 10000' ".backup '$TARGET/database.sqlite'"
[[ $(sqlite3 "$TARGET/database.sqlite" 'PRAGMA quick_check;') == ok ]]
cp -- "$ROOT/app.env" "$TARGET/app.env"
if [[ -f "$ROOT/proxy.env" ]]; then cp -- "$ROOT/proxy.env" "$TARGET/proxy.env"; fi
if [[ -f "$ROOT/current/release.env" ]]; then cp -- "$ROOT/current/release.env" "$TARGET/release.env"; fi
printf '[Buksza] Konzisztens adatbázis- és konfigurációmentés: %s\n' "$TARGET"
