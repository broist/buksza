# 5. fázis – Működési állapot és mentések

A felhasználó 2026.10.15-én ezt választotta a következő fejlesztésnek. A Kintlévőségeim és az általános befektetések részletes kezelése továbbra is későbbi feladat.

## Használat

Telefonon **Továbbiak → Beállítások → Működési állapot és mentések**.

- **Értesítések ütemezője:** az utolsó szerveres életjel és annak állapota. Három percnél régebbi vagy jövőbeli életjel nem jelenik meg működőként.
- **Szervermentés:** utolsó kísérlet, utolsó sikeres mentés, adatbázisméret és az utolsó igazolt napi automatikus futás. A kézi/telepítés közbeni mentés és a napi automatikus mentés külön szerepel. A sikeres mentés 26 óra után elavult; a fél órán túl befejezetlen futás elakadtként látszik. Hiányzó vagy sérült jelentésből nem következtetünk sikeres mentésre.
- **Ütemezett értesítések:** az utolsó 20 küldési bejegyzés, eszköznévvel, típussal, időponttal és eredménnyel. Az összevont napi/havi jelzés két esedékességi bejegyzése külön sor. A szolgáltatói átvétel nem igazolja a telefonos megjelenést. A próbák eredménye továbbra is a Telefonos emlékeztetők eszközlistáján látszik.
- **Állapot frissítése:** csak az állapotot frissíti; a még el nem mentett beállítások és a görgetési helyzet megmaradnak. Az időpontok Budapest szerint jelennek meg, az ellenőrzés saját időpontjával.

A pénzügyi mentés letöltése és visszaállítása továbbra is a külön **Pénzügyek mentése és visszaállítása** kártyán érhető el. A teljes szervermentés az adatbázist és titkos konfigurációját tartalmazza; ezt a felület nem szolgálja ki.

## Telepítés és ellenőrzés

A frissítés a szokásos Git-letöltés és a csomagolt telepítő újrafuttatása. Új adatbázis-migráció nincs; a pénzügyi adatok nem változnak.

```bash
cd ~/buksza-forras &&
git pull --ff-only &&
cd dist &&
sha256sum --check buksza-telepito.sha256 &&
sudo bash buksza-telepito.sh --existing-nginx
```

A telepítés végi mentés után már legyen sikeres mentési időpont. A napi automatikus futás külön akkor igazolt, ha a `buksza-backup.service` is sikerrel lefutott. A timer naponta 03:00-kor fut Budapest szerint; kimaradt futását a systemd induláskor pótolhatja.

Hiba esetén a szerveren:

```bash
sudo bash /opt/buksza/current/scripts/server-status.sh
sudo systemctl status buksza-backup.service --no-pager
sudo journalctl -u buksza-backup.service -n 30 --no-pager
```

Kézi teljes mentés: `sudo /opt/buksza/bin/backup.sh`. Ez nem minősül napi automatikus futásnak. Az állapotfrissítés nem indít mentést vagy értesítésküldést.

## Adatvédelem és működés

- A root által írt `/opt/buksza/status` könyvtár csak időpontokat, eredményt, futástípust és adatbázisméretet tartalmaz. Könyvtára `0755`, jelentései `0644`; a biztonsági másolatok könyvtára továbbra is `0700`, a titkos konfiguráció `0600`.
- A Docker app és ütemező csak olvasható kötetként kapja meg az állapotjelentéseket; a mentési könyvtárat nem kapják meg. Nincs Docker socket, privilegizált konténer vagy a webappból indított szerverparancs.
- A jelentések atomikusan cserélődnek. Hibánál az előző sikeres és napi automatikus mentési jelentés megmarad. Két párhuzamos mentést továbbra is zárolás akadályoz meg.
- A jelentés csak az SQLite mentés, `quick_check` és a szükséges konfiguráció másolása után lesz sikeres. A szerveren lévő másolat nem helyettesíti a másik eszközön/tárhelyen őrzött mentést; automatikus külső másolat és régi mentések törlése ebben a fázisban nem készült.
- Az állapot bejelentkezéshez kötött. Az értesítési előzmény kizárólag a saját eszközök bejegyzéseit adja vissza, tokenek, privát kulcsok, konfiguráció és naplótartalom nélkül.

## Ellenőrzések

- 73 backendteszt, 1005 állítás sikeres. Az új tesztek a friss/régi/jövőbeli életjelet, hiányzó/sérült/hibás méretű jelentéseket, elavult és elakadt mentést, kézi/napi különbséget, korábbi siker megőrzését, hozzáférést és az előzmények korlátozását ellenőrzik. PHPUnit kizárólag memóriabeli adatbázist használ.
- A 360 px-es működési kártya és WCAG AA ellenőrzése sikeres. A frissítés megőrzi az el nem mentett űrlapértéket.
- Friss, elkülönített QA-adatbázissal mind a 25 Playwright-teszt sikeres. Először a 360 px-es felület készült el; ezután a 390/430/768/1280 px-es nézetek is túlcsordulás nélkül működnek. A saját adatbázis nem vesz részt ezekben a próbákban.
- Elkülönített Linuxban sikeres a valós SQLite+konfiguráció mentése, a napi futás megkülönböztetése, szándékos konfigurációhiánynál a hibajelentés és előző siker megőrzése, valamint a zárolás. ShellCheck sikeres.
- A csomagolt telepítőből Linuxban felépített PHP-FPM/Nginx képpel a HTTPS-es produkciós QA sikeres. A szándékosan fiktív, elkülönített állapotjelentés megjelenik; a kézi mentés nem lesz napi automatikus siker, és az állapotkötet nem írható a konténerből. Ez nem a felhasználó éles mentésének próbája.
- A korábbi saját pénzügyi és felhasználói adatok megőrzési ellenőrzése sikeres; a saját adatbázist nem állítottuk alaphelyzetbe.

Az éles AWS-szerver ütemezőjének és napi mentésének tényleges eredményét frissítés után a saját felületén kell ellenőrizni. A fejlesztői teszt nem igazolja a távoli szerver aktuális állapotát.

A fázis végén bemutató és megállás következik; a következő modul külön jóváhagyással indul.
