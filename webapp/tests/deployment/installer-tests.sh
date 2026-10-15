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
if (prepare_root); then exit 1; fi
printf 'buksza-installer-v1\n' > "$INSTALL_ROOT/.buksza-install"
prepare_root
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
printf '\nInstaller tests: domain/input validation, existing directory refusal, config/key preservation, permissions and self-extraction passed.\n'
