#!/usr/bin/env bash
set -Eeuo pipefail
# Runs only in a disposable Linux container. No real server directories are touched.
source /work/scripts/install-ubuntu.sh
TEST_ROOT=$(mktemp -d /tmp/buksza-installer-test.XXXXXX)
INSTALL_ROOT="$TEST_ROOT/install"
parse_args
[[ "$DOMAIN" == buksza.duktor.hu ]]
parse_args --domain buksza.duktor.hu
[[ "$DOMAIN" == buksza.duktor.hu ]]
if (parse_args --domain 'duktor.hu;touch /tmp/unsafe'); then exit 1; fi
if (parse_args --unknown); then exit 1; fi
mkdir "$INSTALL_ROOT"
printf 'keep me\n' > "$INSTALL_ROOT/.existing-data"
if (prepare_root); then exit 1; fi
[[ $(cat "$INSTALL_ROOT/.existing-data") == 'keep me' ]]
[[ ! -e "$INSTALL_ROOT/.buksza-install" ]]
rm "$INSTALL_ROOT/.existing-data"
printf 'keep directory\n' > "$TEST_ROOT/existing-file"
if (INSTALL_ROOT="$TEST_ROOT/existing-file" prepare_root); then exit 1; fi
ln -s "$INSTALL_ROOT" "$TEST_ROOT/linked-root"
if (INSTALL_ROOT="$TEST_ROOT/linked-root" prepare_root); then exit 1; fi
prepare_root
grep -Fxq buksza-installer-v1 "$INSTALL_ROOT/.buksza-install"
[[ $(stat -c '%a' "$INSTALL_ROOT") == 755 ]]
[[ $(stat -c '%a' "$INSTALL_ROOT/backups") == 700 ]]
[[ -d "$INSTALL_ROOT/releases" && -d "$INSTALL_ROOT/backups" && -d "$INSTALL_ROOT/data" ]]
prepare_environment
FIRST_HASH=$(sha256sum "$INSTALL_ROOT/app.env")
prepare_environment
[[ "$FIRST_HASH" == "$(sha256sum "$INSTALL_ROOT/app.env")" ]]
grep -q '^BUKSZA_SETUP_TOKEN=.' "$INSTALL_ROOT/app.env"
grep -q '^SESSION_SECURE_COOKIE=true$' "$INSTALL_ROOT/app.env"
[[ $(stat -c '%a' "$INSTALL_ROOT/app.env") == 600 ]]
if (DOMAIN=other.example; prepare_environment); then exit 1; fi
bash /work/dist/buksza-telepito.sh --extract "$TEST_ROOT/extracted"
[[ -f "$TEST_ROOT/extracted/webapp/artisan" ]]
[[ -f "$TEST_ROOT/extracted/docker/production/compose.yaml" ]]
[[ ! -e "$TEST_ROOT/extracted/webapp/.env" ]]
[[ ! -e "$TEST_ROOT/extracted/webapp/database/database.sqlite" ]]
[[ ! -e "$TEST_ROOT/extracted/webapp/database/seeders/QaSeeder.php" ]]
if bash /work/dist/buksza-telepito.sh --extract "$TEST_ROOT/extracted"; then exit 1; fi
sqlite3 "$INSTALL_ROOT/data/database.sqlite" 'CREATE TABLE ledger(amount INTEGER); INSERT INTO ledger VALUES(1234);'
sed "s|ROOT=/opt/buksza|ROOT=$INSTALL_ROOT|" /work/scripts/server-backup.sh > "$TEST_ROOT/backup.sh"
bash "$TEST_ROOT/backup.sh"
BACKUP_DB=$(find "$INSTALL_ROOT/backups" -name database.sqlite -print -quit)
[[ $(sqlite3 "$BACKUP_DB" 'SELECT amount FROM ledger;') == 1234 ]]
cmp "$INSTALL_ROOT/app.env" "${BACKUP_DB%/*}/app.env"
[[ $(stat -c '%a' "$INSTALL_ROOT/status") == 755 ]]
[[ $(stat -c '%a' "$INSTALL_ROOT/status/backup.json") == 644 ]]
[[ $(sqlite3 :memory: "SELECT json_extract(readfile('$INSTALL_ROOT/status/backup.json'), '$.status');") == succeeded ]]
[[ ! -e "$INSTALL_ROOT/status/scheduled-success.json" ]]
bash "$TEST_ROOT/backup.sh" --scheduled
[[ $(sqlite3 :memory: "SELECT json_extract(readfile('$INSTALL_ROOT/status/scheduled-success.json'), '$.trigger');") == scheduled ]]
LAST_SUCCESS=$(sha256sum "$INSTALL_ROOT/status/last-success.json")
SCHEDULED_SUCCESS=$(sha256sum "$INSTALL_ROOT/status/scheduled-success.json")
mv "$INSTALL_ROOT/app.env" "$INSTALL_ROOT/app.env.test-preserved"
if bash "$TEST_ROOT/backup.sh"; then printf 'Missing config must fail the backup.\n' >&2; exit 1; fi
mv "$INSTALL_ROOT/app.env.test-preserved" "$INSTALL_ROOT/app.env"
[[ $(sqlite3 :memory: "SELECT json_extract(readfile('$INSTALL_ROOT/status/backup.json'), '$.status');") == failed ]]
[[ "$LAST_SUCCESS" == "$(sha256sum "$INSTALL_ROOT/status/last-success.json")" ]]
[[ "$SCHEDULED_SUCCESS" == "$(sha256sum "$INSTALL_ROOT/status/scheduled-success.json")" ]]
STATUS_HASH=$(sha256sum "$INSTALL_ROOT/status/backup.json")
flock /run/buksza-backup.lock bash "$TEST_ROOT/backup.sh"
[[ "$STATUS_HASH" == "$(sha256sum "$INSTALL_ROOT/status/backup.json")" ]]
if bash "$TEST_ROOT/backup.sh" --unexpected; then exit 1; fi
printf '\nInstaller tests: domain/input validation, empty root adoption, nonempty/file/symlink refusal, config/key preservation, permissions and self-extraction passed.\n'
printf 'Backup reporting: atomic metadata, manual/scheduled distinction, failure preservation and lock exclusion passed.\n'
