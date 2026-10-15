# Buksza

Magyar, mobilra tervezett, egyfelhasználós családi pénzügyi webapp. Laravel 13 + Inertia 3 + React/TypeScript + Tailwind 4 + SQLite. Az első három fázis funkciói elkészültek; a felhasználó 2026.10.15-én jelezte a sikeres AWS/Docker telepítést. A https://buksza.duktor.hu/up és a belépési oldal HTTPS-en 200 választ ad. Az iPhone-os értesítés tényleges fogadási próbája még hátravan.

## Indítás Windows alatt

**Ubuntu/AWS, buksza.duktor.hu:** a forrás és az egyfájlos telepítő a [broist/buksza](https://github.com/broist/buksza) tárolóban található. Klónozás után a `dist` könyvtárból `sudo bash buksza-telepito.sh --existing-nginx`; a későbbi frissítés `git pull --ff-only`, majd ugyanennek a telepítőnek az újrafuttatása. Részletes lépések és feltételek: [TELEPITES-UBUNTU.md](docs/TELEPITES-UBUNTU.md). A csomagolt forrásból Dockerben épít, saját Nginx-beállítást, HTTPS-t és ütemezőt készít, saját indítólinket és napi mentést ad. Az AWS-telepítés sikeres; az iPhone-fogadás még készüléken ellenőrizendő.

Futó Docker Desktop és Node.js 24 szükséges. A projekt gyökerében:

```powershell
.\scripts\start.ps1
```

Megnyitás: [helyi alkalmazás](http://localhost:18080). Az első belépésnél saját név, e-mail és legalább 12 karakteres jelszó adható meg. Csak egy felhasználó hozható létre. Nincs előre megadott éles jelszó, pénzügyi mintaadat vagy nyitóösszeg. A kategóriák a SPEC-ből származnak; az adakozás és egyetem alapból kikapcsolt.

A Beállításokban külön add meg a terv szerinti éves nyitó összeget és a főszámla tényleges nyitóegyenlegét/dátumát. A Tervezés oldalon az alapterv a következő hónapok alapja; az aktuális hónap kerete külön menthető. A korábbi és kézzel felülírt hónapokat az alapterv módosítása nem írja át.

Újraindítás: `docker compose up -d app web scheduler`. Leállítás: `docker compose stop app web scheduler`. Az adatbázis: `webapp/database/database.sqlite`; a környezeti konfiguráció `webapp/.env`, mindkettő figyelmen kívül hagyott helyi fájl. A PHP-függőségek külön Docker-köteten vannak a Windows/OneDrive fájlelérés gyorsítására. Nginx szolgálja ki és tömöríti a buildelt felületet, a kéréseket a PHP-szolgáltatásnak továbbítja.

## Megnyitás telefonon a helyi hálózaton

A gép és a telefon ugyanahhoz a Wi-Fi/hálózathoz csatlakozzon. Az alkalmazás előzetes indítása után a gép helyi IPv4-címével:

```powershell
.\scripts\start-lan.ps1 -Address 192.168.1.239
```

A telefon böngészőjében `http://192.168.1.239:18080` nyitható meg, saját bejelentkezéssel. A gépnek és a Dockernek futnia kell. Ez helyi HTTP-elérés; az internetes HTTPS-elérés az AWS-telepítéssel készül el. IP-címváltozáskor az új címmel kell futtatni a parancsot. A Windows tűzfalnak engednie kell a helyi hálózati forgalmat a 18080-as TCP-porton. Visszaállítás csak gépen elérhető módra: `docker compose -f compose.yaml up -d app web`.

## Ellenőrzések

```powershell
docker compose exec -T app php artisan test --no-ansi
cd webapp
npm run build
```

A PHPUnit minden indítási módban memóriabeli adatbázist használ, és induláskor ellenőrzi ezt. A böngészős QA külön `qa.sqlite` adatbázissal, szándékosan fiktív adatokkal fut. Az alábbi alaphelyzetbe állítás **csak az elkülönített QA-adatbázist törli**:

```powershell
# A projekt gyökerében
docker compose -f compose.qa.yaml run --rm qa php artisan migrate:fresh --seed --seeder=QaSeeder --force --no-interaction
docker compose -f compose.yaml -f compose.qa.yaml up -d qa qa-web
cd webapp
npx playwright install chromium
npm run test:e2e
node scripts/lighthouse.mjs
```

A QA seeder ellenőrzi a tesztkörnyezetet és a `qa.sqlite` fájlnevet; az alkalmazás saját adatbázisába nem enged tesztadatokat írni. Képernyőképek: `docs/screenshots`; Lighthouse: `docs/checks`. QA leállítása: `docker compose -f compose.yaml -f compose.qa.yaml stop qa qa-web`.

## Elkészült fázisok

Kategóriák/alkategóriák, sorrend, színek, aktiválás, keretátvitel; kézi bevételek és kiadások, félretétel és Örs befizetése; havi napi lista és asztali kategóriarács; napi megjegyzés; alapterv/havi terv/másolás; valós és terv szerinti maradék; közös, pontos havi/éves számítások; világos/sötét téma; jelszavas belépés.

**2. fázis:** számlák és nyitóegyenlegek, saját számlák közötti egyszeri átvezetés és opcionális díj, alszámlás hitelfedezet, SZÉP/egészségpénztár, dátumozott elszámolási módváltás, Örs és családi megtakarítási célhoz kapcsolt pénzmozgások; Anna havi pillanatképei; bővíthető hitelnyilvántartás, kamat-/törlesztőelőzmény és valódi fizetések; számla-/személyszűrés a családi keretek megőrzésével. Telefonon Továbbiak → Számlák/Hiteleim. Részletes fáziszárás: [PHASE-2.md](docs/PHASE-2.md).

**3. fázis helyi funkciói:** esti rögzítés, Mentés és következő, saját ismétlődő sablonok és fizetés-jóváhagyás, hitelszerződést követő sablonok, három teljes hónapból pontos tervjavaslat, PWA és Web Push szerveroldali ütemezés/eszközbeállítások. Telefonon Továbbiak → Esti rögzítés/Rendszeres tételek; Beállítások → Telefonos emlékeztetők. Részletek: [PHASE-3.md](docs/PHASE-3.md), iPhone/Ubuntu/buksza.duktor.hu előkészítés: [PUSH-IPHONE.md](docs/PUSH-IPHONE.md). A részletes zsebpénz/babakötvény/éves modulok, export és visszaállítás a 4. fázis feladatai; az AWS-telepítés és a napi mentés infrastruktúrája már elkészült. Az Éves pillantás jelenleg a már tesztelt számítások alapösszegeit mutatja. A hitel törlesztése nem csökkenti automatikusan a kézi tőketartozás-adatot, és a kamatból nem készül feltételezett amortizáció.

Az alap Docker-konfiguráció helyi fejlesztői kiszolgálás, loopback porttal. A külön éles PHP-FPM/Nginx környezet már települt AWS-re, a meglévő Ubuntu Nginx mögé, HTTPS-sel, ütemezővel és napi mentéssel. A szerveres ütemező életjelét, az első napi mentést és a telefonos fogadást még ellenőrizni kell. A passkey-belépés, visszaállítás, export és a 4. fázis részletes riportjai még hátravannak.
