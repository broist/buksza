# Buksza telepítése Ubuntura, egy fájlból

## Telepítés és frissítés GitHubról

A projekt és a csomagolt telepítő tárolója: [broist/buksza](https://github.com/broist/buksza).

A szerveren az alkalmazás felhasználó által megadott helye **`/opt/buksza`**; a telepítő alapból ezt használja. Az aktív kiadás `/opt/buksza/current`, az adatbázis `/opt/buksza/data/database.sqlite`, a mentések `/opt/buksza/backups` alatt vannak. Az alábbi `~/buksza-forras` csak a Gitből letöltött forrás és telepítő helye; a futó alkalmazás a telepítés után `/opt/buksza` alatt működik.

A `/opt/buksza` könyvtárat a telepítő létrehozza, illetve egy előre létrehozott, teljesen üres könyvtárat is használhat. Idegen tartalom esetén (rejtett fájlokkal együtt) megáll, és nem írja felül azt. A saját jelölőfájllal rendelkező korábbi telepítést frissíti.

Első alkalommal az Ubuntu-szerveren, az `ubuntu` felhasználóval:

```bash
git clone https://github.com/broist/buksza.git ~/buksza-forras
cd ~/buksza-forras/dist
sha256sum --check buksza-telepito.sha256
sudo bash buksza-telepito.sh --existing-nginx
```

Későbbi frissítés:

```bash
cd ~/buksza-forras
git pull --ff-only
cd dist
sha256sum --check buksza-telepito.sha256
sudo bash buksza-telepito.sh --existing-nginx
```

Csak sikeres letöltés és ellenőrzés után futtasd a következő parancsot. Privát GitHub-tárolónál a szervernek olvasási hozzáférés szükséges; GitHub-jelszó nem használható Git-hitelesítésre. A forráskönyvtár külön van az éles `/opt/buksza` adataitól. A `git pull` önmagában csak a forrást frissíti; az új Docker-képek építését és a váltást a telepítő végzi. A telepítő az éles adatbázist és alkalmazáskulcsot megőrzi, frissítés előtt mentést készít.

## Ezt a fájlt használd

`dist/buksza-telepito.sh` – a csomagolt telepítő, amely az alkalmazás forrását is tartalmazza. A `scripts/install-ubuntu.sh` csak a fejlesztői sablon; a szerveren a **dist** fájl szükséges. A csomag nem tartalmaz helyi adatbázist, `.env`-et, jelszót, felhasználói adatot vagy privát kulcsot.

Másold fel a `buksza-telepito.sh` fájlt az Ubuntu-szerverre, például SFTP/WinSCP segítségével. A fájl könyvtárában:

```bash
sudo bash buksza-telepito.sh --existing-nginx
```

A domain alapból **buksza.duktor.hu**, a megadott AWS-szerver címe **35.158.45.80**, rendszere Ubuntu **24.04.5 LTS**. A meglévő Nginx a `kcal.duktor.hu` és `monitor.acuwall.hu` oldalakat szolgálja ki, ezért ezen a szerveren az **--existing-nginx** kapcsoló szükséges. Másik domainhez add hozzá: `--domain masik.hu`. Külön Git-letöltésre vagy a teljes projekt feltöltésére nincs szükség; PHP/Node a Docker építési környezetében fut. Az első építés néhány percet igényelhet, és internet szükséges a csomagok/konténerképek letöltéséhez.

A telepítő saját fájlja: `/etc/nginx/conf.d/buksza-buksza.duktor.hu.conf`. A meglévő oldalak konfigurációjához és tanúsítványaihoz nem nyúl. Ellenőrzi az Nginx beállításait, majd újratöltést kér. A Docker webkonténer csak a szerver saját címén érhető el: alapból **127.0.0.1:18083**; foglaltság esetén a 18084–18099 tartományból választ. Ezt a portot nem kell megnyitni az AWS-ben. A böngésző címe **https://buksza.duktor.hu** (443).

A HTTPS-hez Certbot webroot ellenőrzést használ, saját `buksza-buksza.duktor.hu` tanúsítvánnyal és a `/var/lib/buksza-acme` ellenőrzési könyvtárral. A saját megújító időzítő naponta kétszer ellenőriz; a megújítás utáni, saját tanúsítványra korlátozott hook az Nginxet újratölti. A meglévő Certbot-megújítások is használhatják ezt a hookot. A szolgáltatásokat az Nginx újratöltése nem állítja le.

## Két külső feltétel

1. A `buksza.duktor.hu` DNS A-rekordja `35.158.45.80` címre mutasson. Cloudflare-ben a `duktor.hu` zónában: **DNS → Records → Add record**, típus **A**, név **buksza**, IPv4 **35.158.45.80**, TTL **Auto**, proxy **DNS only (szürke felhő)**. Ennél a telepítőnél közvetlen DNS-feloldás szükséges az IP-ellenőrzéshez. A fődomain `@` és a `www` rekordját nem kell ehhez átírni. Ha a `buksza` néven már van A/CNAME/AAAA rekord, azt a meglévő beállítással együtt kell rendezni; ne maradjon régi címre mutató, ütköző rekord. IPv6-cím nincs megadva ehhez a szerverhez, ezért új AAAA-rekordot ne hozz létre.
2. Az AWS Security Group engedje a bejövő **TCP 80 és 443** portot. Ezeket a script AWS-fiókhozzáférés nélkül nem tudja a konzolban beállítani. Az aktív Ubuntu UFW-ben a szükséges két portot engedélyezi; SSH-beállításokat nem módosít.

Ubuntu **22.04, 24.04 vagy 26.04**, 64 bites x86/ARM rendszer és sudo jogosultság szükséges. Az építéshez 2 GB RAM ajánlott. Üres szerveren, meglévő Nginx nélkül a kapcsoló elhagyható: ekkor Caddy kezeli a 80/443-as portot és a HTTPS-t. A két telepítési mód közötti későbbi váltás külön migráció; a frissítő nem vált automatikusan.

Előellenőrzés telepítés nélkül:

```bash
sudo bash buksza-telepito.sh --existing-nginx --check
```

## Mit készít el?

- Docker Engine és Compose, ha még nincs Docker; a meglévő, működő Docker/Compose használható marad. Eltérő, ütköző konténercsomagokat nem távolít el automatikusan.
- Épített Laravel/PHP-FPM alkalmazás és React felület, Dockeres belső Nginx; a meglévő Ubuntu Nginx és Certbot biztosítja a nyilvános HTTPS-t. Üres szerveren a külön Caddy-mód is elérhető.
- Tartós SQLite-adatbázis: `/opt/buksza/data/database.sqlite`. Az adatbázisnak és a PHP-FPM-nek nincs nyilvánosan megnyitott portja.
- Véletlen alkalmazáskulcs, titkosított HTTPS-es munkamenet, kikapcsolt hibakiírás és saját Web Push kulcs.
- Folyamatos Docker-ütemező a 19:00-s és Anna hónap végi emlékeztetőkhöz. A telefon regisztrációja és próbaértesítés továbbra is szükséges az appban.
- Napi **03:00 Europe/Budapest** adatbázis- és konfigurációmentés systemd időzítővel. Könyvtár: `/opt/buksza/backups`. A script nem törli automatikusan a régi mentéseket; a mentések a szerveren maradnak. Külső, más szerveren lévő másolat külön beállítás.

A telepítés csak a HTTPS és az alkalmazás egészségellenőrzésének sikere után írja ki, hogy kész. Hibás DNS/portszabály esetén az alkalmazás már futhat, de a script nem állítja, hogy a HTTPS igazolt.

## Első saját belépés

A telepítés végén a script kiír egy **saját indítólinket**. Ezt nyisd meg, és add meg saját nevedet, e-mail-címedet és legalább 12 karakteres jelszavadat. Nincs előre megadott jelszó vagy fiktív pénzügyi adat. Az indítólink az első felhasználó létrehozásához szükséges; ezután a rendes belépés működik.

Az indítólink később a szerveren ismét kiíratható:

```bash
cd /opt/buksza/current
sudo docker compose -p buksza --env-file release.env -f docker/production/compose.yaml exec -T app php artisan buksza:setup-link
```

A gépeden már rögzített kezdőadatok automatikus átköltöztetése nem része ennek a csomagnak. Új szerveren saját belépést és üres kasszát készít; meglévő, ezzel a telepítővel létrehozott szervertelepítés frissítése megőrzi annak adatbázisát és kulcsait. Helyi adatok költöztetésekor az adatbázishoz tartozó eredeti alkalmazáskulcsot is meg kell őrizni.

## Frissítés, állapot és mentések

Az új csomagolt telepítő ugyanazzal a paranccsal futtatható. Építés közben a régi app működik. A váltás előtt rövid karbantartási mód, az adatíró szolgáltatások leállítása és konzisztens mentés következik; nincs adatbázis-törlés vagy kulcscsere. Sikertelen váltáskor a korábbi szolgáltatásokat újraindítja, ha már volt sikeres kiadás. A migrációk nem kerülnek automatikusan visszagörgetésre, és régi adatbázis-mentés nem ír felül új tételeket automatikusan.

Állapot és az értesítési/HTTPS-naplók:

```bash
sudo bash /opt/buksza/current/scripts/server-status.sh
```

Kézi mentés:

```bash
sudo /opt/buksza/bin/backup.sh
```

A mentés `database.sqlite`, `app.env`, `proxy.env` és az aktív kiadás `release.env` fájlját tartalmazza. A konfiguráció titkosítási kulcsot is tartalmaz, ezért a mentési könyvtár csak root számára olvasható. A visszaállítás külön, ellenőrzött művelet; a telepítő nem állítja alaphelyzetbe a kasszát.

iPhone beállítás: [PUSH-IPHONE.md](PUSH-IPHONE.md). A tényleges iPhone-fogadást a szerver telepítése után kell kipróbálni; a script nem tud a telefonon értesítési engedélyt adni.

## Fejlesztői ellenőrzés és csomagkészítés

```powershell
node scripts/package-installer.mjs
docker build --target app -f docker/production/Dockerfile -t buksza-app:installer-qa .
docker build --target web -f docker/production/Dockerfile -t buksza-web:installer-qa .
.\scripts\test-production.ps1
```

Az integrációs próba kizárólag a `buksza-installer-qa` Docker-projektet és külön `qa.sqlite` fájlt használ. HTTPS/Caddy → Nginx → PHP-FPM, védett első belépés, saját jelszó létrehozása, secure munkamenet, valódi tételrögzítés, újraindítás utáni adat- és munkamenet-megőrzés, meglévő Web Push kulcs, PWA és 360 px-es felület sikeresen ellenőrizve. A QA saját localhost-tanúsítványt használ; a valódi Let's Encrypt/tanúsítvány-kiadást és AWS-hálózatot helyben nem lehet igazolni.

ShellCheck sikeres; 54 backendteszt, 797 állítás sikeres. Elkülönített Linux-konténerben teszteltük a bemenetellenőrzést, idegen könyvtár megtartását, meglévő kulcs/konfiguráció megőrzését, fájljogosultságokat, csomagkibontást és az SQLite+konfiguráció mentését. A csomag fehérlistás forrásfájlokból épül, SHA-256 ellenőrző összeggel; titkos helyi fájlok nélkül. A tényleges csomagolt forrásból is sikeresen felépült az alkalmazás képe.

A meglévő Nginx módot elkülönített Linux-konténerben, valódi Nginxszel is ellenőriztük: HTTP ACME-kiszolgálás, saját TLS proxy és átirányítás, két meglévő név szerinti oldal változatlan működése és konfigurációja, foglalt belső port, idegen fájl/domain/szimbolikus link elutasítása, saját konfiguráció visszaállítása és mentett proxybeállítások. A megújító hook másik oldal tanúsítványára nem lép működésbe. A valódi AWS-en a Let's Encrypt-kiadás még telepítéskor ellenőrizendő.

Technikai források: [Docker Engine Ubuntu telepítés](https://docs.docker.com/engine/install/ubuntu/), [Nginx konfigurációteszt és újratöltés](https://nginx.org/en/docs/switches.html), [Certbot webroot és megújító hookok](https://eff-certbot.readthedocs.io/en/latest/using.html), [Caddy automatikus HTTPS](https://caddyserver.com/docs/automatic-https).

Cloudflare-források: [aldomain DNS-rekord létrehozása](https://developers.cloudflare.com/dns/manage-dns-records/how-to/create-subdomain/), [DNS only és proxy működése](https://developers.cloudflare.com/dns/proxy-status/).
