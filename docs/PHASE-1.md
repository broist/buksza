# 1. fázis – elkészült alap

2026. október 14. A specifikáció és a beszélgetésben elfogadott módosítások alapján.

## Használható funkciók

- Saját első belépés és egyfelhasználós jelszavas védelem, hosszú munkamenettel.
- 23 kezdőkategória, alkategóriák, színek, sorrend, aktiválás és kategóriánkénti keretátvitel. Adakozás és egyetem alapból kikapcsolva; a régi tételek archivált kategóriában is megmaradnak.
- Kézi kiadás és bevétel, családi félretétel/visszavétel, Örs külön befizetése. Egészforintos bevitel, napi dátum, megjegyzés, magán/üzleti jelölés; szerkesztés, megerősített törlés és visszavonás. Ismételt kérés ugyanazzal az azonosítóval nem hoz létre dupla tételt.
- Telefonon napi lista és napi megjegyzés. Asztalon alapból a kategóriarács jelenik meg; üres cella előre kitöltött dátummal/kategóriával rögzíthető, egytételes cella közvetlenül szerkeszthető, többtételes cella a napi listát nyitja. Telefonon külön Táblázat kapcsoló, rögzített első oszloppal.
- Alapterv, havi felülírás és előző hónap másolása. Alapterv változtatása nem módosít korábbi vagy külön felülírt hónapokat.
- Áttekintés: valós főszámla-egyenleg, terv szerinti maradék, bevétel/kiadás/CF, napi átlag és legdrágább nap, keretek és terv–tény, megtakarítási cél/tényleges félretétel, Örs külön összege, napi oszlopdiagram és kategóriaarányok.
- Magyar felület, szóközös ezrestagolás, magyar hónap-/napnevek, világos/sötét téma, 360 px-re kialakított felület; 1024 px felett oldalsáv és teljes rács.

## Számítások

A 4. fejezet közös havi és éves számításai elkészültek. Pontos törtszámítás őrzi az arányokat és átlagokat, nem egészforintos kerekítés. A nem véges tizedesek megjelenítése `…` jelöléssel rövidül; az összeg pontos törtértéke a címkében megtekinthető, a további számítások ezt használják. Nulla tervnél a százalékos eltérés helyett „Keret nélkül” jelenik meg.

A családi megtakarítási cél nem tényleges pénzmozgás. A félretételt a terv szerinti CF nem vonja le még egyszer. Örs befizetése külön levonás a CF-ben, és nem teljesíti a családi célt. A valós egyenleg a nyitóegyenleg és a tényleges mozgások összege; nyitódátum előtti hónaphoz nem találunk ki egyenleget.

## Ellenőrzések

- **22 PHPUnit teszt, 194 állítás:** hónaphosszak/szökőév, eltelt mai nap, napi/kategória/havi összegek, bevételi források, megtakarítási arányok, Örs külön kezelése, nyitó–záró lánc, pozitív/negatív és kikapcsolt átvitel, terv–tény és nulla terv, éves átlagok/CF-egyezés, valós egyenleg, pontos törtértékek; hitelesítés, adatellenőrzés, tartós CRUD, duplikációvédelem, visszavonás, kategóriák és tervek.
- **7 Playwright teszt:** 360×800, 390×844, 430×932, 768×1024, 1280×900 képernyők, minden első fázisos oldal vízszintes túlcsordulás nélkül; CRUD/visszavonás és újratöltés; bevétel/két megtakarítás; alacsony, billentyűzetet modellező nézetben elérhető mentés; kategória/terv/hónapváltás; sötét téma és Escape; asztali cellák rögzítése/szerkesztése.
- **Axe WCAG AA:** ellenőrzött világos oldalak, sötét Áttekintés és gyorsrögzítő panel hibajelzés nélkül.
- **Lighthouse mobil: 96 teljesítmény, 100 hozzáférhetőség**, buildelt, tömörítetten kiszolgált QA-változaton. FCP 1,6 s, LCP 1,7 s, TBT 180 ms, CLS 0. Jelentés: `docs/checks/lighthouse-mobile.html`.
- Képernyőképek: `docs/screenshots`, köztük telefonos, asztali és sötét nézetek. A képeken kizárólag elkülönített, fiktív QA-adatok szerepelnek; a saját alkalmazás pénzügyi adatai üresek.

A kezdeti konténeres tesztindításnál környezeti felülírást találtunk és javítottunk. A PHPUnit most minden indítási módban memóriabeli adatbázist kényszerít, és ezt a tesztalap induláskor ellenőrzi. A böngészős QA `qa.sqlite`-ot használ. A saját `database.sqlite` induló állapota külön ellenőrizve: 0 felhasználó, 0 tranzakció, 23 kategória. A fejlesztői kiszolgálás nem engedi el a Dockerben beállított adatbázis-környezetet.

## A fázis határa

Az 1. fázisban a CIB főszámla használható; a több számla, Anna havi pillanatképei és bővíthető hitelnyilvántartás a 2. fázisba tartoznak. A 19:00-s és hónap végi telefonos értesítés, ismétlődő sablonok és telepíthető PWA a 3. fázis feladatai. A részletes befektetési/zsebpénz/éves modulok, export, mentés/visszaállítás, passkey és AWS-kiadás később készülnek. Jelenleg nincs aktív emlékeztető és nincs AWS-telepítés.

Az Éves pillantás csak a közös számítások négy alapösszegét mutatja; a teljes éves riport a 4. fázisban következik. A virtuális képernyő- és billentyűzetellenőrzés nem helyettesít fizikai telefonos, biometrikus vagy push-próbát; ezek az érintett későbbi fázisokhoz tartoznak.

**Megállás az 1. fázis végén.** A 2. fázis csak a felhasználó következő jóváhagyásával indul.
