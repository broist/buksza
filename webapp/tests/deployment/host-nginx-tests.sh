#!/usr/bin/env bash
set -Eeuo pipefail
# Disposable Linux container only; real Nginx, isolated configuration and certificates.
source /work/scripts/install-ubuntu.sh
TEST_ROOT=$(mktemp -d /tmp/buksza-nginx-test.XXXXXX)
INSTALL_ROOT="$TEST_ROOT/install"
NGINX_ROOT="$TEST_ROOT/nginx"
CERT_ROOT="$TEST_ROOT/certs"
ACME_ROOT="$TEST_ROOT/acme"
RELEASE_PATH=/work
HOST_NGINX=1
mkdir -p "$NGINX_ROOT/conf.d" "$ACME_ROOT/.well-known/acme-challenge"
prepare_root
cat > "$NGINX_ROOT/nginx.conf" <<CONF
pid $TEST_ROOT/nginx.pid;
error_log stderr warn;
events {}
http {
 access_log off;
 include $NGINX_ROOT/conf.d/*.conf;
}
CONF
printf 'server {listen 80; server_name kcal.duktor.hu; location / {return 200 "kcal preserved";}}\n' > "$NGINX_ROOT/conf.d/kmester.conf"
printf 'server {listen 80; server_name monitor.acuwall.hu; location / {return 200 "monitor preserved";}}\n' > "$NGINX_ROOT/conf.d/monitor.conf"
nginx() { command nginx -c "$NGINX_ROOT/nginx.conf" "$@"; }
systemctl() {
    case "$1" in
        is-active) return 0 ;;
        reload) if [[ -s "$TEST_ROOT/nginx.pid" ]]; then nginx -s reload; fi ;;
        *) return 1 ;;
    esac
}
docker() { return 1; }
ss() { if [[ "$*" == *18083* ]]; then printf 'occupied\n'; fi; }
BEFORE=$(sha256sum "$NGINX_ROOT/conf.d/kmester.conf" "$NGINX_ROOT/conf.d/monitor.conf")
check_host_nginx
[[ "$HTTP_PORT" == 18084 ]]
if (printf 'server_name\n "buksza.duktor.hu";\n' | nginx_uses_domain); then :; else exit 1; fi
if printf '# server_name buksza.duktor.hu;\nserver_name kcal.duktor.hu;\n' | nginx_uses_domain; then exit 1; fi
printf 'server {listen 80; server_name\n "buksza.duktor.hu";}\n' > "$NGINX_ROOT/conf.d/foreign.conf"
if (check_host_nginx); then exit 1; fi
rm "$NGINX_ROOT/conf.d/foreign.conf"
printf '# foreign config\n' > "$HOST_FILE"
if (check_host_nginx); then exit 1; fi
rm "$HOST_FILE"
ln -s "$NGINX_ROOT/conf.d/kmester.conf" "$HOST_FILE"
if (check_host_nginx); then exit 1; fi
rm "$HOST_FILE"
write_host_config http
printf 'acme-ok' > "$ACME_ROOT/.well-known/acme-challenge/probe"
chmod -R a+rX "$TEST_ROOT"
nginx
trap 'nginx -s quit || true' EXIT
[[ $(curl -fsS -H "Host: $DOMAIN" http://127.0.0.1/.well-known/acme-challenge/probe) == acme-ok ]]
restore_host_config
[[ ! -f "$HOST_FILE" ]]
HOST_CONFIG_CHANGED=0
write_host_config http
HOST_CONFIG_CHANGED=0
HOST_PREVIOUS_CONFIG=''
mkdir -p "$CERT_ROOT/live/buksza-$DOMAIN"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=$DOMAIN" \
    -keyout "$CERT_ROOT/live/buksza-$DOMAIN/privkey.pem" -out "$CERT_ROOT/live/buksza-$DOMAIN/fullchain.pem" >/dev/null 2>&1
printf 'server {listen %s; location / {return 200 "Buksza upstream";}}\n' "$HTTP_PORT" > "$NGINX_ROOT/conf.d/qa-upstream.conf"
write_host_config https
for attempt in $(seq 1 20); do
    if curl -kfsS --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/up" > "$TEST_ROOT/response"; then break; fi
    sleep 0.1
done
[[ $(cat "$TEST_ROOT/response") == 'Buksza upstream' ]]
[[ $(curl -fsS -H 'Host: kcal.duktor.hu' http://127.0.0.1/) == 'kcal preserved' ]]
[[ $(curl -fsS -H 'Host: monitor.acuwall.hu' http://127.0.0.1/) == 'monitor preserved' ]]
[[ $(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1/) == 301 ]]
[[ "$BEFORE" == "$(sha256sum "$NGINX_ROOT/conf.d/kmester.conf" "$NGINX_ROOT/conf.d/monitor.conf")" ]]
install_certificate_hook
HOOK="$CERT_ROOT/renewal-hooks/deploy/buksza-$DOMAIN.sh"
RENEWED_LINEAGE="$CERT_ROOT/live/other-site" sh "$HOOK"
[[ $(stat -c '%a' "$HOOK") == 700 ]]
grep -Fq "$CERT_ROOT/live/buksza-$DOMAIN" "$HOOK"
restore_host_config
if grep -q 'listen 443' "$HOST_FILE"; then exit 1; fi
[[ "$BEFORE" == "$(sha256sum "$NGINX_ROOT/conf.d/kmester.conf" "$NGINX_ROOT/conf.d/monitor.conf")" ]]
printf 'BUKSZA_PROXY_MODE=nginx\nBUKSZA_HTTP_PORT=18084\n' > "$INSTALL_ROOT/proxy.env"
HOST_NGINX=0
load_proxy_settings
[[ "$HOST_NGINX" == 1 && "$HTTP_PORT" == 18084 ]]
printf '\nHost Nginx tests passed: occupied ports, domain/file collisions, ACME, TLS proxy, redirects, existing sites, rollback, scoped renewal and saved mode.\n'
