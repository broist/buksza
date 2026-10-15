#!/usr/bin/env bash
# Buksza Ubuntu installer. The downloadable version embeds an application archive below.
set -Eeuo pipefail
umask 077

DOMAIN=buksza.duktor.hu
INSTALL_ROOT=/opt/buksza
CHECK_ONLY=0
EXTRACT_ONLY=''
PAYLOAD_SHA256='@PAYLOAD_SHA256@'
QUIESCED=0
COMMITTED=0
OLD_RELEASE=''
OLD_PATH=''
HOST_NGINX=0
HTTP_PORT=18083
NGINX_ROOT=/etc/nginx
CERT_ROOT=/etc/letsencrypt
ACME_ROOT=/var/lib/buksza-acme
HOST_FILE=''
HOST_PREVIOUS_CONFIG=''
HOST_CONFIG_CHANGED=0

say() { printf '\n[Buksza] %s\n' "$*"; }
fail() { printf '\n[Buksza] HIBA: %s\n' "$*" >&2; return 1; }
usage() {
    cat <<'HELP'
Buksza telepítő Ubuntu 22.04/24.04/26.04 szerverre.
Használat: sudo bash buksza-telepito.sh [--domain buksza.duktor.hu]
Meglévő Ubuntu Nginx mellé: sudo bash buksza-telepito.sh --existing-nginx
Ellenőrzés változtatás nélkül: bash buksza-telepito.sh --check
Forráscsomag kibontása: bash buksza-telepito.sh --extract /uj/konyvtar
A domain a szerverre mutasson; AWS Security Group: bejövő TCP 80/443 legyen engedélyezve.
HELP
}
parse_args() {
    while (($#)); do
        case "$1" in
            --domain) [[ $# -ge 2 ]] || { fail 'Hiányzó domain.'; return 1; }; DOMAIN=$2; shift 2 ;;
            --check) CHECK_ONLY=1; shift ;;
            --existing-nginx) HOST_NGINX=1; shift ;;
            --extract) [[ $# -ge 2 ]] || { fail 'Hiányzó kibontási könyvtár.'; return 1; }; EXTRACT_ONLY=$2; shift 2 ;;
            --help|-h) usage; exit 0 ;;
            *) fail "Ismeretlen kapcsoló: $1"; return 1 ;;
        esac
    done
    DOMAIN=${DOMAIN,,}
    [[ ${#DOMAIN} -le 253 && "$DOMAIN" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$ ]] || { fail 'Érvénytelen domainnév.'; return 1; }
}
extract_payload() {
    local target=$1 archive marker
    [[ ! -e "$target" ]] || { fail 'A kibontási könyvtár már létezik. Válassz új könyvtárat.'; return 1; }
    marker=$(awk '/^# __BUKSZA_PAYLOAD__$/ {print NR+1; exit}' "${BASH_SOURCE[0]}")
    [[ -n "$marker" && "$PAYLOAD_SHA256" =~ ^[a-f0-9]{64}$ ]] || { fail 'Ez a fejlesztői sablon, nem a csomagolt telepítő. Futtasd a csomagkészítőt.'; return 1; }
    archive=$(mktemp)
    tail -n +"$marker" "${BASH_SOURCE[0]}" | base64 --decode > "$archive"
    if [[ $(sha256sum "$archive" | awk '{print $1}') != "$PAYLOAD_SHA256" ]]; then
        rm -f -- "$archive"
        fail 'Sérült telepítőcsomag; az ellenőrző összeg eltér.'; return 1
    fi
    mkdir -p -- "$target"
    tar -xzf "$archive" -C "$target" --no-same-owner
    rm -f -- "$archive"
}
check_platform() {
    [[ -r /etc/os-release ]] || { fail 'Nem azonosítható az operációs rendszer.'; return 1; }
    # shellcheck disable=SC1091
    . /etc/os-release
    [[ "$ID" == ubuntu ]] || { fail 'A script Ubuntu szerverre készült.'; return 1; }
    case "$VERSION_ID" in 22.04|24.04|26.04) ;; *) fail "Nem támogatott Ubuntu: $VERSION_ID. Ubuntu 22.04, 24.04 vagy 26.04 szükséges."; return 1 ;; esac
    case "$(uname -m)" in x86_64|aarch64) ;; *) fail '64 bites x86 vagy ARM szerver szükséges.'; return 1 ;; esac
    say "Ubuntu $VERSION_ID; domain: $DOMAIN"
}
check_ports() {
    command -v ss >/dev/null || { fail 'Az iproute2 (ss) parancs hiányzik.'; return 1; }
    if [[ "$HOST_NGINX" == 1 ]]; then check_host_nginx; return; fi
    local listeners owned
    listeners=$(ss -H -ltn '( sport = :80 or sport = :443 )')
    [[ -n "$listeners" ]] || return 0
    owned=''
    if command -v docker >/dev/null && docker info >/dev/null 2>&1; then
        owned=$(docker ps -q --filter label=com.docker.compose.project=buksza --filter label=com.docker.compose.service=caddy)
    fi
    [[ -n "$owned" && -f "$INSTALL_ROOT/.buksza-install" ]] || { fail 'A 80/443-as port már foglalt. A telepítő nem állít le meglévő webhelyet. Szabad portokkal rendelkező szerveren futtasd.'; return 1; }
}
load_proxy_settings() {
    local saved_mode saved_port
    if [[ -f "$INSTALL_ROOT/proxy.env" ]]; then
        saved_mode=$(sed -n 's/^BUKSZA_PROXY_MODE=//p' "$INSTALL_ROOT/proxy.env")
        saved_port=$(sed -n 's/^BUKSZA_HTTP_PORT=//p' "$INSTALL_ROOT/proxy.env")
        case "$saved_mode" in
            nginx) HOST_NGINX=1 ;;
            caddy) [[ "$HOST_NGINX" == 0 ]] || { fail 'A meglévő Caddy-telepítés átállítása külön migrációt igényel.'; return 1; } ;;
            *) fail 'Ismeretlen mentett proxybeállítás.'; return 1 ;;
        esac
        [[ "$saved_port" =~ ^[1-9][0-9]{3,4}$ && "$saved_port" -ge 1024 && "$saved_port" -le 65535 ]] || { fail 'Hibás mentett belső port.'; return 1; }
        HTTP_PORT=$saved_port
    elif [[ "$HOST_NGINX" == 1 && -e "$INSTALL_ROOT/current" ]]; then
        fail 'A korábbi, Caddy-alapú telepítés átállítása külön migrációt igényel.'; return 1
    fi
}
nginx_uses_domain() {
    sed '/^[[:space:]]*#/d;s/[[:space:]]#.*$//' | awk -v domain="$DOMAIN" 'BEGIN{RS=";"} {for(i=1;i<=NF;i++) if($i=="server_name") for(j=i+1;j<=NF;j++){gsub(/"/,"",$j); if($j==domain)found=1}} END{exit !found}'
}
check_host_nginx() {
    local dump owned candidate
    command -v nginx >/dev/null || { fail 'Az --existing-nginx módhoz telepített Nginx szükséges.'; return 1; }
    systemctl is-active --quiet nginx || { fail 'A meglévő Nginx nem fut.'; return 1; }
    nginx -t
    dump=$(nginx -T 2>&1)
    grep -Fq "$NGINX_ROOT/conf.d/*.conf" <<< "$dump" || { fail 'Az Nginx conf.d/*.conf betöltése nem található. A telepítő nem módosítja a globális nginx.conf fájlt.'; return 1; }
    HOST_FILE="$NGINX_ROOT/conf.d/buksza-$DOMAIN.conf"
    if [[ -e "$HOST_FILE" || -L "$HOST_FILE" ]]; then
        if [[ -L "$HOST_FILE" || ! -f "$HOST_FILE" || ! -f "$INSTALL_ROOT/.buksza-install" ]] || ! grep -Fxq "# Buksza installer managed: $DOMAIN" "$HOST_FILE"; then
            fail 'A Buksza nevű Nginx-fájl már létezik, de nem a telepítőhöz tartozik.'; return 1
        fi
    elif nginx_uses_domain <<< "$dump"; then
        fail "A $DOMAIN névhez már létezik másik Nginx-beállítás; nem írjuk felül."; return 1
    fi
    if [[ -e "$CERT_ROOT/renewal/buksza-$DOMAIN.conf" && ! -f "$INSTALL_ROOT/.buksza-install" ]]; then
        fail 'A Buksza nevű tanúsítvány már létezik. A meglévő tanúsítványt nem módosítjuk automatikusan.'; return 1
    fi
    owned=''
    if command -v docker >/dev/null && docker info >/dev/null 2>&1; then
        owned=$(docker ps -q --filter label=com.docker.compose.project=buksza --filter label=com.docker.compose.service=web)
    fi
    if [[ -f "$INSTALL_ROOT/proxy.env" ]]; then
        if [[ -n $(ss -H -ltn "sport = :$HTTP_PORT") && -z "$owned" ]]; then fail "A mentett $HTTP_PORT belső portot más szolgáltatás használja."; return 1; fi
    else
        for ((candidate=18083; candidate<=18099; candidate++)); do
            if [[ -z $(ss -H -ltn "sport = :$candidate") ]]; then HTTP_PORT=$candidate; break; fi
        done
        [[ "$candidate" -le 18099 ]] || { fail 'Nincs szabad belső port a 18083–18099 tartományban.'; return 1; }
    fi
    say "Meglévő Nginx használata; a Buksza belső címe: 127.0.0.1:$HTTP_PORT"
}
write_host_config() {
    local mode=$1 temporary dump
    if [[ "$HOST_CONFIG_CHANGED" == 0 && -f "$HOST_FILE" ]]; then
        HOST_PREVIOUS_CONFIG="$INSTALL_ROOT/backups/nginx-$(date -u +%Y%m%dT%H%M%S)-$RANDOM.conf"
        cp -- "$HOST_FILE" "$HOST_PREVIOUS_CONFIG"
    fi
    temporary=$(mktemp "$NGINX_ROOT/conf.d/.buksza.XXXXXX")
    sed -e "s|__BUKSZA_DOMAIN__|$DOMAIN|g" -e "s|__BUKSZA_HTTP_PORT__|$HTTP_PORT|g" \
        -e "s|__BUKSZA_ACME_ROOT__|$ACME_ROOT|g" -e "s|__BUKSZA_CERT_ROOT__|$CERT_ROOT|g" \
        -e "s|__BUKSZA_CERT_NAME__|buksza-$DOMAIN|g" \
        "$RELEASE_PATH/docker/production/host-nginx.$mode.conf" > "$temporary"
    chmod 0644 "$temporary"
    mv -- "$temporary" "$HOST_FILE"
    HOST_CONFIG_CHANGED=1
    nginx -t
    dump=$(nginx -T 2>&1)
    grep -Fq "# configuration file $HOST_FILE:" <<< "$dump" || { fail 'Az új Buksza-konfigurációt az Nginx nem töltötte be.'; return 1; }
    systemctl reload nginx
}
restore_host_config() {
    [[ "$HOST_CONFIG_CHANGED" == 1 ]] || return 0
    if [[ -n "$HOST_PREVIOUS_CONFIG" ]]; then
        cp -- "$HOST_PREVIOUS_CONFIG" "$HOST_FILE"
    else
        rm -f -- "$HOST_FILE"
    fi
    if nginx -t; then systemctl reload nginx; fi
}
prepare_host_tls() {
    [[ "$HOST_NGINX" == 1 ]] || return 0
    say 'A Buksza saját Nginx-beállítása és HTTPS-tanúsítványa…'
    install -d -m 0755 "$ACME_ROOT" "$ACME_ROOT/.well-known" "$ACME_ROOT/.well-known/acme-challenge"
    install_certificate_hook
    if [[ ! -f "$HOST_FILE" ]]; then write_host_config http; fi
    certbot certonly --webroot --webroot-path "$ACME_ROOT" --domain "$DOMAIN" \
        --cert-name "buksza-$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email \
        --keep-until-expiring
    [[ -s "$CERT_ROOT/live/buksza-$DOMAIN/fullchain.pem" && -s "$CERT_ROOT/live/buksza-$DOMAIN/privkey.pem" ]] || { fail 'A tanúsítvány fájljai nem jöttek létre.'; return 1; }
}
install_certificate_timer() {
    [[ "$HOST_NGINX" == 1 ]] || return 0
    local certbot_path
    certbot_path=$(command -v certbot)
    cat > /etc/systemd/system/buksza-cert-renew.service <<SERVICE
[Unit]
Description=Buksza sajat HTTPS tanusitvany megujitasa
[Service]
Type=oneshot
ExecStart=$certbot_path renew --cert-name buksza-$DOMAIN --quiet
SERVICE
    cat > /etc/systemd/system/buksza-cert-renew.timer <<'TIMER'
[Unit]
Description=Buksza tanusitvany ellenorzes
[Timer]
OnCalendar=*-*-* 04,16:00:00 Europe/Budapest
RandomizedDelaySec=1800
Persistent=true
[Install]
WantedBy=timers.target
TIMER
    systemctl daemon-reload
    systemctl enable --now buksza-cert-renew.timer
}
install_certificate_hook() {
    local hook="$CERT_ROOT/renewal-hooks/deploy/buksza-$DOMAIN.sh"
    install -d -m 0755 "$CERT_ROOT/renewal-hooks/deploy"
    if [[ -e "$hook" || -L "$hook" ]]; then
        if [[ -L "$hook" || ! -f "$hook" ]] || ! grep -Fxq "# Buksza installer managed: $DOMAIN" "$hook"; then
            fail 'A Buksza tanúsítványmegújító hook nevét más fájl foglalja.'; return 1
        fi
    fi
    cat > "$hook" <<HOOK
#!/bin/sh
# Buksza installer managed: $DOMAIN
set -eu
[ "\${RENEWED_LINEAGE:-}" = "$CERT_ROOT/live/buksza-$DOMAIN" ] || exit 0
/usr/sbin/nginx -t
/bin/systemctl reload nginx
HOOK
    chmod 0700 "$hook"
}
check_dns() {
    local addresses public_ip
    addresses=$(getent ahostsv4 "$DOMAIN" | awk '{print $1}' | sort -u)
    [[ -n "$addresses" ]] || { fail 'A domainnek nincs feloldható IPv4-címe. Előbb állítsd be a DNS A-rekordot.'; return 1; }
    say "DNS IPv4: $addresses"
    if command -v curl >/dev/null; then
        public_ip=$(curl -4fsS --connect-timeout 3 --max-time 8 https://checkip.amazonaws.com 2>/dev/null || true)
        if [[ "$public_ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] && ! grep -Fxq "$public_ip" <<< "$addresses"; then
            fail "A szerver publikus címe $public_ip, a domain másik címre mutat. Állítsd a DNS A-rekordot erre a szerverre."; return 1
        fi
    fi
}
install_dependencies() {
    say 'Szerveroldali függőségek előkészítése…'
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends ca-certificates curl openssl sqlite3 iproute2
    if [[ "$HOST_NGINX" == 1 ]] && ! command -v certbot >/dev/null; then apt-get install -y --no-install-recommends certbot; fi
    if ! command -v docker >/dev/null; then
        local codename architecture conflicts
        conflicts=$(dpkg-query -W -f='${db:Status-Abbrev} ${binary:Package}\n' docker.io podman-docker containerd runc 2>/dev/null | awk '$1=="ii" {print $2}' || true)
        [[ -z "$conflicts" ]] || { fail "Meglévő, eltérő konténercsomagok vannak: $conflicts. Ezeket a telepítő nem távolítja el automatikusan."; return 1; }
        codename=${UBUNTU_CODENAME:-$VERSION_CODENAME}
        architecture=$(dpkg --print-architecture)
        install -m 0755 -d /etc/apt/keyrings
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
        chmod a+r /etc/apt/keyrings/docker.asc
        cat > /etc/apt/sources.list.d/buksza-docker.sources <<REPO
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $codename
Components: stable
Architectures: $architecture
Signed-By: /etc/apt/keyrings/docker.asc
REPO
        apt-get update
        apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    fi
    docker compose version >/dev/null || { fail 'A Docker Compose plugin hiányzik; telepítsd a meglévő Dockerhez.'; return 1; }
    systemctl enable --now docker
    docker info >/dev/null
}
prepare_root() {
    [[ ! -L "$INSTALL_ROOT" ]] || { fail 'A telepítési könyvtár nem lehet szimbolikus link.'; return 1; }
    if [[ -e "$INSTALL_ROOT" ]]; then
        [[ -d "$INSTALL_ROOT" ]] || { fail 'A /opt/buksza útvonalat egy fájl foglalja.'; return 1; }
        if [[ ! -f "$INSTALL_ROOT/.buksza-install" ]] || ! grep -Fxq 'buksza-installer-v1' "$INSTALL_ROOT/.buksza-install"; then
            local existing_entry
            existing_entry=$(find "$INSTALL_ROOT" -mindepth 1 -maxdepth 1 -print -quit)
            if [[ -n "$existing_entry" ]]; then
                fail 'A /opt/buksza nem üres, és nem ehhez a telepítőhöz tartozik. A tartalmát nem módosítottuk.'; return 1
            fi
            install -d -m 0755 -o root -g root "$INSTALL_ROOT"
            printf 'buksza-installer-v1\n' > "$INSTALL_ROOT/.buksza-install"
        fi
    else
        install -d -m 0755 "$INSTALL_ROOT"
        printf 'buksza-installer-v1\n' > "$INSTALL_ROOT/.buksza-install"
    fi
    chmod 0755 "$INSTALL_ROOT"
    install -d -m 0700 "$INSTALL_ROOT/releases" "$INSTALL_ROOT/backups" "$INSTALL_ROOT/bin"
    install -d -m 0755 -o root -g root "$INSTALL_ROOT/status"
    if [[ ! -d "$INSTALL_ROOT/data" ]]; then install -d -m 0750 "$INSTALL_ROOT/data"; fi
    if [[ -L "$INSTALL_ROOT/current" ]]; then
        OLD_PATH=$(readlink -f "$INSTALL_ROOT/current")
        [[ "$OLD_PATH" == "$INSTALL_ROOT/releases/"* && -f "$OLD_PATH/release.env" ]] || { fail 'A korábbi kiadás útvonala nem érvényes.'; return 1; }
        OLD_RELEASE=$(sed -n 's/^BUKSZA_RELEASE=//p' "$OLD_PATH/release.env")
        [[ "$OLD_RELEASE" =~ ^[a-z0-9-]+$ ]] || { fail 'Hibás korábbi kiadásazonosító.'; return 1; }
    elif [[ -e "$INSTALL_ROOT/current" ]]; then
        fail 'A current útvonalat már más állomány foglalja.'; return 1
    fi
}
prepare_environment() {
    if [[ -f "$INSTALL_ROOT/app.env" ]]; then
        grep -Fxq "APP_URL=https://$DOMAIN" "$INSTALL_ROOT/app.env" || { fail 'A meglévő telepítés más domainhez tartozik; a megőrzéséhez annak domainjével futtasd a scriptet.'; return 1; }
        grep -Eq '^APP_KEY=base64:.+$' "$INSTALL_ROOT/app.env" || { fail 'A meglévő APP_KEY hiányzik; a telepítő nem cseréli le a titkosítási kulcsot.'; return 1; }
        return
    fi
    [[ ! -s "$INSTALL_ROOT/data/database.sqlite" ]] || { fail 'Meglévő adatbázishoz hiányzik az app.env. Állítsd vissza a hozzá tartozó konfigurációt.'; return 1; }
    local app_key setup_token
    app_key=$(openssl rand -base64 32)
    setup_token=$(openssl rand -hex 32)
    cat > "$INSTALL_ROOT/app.env" <<ENV
APP_NAME=Buksza
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$app_key
BUKSZA_SETUP_TOKEN=$setup_token
APP_URL=https://$DOMAIN
APP_TIMEZONE=Europe/Budapest
APP_LOCALE=hu
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=/data/database.sqlite
SESSION_DRIVER=database
SESSION_COOKIE=buksza_session
SESSION_LIFETIME=43200
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=log
ENV
    chmod 0600 "$INSTALL_ROOT/app.env"
}
install_backup() {
    install -m 0700 "$RELEASE_PATH/scripts/server-backup.sh" "$INSTALL_ROOT/bin/backup.sh"
    cat > /etc/systemd/system/buksza-backup.service <<'SERVICE'
[Unit]
Description=Buksza SQLite es konfiguracio mentes
[Service]
Type=oneshot
ExecStart=/opt/buksza/bin/backup.sh --scheduled
UMask=0077
SERVICE
    cat > /etc/systemd/system/buksza-backup.timer <<'TIMER'
[Unit]
Description=Buksza napi mentes
[Timer]
OnCalendar=*-*-* 03:00:00 Europe/Budapest
Persistent=true
[Install]
WantedBy=timers.target
TIMER
    systemctl daemon-reload
    systemctl enable --now buksza-backup.timer
}
compose_at() {
    local source_path=$1
    shift
    local options=(-p buksza -f "$source_path/docker/production/compose.yaml")
    if [[ "$HOST_NGINX" == 1 ]]; then options+=(-f "$source_path/docker/production/compose.host-nginx.yaml"); fi
    docker compose "${options[@]}" "$@"
}
dc() { compose_at "$RELEASE_PATH" "$@"; }
start_services() {
    local source_path=$1
    if [[ "$HOST_NGINX" == 1 ]]; then compose_at "$source_path" up -d app web scheduler; else compose_at "$source_path" up -d app web scheduler caddy; fi
}
on_failure() {
    local result=$?
    trap - ERR
    printf '\n[Buksza] A telepítés nem fejeződött be (kód: %s). Az adatbázist nem töröltük.\n' "$result" >&2
    if [[ "$HOST_NGINX" == 1 && "$COMMITTED" == 0 ]]; then restore_host_config || true; fi
    if [[ "$QUIESCED" == 1 && "$COMMITTED" == 0 && -n "$OLD_PATH" ]]; then
        say 'A korábbi szolgáltatások újraindítása…'
        BUKSZA_RELEASE="$OLD_RELEASE" start_services "$OLD_PATH" || true
        BUKSZA_RELEASE="$OLD_RELEASE" compose_at "$OLD_PATH" exec -T app php artisan up || true
    fi
    exit "$result"
}
deploy() {
    say 'Alkalmazásképek építése (az első alkalom néhány perc)…'
    dc build app web
    dc config --quiet
    if command -v ufw >/dev/null && ufw status | grep -q '^Status: active'; then
        ufw allow 80/tcp comment 'Buksza HTTPS tanusitvany'
        ufw allow 443/tcp comment 'Buksza HTTPS'
    fi
    prepare_host_tls
    if [[ -n "$OLD_PATH" ]]; then
        say 'Frissítés előtti adatbázis- és kulcsmentés…'
        dc exec -T app php artisan down --retry=60
        QUIESCED=1
        dc stop scheduler app web
        "$INSTALL_ROOT/bin/backup.sh"
    fi
    if [[ ! -e "$INSTALL_ROOT/data/database.sqlite" ]]; then
        touch "$INSTALL_ROOT/data/database.sqlite"
    fi
    dc run --rm --no-deps app php artisan migrate --seed --force --no-interaction
    dc run --rm --no-deps app php artisan buksza:push-keys
    start_services "$RELEASE_PATH"
    dc exec -T app php artisan up
    local attempt
    for attempt in $(seq 1 30); do
        if dc exec -T web wget -q -O /dev/null http://127.0.0.1:8080/up; then break; fi
        sleep 2
    done
    dc exec -T web wget -q -O /dev/null http://127.0.0.1:8080/up
    if [[ "$HOST_NGINX" == 1 ]]; then write_host_config https; install_certificate_timer; fi
    ln -sfn "$RELEASE_PATH" "$INSTALL_ROOT/current"
    COMMITTED=1
    "$INSTALL_ROOT/bin/backup.sh"
    say 'HTTPS-tanúsítvány és elérés ellenőrzése…'
    for ((attempt=1; attempt<=24; attempt++)); do
        if curl -fsS --connect-timeout 3 --max-time 5 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/up" >/dev/null 2>&1; then
            say "Kész: https://$DOMAIN"
            printf 'Első belépéskor add meg a saját nevedet és jelszavadat.\n'
            if dc exec -T app php artisan buksza:setup-link; then :; else return 1; fi
            printf 'iPhone: Safari → Megosztás → Hozzáadás a Főképernyőhöz → Beállítások → Telefonos emlékeztetők.\n'
            printf 'Napi mentés: 03:00 Budapest szerint; könyvtár: /opt/buksza/backups.\n'
            return
        fi
        sleep 5
    done
    fail 'Az alkalmazás fut, de a HTTPS még nem igazolt. Ellenőrizd a DNS A/AAAA rekordokat és az AWS Security Group bejövő TCP 80/443 szabályait. Napló: sudo bash /opt/buksza/current/scripts/server-status.sh'
}
main() {
    parse_args "$@"
    if [[ -n "$EXTRACT_ONLY" ]]; then extract_payload "$EXTRACT_ONLY"; say 'Forráscsomag kibontva.'; return; fi
    check_platform
    if [[ "$CHECK_ONLY" == 0 ]]; then
        [[ $EUID == 0 ]] || { fail 'Futtasd sudo bash buksza-telepito.sh paranccsal.'; return 1; }
        if ! command -v ss >/dev/null; then
            export DEBIAN_FRONTEND=noninteractive
            apt-get update
            apt-get install -y --no-install-recommends iproute2
        fi
    fi
    load_proxy_settings
    check_ports
    check_dns
    if [[ "$CHECK_ONLY" == 1 ]]; then say 'Előellenőrzés kész. A script nem módosított semmit; az AWS portszabályokat külön ellenőrizd.'; return; fi
    [[ "$PAYLOAD_SHA256" =~ ^[a-f0-9]{64}$ ]] || { fail 'Ez csak a telepítő sablonja. A dist/buksza-telepito.sh csomagolt fájlt használd.'; return 1; }
    command -v flock >/dev/null || { fail 'A flock parancs hiányzik.'; return 1; }
    exec 9>/run/buksza-installer.lock
    flock -n 9 || { fail 'Már fut egy Buksza-telepítés.'; return 1; }
    install_dependencies
    check_dns
    prepare_root
    prepare_environment
    export BUKSZA_HOME=$INSTALL_ROOT BUKSZA_DOMAIN=$DOMAIN BUKSZA_HTTP_PORT=$HTTP_PORT
    if [[ "$HOST_NGINX" == 1 ]]; then BUKSZA_PROXY_MODE=nginx; else BUKSZA_PROXY_MODE=caddy; fi
    export BUKSZA_PROXY_MODE
    printf 'BUKSZA_PROXY_MODE=%s\nBUKSZA_HTTP_PORT=%s\n' "$BUKSZA_PROXY_MODE" "$HTTP_PORT" > "$INSTALL_ROOT/proxy.env"
    BUKSZA_RELEASE="$(date -u +%Y%m%d%H%M%S)-${PAYLOAD_SHA256:0:12}"
    export BUKSZA_RELEASE
    RELEASE_PATH="$INSTALL_ROOT/releases/$BUKSZA_RELEASE"
    extract_payload "$RELEASE_PATH"
    printf 'BUKSZA_RELEASE=%s\nBUKSZA_HOME=%s\nBUKSZA_DOMAIN=%s\nBUKSZA_PROXY_MODE=%s\nBUKSZA_HTTP_PORT=%s\n' "$BUKSZA_RELEASE" "$INSTALL_ROOT" "$DOMAIN" "$BUKSZA_PROXY_MODE" "$HTTP_PORT" > "$RELEASE_PATH/release.env"
    install_backup
    deploy
}
if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    trap on_failure ERR
    main "$@"
    exit 0
fi
# __BUKSZA_PAYLOAD__
