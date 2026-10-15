#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT=/opt/buksza
[[ $EUID == 0 ]] || { printf 'A mentéshez sudo szükséges.\n' >&2; exit 1; }
TRIGGER=manual
case "${1:-}" in
    '') ;;
    --scheduled) TRIGGER=scheduled ;;
    *) printf 'Használat: backup.sh [--scheduled]\n' >&2; exit 1 ;;
esac
[[ $# -le 1 ]] || exit 1
exec 8>/run/buksza-backup.lock
flock -n 8 || exit 0
install -d -m 0755 -o root -g root "$ROOT/status"
STARTED=$(date -u +%Y-%m-%dT%H:%M:%SZ)
BYTES=null
write_status() {
    local state=$1 name=$2 temporary finished=null
    if [[ "$state" != running ]]; then finished="\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\""; fi
    temporary=$(mktemp "$ROOT/status/.backup.XXXXXX") || return 1
    printf '{"version":1,"status":"%s","trigger":"%s","started_at":"%s","finished_at":%s,"bytes":%s}\n' "$state" "$TRIGGER" "$STARTED" "$finished" "$BYTES" > "$temporary" || return 1
    chmod 0644 "$temporary" || return 1
    mv -f -- "$temporary" "$ROOT/status/$name"
}
finish() {
    local result=$?
    trap - EXIT
    if [[ $result != 0 ]]; then write_status failed backup.json || true; fi
    exit "$result"
}
trap finish EXIT
write_status running backup.json
[[ -s "$ROOT/data/database.sqlite" ]] || { printf 'A mentendő adatbázis nem található.\n' >&2; exit 1; }
TARGET="$ROOT/backups/$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM"
mkdir -p "$TARGET"
sqlite3 "$ROOT/data/database.sqlite" '.timeout 10000' ".backup '$TARGET/database.sqlite'"
[[ $(sqlite3 "$TARGET/database.sqlite" 'PRAGMA quick_check;') == ok ]]
cp -- "$ROOT/app.env" "$TARGET/app.env"
if [[ -f "$ROOT/proxy.env" ]]; then cp -- "$ROOT/proxy.env" "$TARGET/proxy.env"; fi
if [[ -f "$ROOT/current/release.env" ]]; then cp -- "$ROOT/current/release.env" "$TARGET/release.env"; fi
BYTES=$(stat -c '%s' "$TARGET/database.sqlite")
write_status succeeded last-success.json
if [[ "$TRIGGER" == scheduled ]]; then write_status succeeded scheduled-success.json; fi
write_status succeeded backup.json
printf '[Buksza] Konzisztens adatbázis- és konfigurációmentés: %s\n' "$TARGET"
