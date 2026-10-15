# 6. fázis – Figyelmeztetések és üzleti/magán riport

A felhasználó 2026.10.15-én jóváhagyta a soron következő fejlesztéseket. Külön kérte a figyelmeztetéseket a felületen és az esti értesítésben, majd az azonnali jelzést is a keret elérésekor.

## Havi figyelmeztetések

Az **Áttekintés és Havi nézet** a közös családi kereteket figyeli. A felhasználható keret a havi terv és az áthozott maradék összege; az adatokat a közös `Calculator` számolja. A számla- vagy személyszűrés nem hoz létre külön keretet.

- 80%-tól közeledő keretelérés, pontosan 100%-nál elfogyott keret, fölötte túllépés látszik.
- Nulla/nem pozitív felhasználható keret melletti költés külön jelzést kap. Az üres, költés nélküli nulla keret nem figyelmeztetés. Negatív áthozott maradék is látszik.
- A jelzés a rögzített adatokból újraszámolódik: módosítás, törlés és visszavonás után a megfelelő állapot jelenik meg. A 80%-os határ pontos összehasonlítás; nincs pénzösszeg-kerekítés.
- Az első három kategória és három összegváltozás látszik rögtön, a teljes lista külön gombbal nyitható meg.

## Telefonos jelzések

**Beállítások → Telefonos emlékeztetők:** külön kapcsoló az esti összefoglalóhoz és az azonnali keretjelzéshez. A felhasználói kérés alapján mindkettő alapból bekapcsolt. A meglévő napi/hónap végi időpont és eszközengedély megmarad; új engedélyt nem kérünk automatikusan.

- **Esti összefoglaló:** a napi emlékeztető meglévő üzenetéhez a keretjelzések és rendszeres összegváltozások darabszáma kerül. Nincsenek összegek/tételnevek a napi összefoglalóban. Azonos időpontban Anna hónap végi jelzése továbbra is összevonódik vele. Ha a napi jelzés ki van kapcsolva, ez az összefoglaló sem érkezik. A részletek az esti rögzítésből elérhetők.
- **Azonnali keretjelzés:** az appba rögzített, szerkesztett, sablonból jóváhagyott vagy visszaállított költésnél, amikor a jelenlegi hónap pozitív felhasználható kategóriakerete először eléri/túllépi a 100%-ot. A kategória nevét tartalmazza, pénzösszeget nem. A havi nézetet nyitja meg.
- Kategóriánként, készülékenként, naptári hónaponként legfeljebb egy azonnali küldési kísérlet történik. Ismételt mentés, további túlköltés vagy törlés-visszavonás nem küldi újra. Sikertelen küldés sem indít automatikus ismétlési sort; az eredmény a működési előzményben látszik, az esti összefoglaló továbbra is elérhető.
- Korábbi hónap rögzítése, jövőbeli tétel vagy a puszta tervmódosítás nem indít azonnali jelzést. A 80%-os állapot felületi/esti figyelmeztetés. Az azonnali kapcsoló a napi emlékeztetőtől független.
- A banki díj és a zsebpénz-módú feltöltés a meglévő családi elszámolást követi. Átvezetés önmagában nem fogyaszt keretet.
- Az értesítés csak már engedélyezett eszközre, HTTPS-es alkalmazásból küldhető. Küldési hiba nem vonja vissza és nem akadályozza a pénzügyi mentést. Az opcionális esti számítás hibájánál az alap napi emlékeztető továbbra is elküldhető.

**Beállítások → Működési állapot és mentések:** az utolsó 20 küldés már az azonnali kategóriajelzéseket is tartalmazza, saját kategórianévvel. A szolgáltatói átvétel továbbra sem bizonyítja a telefonos megjelenést.

## Rendszeres összegek változása

Az **Áttekintés és Rendszeres tételek** jelzi a ténylegesen jóváhagyott kiadás eltérését a sablon összegétől. Ha a fizetés egyezik a sablonnal, a korábbi, nem törölt tényleges fizetéssel is összehasonlítja. A referencia, annak dátuma, tényleges összeg és növekedés/csökkenés külön látszik.

Nem találunk ki szolgáltatói áremelést, és nem írjuk át automatikusan a sablont. Belső `kp`-költés, átvezetés és még ki nem fizetett sablon nem számít tényleges rendszeres kiadásváltozásnak. A jelzés az adott hónap összehasonlítása, nem olvasatlanüzenet-nyilvántartás.

## Üzleti/magán riport

**Továbbiak → Üzleti / magán riport**, asztalon az oldalsávból is. Kezdő- és zárónappal, évváltáson át is használható. Alapértelmezése a kiválasztott hónap és az üzleti tétellista.

- Összes/üzleti/magán időszaki bevétel, kiadás és azok különbsége, havi és kategóriabontással.
- Üzleti + magán = teljes családi időszaki bevétel/kiadás. A jelölés nélküli tétel magán. A díj az eredeti pénzmozgás üzleti jelölését követi, egyszer számít; a már elszámolt feltöltés és belső költés nem duplázódik.
- A bevétel mínusz kiadás megtakarítás előtti különbség, nem új CF, adóeredmény vagy személyes keret. A családi megtakarítási cél és Örs külön befizetésének meglévő CF-kezelése nem változik.
- Kereshető tétellista, 20 soros lapozással; szerkesztés az eredeti pénzmozgást nyitja meg. A banki díj elszámolási sora is az eredeti átvezetésre vezet.
- A dátumhatárok befoglalók. Érvénytelen dátum, fordított időszak vagy ismeretlen bontás nem készít riportot.
- Excel-export négy munkalappal: Összegzés, Hónapok, Kategóriák, Tételek. A teljes időszak mindkét csoportjának összesítése egyeztethető; a Tételek munkalap és CSV a kiválasztott üzleti/magán/összes bontást követi. A képernyős keresés/lapozás az exportot nem korlátozza. A szöveges adatokból nem hajtunk végre képletet.

## Frissítés és adatmegőrzés

A szokásos Git-frissítés és csomagolt telepítő újrafuttatása szükséges. Két új logikai emlékeztető-beállítás kerül az adatbázisba; pénzügyi tételt nem írunk át. Az azonnali jelzés a meglévő eszközönkénti küldésnapló egyedi kulcsát használja. A korábbi pénzügyi `.buksza` mentések adatmodellje változatlan.

```bash
cd ~/buksza-forras &&
git pull --ff-only &&
cd dist &&
sha256sum --check buksza-telepito.sha256 &&
sudo bash buksza-telepito.sh --existing-nginx
```

Frissítés után nyisd meg újra a kezdőképernyős Bukszát az értesítési worker frissítéséhez. A telefonos érkezést a saját készüléken külön kell ellenőrizni; a fejlesztői transportteszt ezt nem igazolja.

## Ellenőrzések

- 88 backendteszt / 1205 állítás sikeres, kizárólag memóriabeli adatbázison. Pontos határértékek, átvitel, pénzügyi összeg-egyezés, dupla díj/kp kizárása, dátumok és hozzáférés, alapértelmezett terv írás nélküli használata, jövőbeli tételek kizárása, push-hiba melletti adatmegőrzés, azonnali duplikációk kizárása és kapcsolók.
- A 360 px-es új felületek és WCAG AA ellenőrzése sikeres; ezt követte az asztali bővítés. Böngészőből ellenőrzött üzleti rögzítés/jelölésváltás, keretjelzés, riport-export és tényleges rendszeres összegváltozás.
- Friss, elkülönített `qa.sqlite` adatbázissal mind a 28 Playwright-teszt sikeres; a meglévő pénzügyi és bejelentkezési folyamatok, valamint a 360/390/430/768/1280 px-es nézetek ellenőrzése is megtörtént. A korábbi saját adatok megőrzési ellenőrzése sikeres; saját adatbázis nem lett alaphelyzetbe állítva.
- Négy service worker teszt sikeres: cím továbbadása, hibás adatok kezelése, azonnali jelzés havi céloldala és a frissítés aktiválása.
- A csomagolt Linux-forrásból a PHP-FPM/Nginx kép sikeresen felépült és `www-data` felhasználóval indul. Az elkülönített HTTPS-es produkciós QA az új migrációkat, üzleti riportot és annak Excel-exportját, valamint a bekapcsolt alapértelmezett összefoglalót is ellenőrizte a korábbi belépési/adatmegőrzési próbák mellett. Ez nem éles iPhone-os kézbesítési próba.

A fázis végén bemutató és megállás következik. A külső biztonsági mentés, Kintlévőségeim és általános befektetések további fázisok feladatai.
