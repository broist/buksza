# Személyes pénzügyi webalkalmazás – specifikáció

Cél: az eddigi Excel (Havi-CF-2026) kiváltása egy **átlátható, gyorsan kezelhető, egyfelhasználós webappal**. Az Excel és a korábbi kivonatos elemzés a kategóriák és a számítások tervezési háttere. **A kezdőadatokat és a tényleges pénzmozgásokat a felhasználó kézzel rögzíti**, jellemzően minden este 19:00-kor. Az app terv–tény összehasonlítást ad; bankkivonat-import nem része az első négy fejlesztési fázisnak.

Az app a **teljes családi kasszát** kezeli egy helyen: több számla (két CIB, a főszámlához rendelt alszámla, Raiffeisen, Revolut), továbbá Isti SZÉP-kártyája és egészségpénztári egyenlege. Isti és Anna mellett **Örs gyermekmegtakarítását, a MÁK-nál vezetett babakötvény-befektetéseket** is nyilvántartja, havi fix befizetésekkel. Alapból egy összevont, családi nézetet ad, de számlánként és személyenként is szűrhető. A bankszámlák, a kötött felhasználású egyenlegek és a gyermekmegtakarítás külön is áttekinthetők.

Az alkalmazás neve **Buksza**; ez szerepel a belépőoldalon, a mobilos és asztali fejlécben, valamint a böngésző lapcímében.

Nyelv: **magyar felület**. Pénznem: **HUF**, a kézzel rögzített tételek egész forintban, ezres tagolás szóközzel (`1 234 567 Ft`). A számítások nem kerekíthetők automatikusan egész forintra; az átlagok és arányok számítási pontosságát meg kell őrizni. Napnevek és hónapnevek magyarul, a hét hétfővel kezdődik.

---

## 1. Alapelvek

- **Gyors rögzítés**: egy kiadás felvitele max. 3 kezelési lépés / 10 másodperc, telefonon is; a számjegyek begépelése nem számít külön kezelési lépéseknek.
- **Mobil-first**: az elsődleges használat telefonon, egy kézzel történik (részletek: 1.1). Az asztali böngészős nézet másodlagos, gyors áttekintésre. Világos és sötét téma.
- **Minden összeg automatikusan számolódik**, semmit nem kell kézzel összeadni.
- **Piros/zöld jelzés**: keret túllépése piros, a keretben maradás zöld.
- Kevés, jól elkülönített oldal (lásd 3. fejezet).
- Egy felhasználó, egy közös, AWS-en Dockerben futó webapp és adatbázis, könnyű mentés/visszaállítás.
- **Kézi esti rögzítés**, telefonos emlékeztetővel minden nap 19:00-kor, Europe/Budapest időzónában.

---

### 1.1 Mobil-first (elsődleges követelmény)

Az app **elsősorban telefonon, álló nézetben, egy kézzel** használatos. A fejlesztést a telefonos felülettel kell kezdeni, minden képernyőt **először telefonra** kell megtervezni és késznek tekinteni, és csak utána bővíteni asztali elrendezéssel. A böngészős (asztali) nézet másodlagos: arra való, hogy gépről is rá lehessen nézni az állásra, és kényelmesebb legyen a nagyobb táblázatok szerkesztése. Ugyanaz az app és ugyanazok a funkciók, csak az elrendezés igazodik a képernyőhöz (reszponzív webapp, nem külön mobilapp).

**Telefonos tervezési szabályok**
- Kiinduló méret: **360–430 px széles álló telefon**. Minden funkció elérhető rajta, vízszintes görgetés nélkül (kivéve a szándékosan görgethető táblázatot).
- **Alsó navigációs sáv** 4–5 elemmel (Áttekintés, Hónap, középen a nagy **+** gyors rögzítés, Éves, Továbbiak). A fő funkciókhoz nincs hamburger-menü. A leggyakoribb műveletek a hüvelykujjal elérhető alsó zónában vannak.
- **Érintési célpontok** legalább 44×44 px, kellő távolsággal egymástól.
- **Gyors kiadás ablak alulról felcsúszó panelként** (bottom sheet): összeg, kategória, mentés. A mentés gomb a billentyűzet fölött is látszik.
- Összegmező: `inputmode="numeric"`, számbillentyűzettel. Beviteli mezők betűmérete legalább 16 px (hogy a telefon ne nagyítson rá), az összegek `tabular-nums` számjegyekkel, jó kontraszttal.
- Biztonságos területek kezelése (kamera-kivágás, alsó sáv: `safe-area-inset`).
- Listaelemeken csúsztatás a szerkesztéshez/törléshez (visszavonási lehetőséggel), húzással frissítés.
- Gyors betöltés lassú mobilneten is: kis csomagméret, vázképernyők (skeleton) betöltés közben.

**Telefonos nézetek** (a 3. fejezet oldalainak mobil megfelelője)
- **Áttekintés**: kártyák egymás alatt (havi állás, CF, kategóriasávok), diagramok egy oszlopban, nézetváltó (család / számla / személy) fent.
- **Havi nézet**: a 20+ oszlopos napi rács telefonon nem használható, ezért itt **napi lista** van: a hónap napjai függőlegesen (nap + napnév + napi összeg), a napra érintve megnyílnak a napi tételek. Fölül kategóriánkénti összesítő, az „Ennyit költhetek” kategóriánként kártyás listában. A teljes rács telefonon csak opcionális **„Táblázat” kapcsolóval** érhető el, vízszintesen görgethetően, rögzített első oszloppal.
- **Tervezés, Éves összesítő**: kategóriák kártyákon, nem széles táblázatban; a részletek lenyithatók.
- **Befektetéseim, Kintlévőségeim**: kártyalista; a két kintlévőség-lista külön fülön.
- **Szűrők** (hónap, számla, személy) egysoros, könnyen érinthető választókként a képernyő tetején.

**Asztali / böngészős nézet** (másodlagos)
- 1024 px fölött oldalsáv-navigáció, többoszlopos Áttekintés, a Havi nézet a teljes napi rácsot mutatja, szerkeszthetően.
- Cél a gyors rálátás („hogyan állunk pontosan”): nagyobb képernyőn több adat látszik egyszerre.
- Telefon és böngésző **ugyanazt az adatot** látja, nincs külön szinkronizálandó példány.

**Telepíthetőség és használhatóság**
- **PWA**: a kezdőképernyőre tehető, teljes képernyőn indul, saját ikonnal és névvel, gyorsan nyílik.
- **Online rögzítés**: offline beviteli sor és későbbi adatszinkronizálás nem szükséges. A PWA telepíthetősége ettől független követelmény.
- **Napi telefonos push-emlékeztető 19:00-kor**, Europe/Budapest időzónában, téli/nyári időszámítást követve. Az értesítésből az esti rögzítés nyílik meg; bekapcsolása és próbaértesítés elérhető a Beállításokban. A szerver ütemezi a küldést, nem a nyitott böngészőlap. A fogadás az eszköz/böngésző támogatásától és a megadott értesítési engedélytől függ. A funkció elkészült; a felhasználó 2026.10.15-én igazolta az iPhone-os értesítés megérkezését.
- **Havi helyzetjelentés-emlékeztető Anna számlájához**, minden hónap utolsó naptári napján. Tervezési alapértelmezésként az esti 19:00-s jelzéshez kapcsolódik, Europe/Budapest szerint; az időpont beállítható. Hónap végén a napi értesítés Anna egyenlegének megadására is emlékeztet, két azonos idejű üzenet helyett. A 28/29/30/31 napos hónapokat automatikusan kezeli.
- **Hosszú munkamenet** és **passkey/biometrikus belépés**, hogy ne kelljen mindig jelszót írni telefonon (jelszó tartalékként).

---

## 2. Adatmodell

### 2.1 Tétel típusa
Minden tétel egy típusba tartozik. Ez a legfontosabb különbség az Excelhez képest, mert a kivonatban sok pénzmozgás nem valódi költés.

| Típus | Jelentés | Számít költésnek? |
|---|---|---|
| `kiadás` | valódi költés | igen |
| `bevétel` | forrás: `Munka` / `Egyéb` | – |
| `átvezetés` | a családi számlák közti mozgás (pl. főszámla → Raiffeisen, Anna számlája → főszámla, automatikus fedezetátvezetés), lásd 2.2 | **nem** |
| `megtakarítás` | megtakarítási számlára, kerekítéses megtakarítás | nem, a megtakarításhoz tartozik |
| `kölcsön adás` | másnak/cégnek adott kölcsön (pl. tagi kölcsön) | nem, **automatikusan bekerül a Kintlévőségeim „Ki tartozik nekem?” listájába** |
| `kölcsön visszafizetés` | amit visszakapok / amit törlesztek másnak | nem, csökkenti a kintlévőséget |

### 2.2 Számlák és személyek
A családi kassza több számlából áll. Egy számla/nyilvántartott egyenleg adatai: név, bank vagy szolgáltató, tulajdonos (Isti / Anna / közös / Örs, bővíthető), **típus** (bankszámla, alszámla, SZÉP-kártya, egészségpénztár, befektetési nyilvántartás), **szerep**, **feltöltés kezelése**, aktív, nyitó egyenleg (opcionális). Alszámlánál a hozzá tartozó főszámla is megadható. Befektetésnél a kedvezményezett külön is rögzíthető; Örs ettől nem válik az app önálló felhasználójává vagy zsebpénz-kedvezményezettjévé.

Alapértelmezett számlák (első indításkor felvéve, szerkeszthetők):

| Számla | Tulajdonos | Szerep | Feltöltés kezelése |
|---|---|---|---|
| CIB főszámla | Isti | a család főszámlája: bevételek, fix terhek, hitel, biztosítások, rezsi; az innen induló utalás díjmentes | – |
| CIB – Anna | Anna | a főszámlára küldött hozzájárulás követett; saját forgalma helyett havi utolsó napi egyenleg-helyzetjelentés | hozzájárulás a főszámlán bevétel; Anna egyenlege külön pillanatkép |
| Raiffeisen – családi | közös | a havi étel és a bevásárlások számlája; a főszámláról kap havi rendszeres feltöltést | részletes, kézi vásárlásrögzítéssel; feltöltése átvezetés |
| Revolut – Isti | Isti | Isti szabad zsebpénze, bármire költhető; a főszámláról havonta, több részletben töltődik | zsebpénz |
| CIB alszámla – Isti | Isti | az aktuális havi hitel és egy változó további összeg ideiglenes elkülönítése; a hitel összege hónap végén visszakerül a főszámlára | belső átvezetés, lásd 2.2.1 |
| SZÉP-kártya – Isti | Isti | külön nyilvántartott, kötött felhasználású egyenleg; munkáltatói jóváírások | külső jóváírás a SZÉP-egyenlegre, külön juttatásként követve |
| Egészségpénztár – Isti | Isti | saját befizetésekből képzett külön pénztári egyenleg; eddig nem történt vásárlás | saját befizetés átvezetés, tényleges vásárlás majd kiadás |
| MÁK babakötvény – Örs | Örs | gyermekmegtakarítás, havi fix összegű befizetések és babakötvény-befektetések | megtakarítási/befektetési nyilvántartás, lásd 2.2.3 |

**Feltöltés kezelése** (számlánként beállítható), hogy a pénz ne számolódjon duplán:
- **részletes**: a számla költéseit a felhasználó kézzel rögzíti. A rá érkező saját pénz `átvezetés`, a tényleges vásárlások kiadások.
- **összevont**: csak a feltöltés követett, ezért az **kiadásnak** számít az előre megadott kategóriában. Ugyanezen időszak tényleges vásárlásait nem szabad még egyszer családi kiadásként hozzáadni. A részletes módra váltás kézi elszámolási döntés, nem kivonatimporthoz kötött.
- **zsebpénz**: a feltöltés az adott személy havi zsebpénze és egyszer családi kiadásnak számít. Revolutnál az elköltött összegek általános **„kp”** megjelöléssel, költési cél és kötelező részletes kategorizálás nélkül követhetők, az egyenleg csökkentésére. Ezek nem újabb családi kiadások. A „kp” itt a felhasználó által kért megjelölés, nem automatikusan azonosított ATM-felvétel.

**Átvezetés kézi rögzítése**: a felhasználó egyszer adja meg a forrás- és célszámlát, az összeget és a dátumot; az app ebből kezeli mindkét számla egyenlegváltozását. Nem kell kétszer beírni ugyanazt a mozgást. Ha külön rögzített oldalak egyeztetése szükséges, az eredeti ±2 napos párosítási szabály később használható. A számla elszámolási módja dönti el a családi összesítőben való megjelenést:
- összevont / zsebpénz számlára menő pénz → kiadás
- Anna főszámlára küldött hozzájárulása **bevétel** (Egyéb / Anna hozzájárulása). Anna fizetését és saját költéseit nem rögzítjük; a hozzájárulás a családi pénzforgalmi nyilvántartás belépési pontja. Anna havi egyenleg-helyzetjelentése ettől külön adat, nem második bevétel és nem átvezetési ellenoldal.

Az átutalási díj a díjat fizető számlán kiadás (kategória: adó / hatósági / banki díj).

**Nézetek**: összes számla (család), számla szerint, személy szerint (Isti, Anna, közös, Örs). A kötött felhasználású egyenlegek és Örs befektetései külön csoportban látszanak; nem olvadnak bele jelöletlenül a szabadon elkölthető bankszámla-egyenlegbe.

#### 2.2.1 Hitelhez elkülönített pénz az alszámlán
- A főszámláról az alszámlára utalt összeg az **aktuális havi hitel + egy változó további összeg**. A feltöltés összege ezért nem kezelhető automatikusan a hiteltörlesztés összegének.
- A főszámla → alszámla mozgás `átvezetés`, nem kiadás, és kézzel egy műveletként rögzíthető.
- Hónap végén a hitel fedezete az alszámláról visszakerül a főszámlára: ez szintén `átvezetés`, nem bevétel.
- A bank **tényleges hitellevonása** a főszámlán egyszer, `kiadás` típussal és `hitel` kategóriában jelenik meg.
- Az alszámla egyenlege és a kétirányú forgalom külön látszik. Az alszámlán maradó további összeg rendeltetését a felhasználó később pontosítja.
- Egy saját számlák közötti mozgásnál a számlakapcsolat elsőbbséget élvez a közlemény `hitel` szavára épülő kiadásbesorolással szemben.

#### 2.2.2 SZÉP-kártya és egészségpénztár
- Mindkettő külön egyenleggel, nyitó összeggel és tétellistával kezelhető, kézi rögzítéssel. Import nem szükséges a használatukhoz.
- A kártyával fizetett vásárlások a valódi költés szerinti kategóriát kapják (például étkezés, gyógyszer vagy orvosi vizsgálat), a fizetés forrása a megfelelő kártya/pénztár.
- A feltöltés, a vásárlás és az esetleges térítés egyeztethető legyen, hogy ugyanaz a költés ne szerepeljen kétszer.
- **SZÉP-kártya:** a munkáltató fizet rá. A jóváírás külön azonosítható munkáltatói juttatás a SZÉP-egyenlegen; nem főszámlás jóváírás és nem saját számlák közötti átvezetés. A tényleges vásárlás a megfelelő kategória kiadása. A kötött juttatás megjelenik az összesítésben, a szabad bankszámla-egyenlegtől külön.
- **Egészségpénztár:** Isti saját pénzből fizet be. A befizetés a forrásszámla és a pénztár közötti átvezetés, nem új bevétel és nem kétszer elszámolandó vásárlás. A tényleges vásárlás majd kiadás; jelenleg nincs ilyen vásárlás, nem generálunk kezdő költéseket.
- Pénztári díj tényleges levonásként külön kiadás lehet, nem vásárlásnak álcázott teljes befizetés. Utólagos számlatérítés és adójóváírás részletes kezelése csak későbbi ilyen használat esetén tisztázandó.

#### 2.2.3 Örs babakötvény-megtakarítása
- Örs (a felhasználó egyéves kisfia) MÁK-nál vezetett babakötvény-befektetései külön gyermekmegtakarításként szerepelnek.
- A **havi fix összegű befizetés** ismétlődő sablonból tervezhető; az összeg és az esedékesség beállítható, későbbi változtatásuk megőrzi a korábbi hónapok adatait.
- A befizetés megtakarítási/befektetési pénzmozgás, nem a `család → gyerek` fogyasztási kiadása. A befizetés és a hozzá kapcsolódó befektetési bejegyzés nem számolható kétszer.
- Havi és éves befizetési összesítő, befektetésenkénti nyilvántartás és aktuális érték jelenjen meg a Befektetéseim oldalon, Örs szerint elkülönítve.
- **Örs megtakarítása teljesen külön kezelt cél**: befizetései és vagyona nem számítanak bele a családi százalékos megtakarítási célba, sem annak teljesítésébe. A forrásszámlán a tényleges utalás természetesen csökkenti az egyenleget.
- A pontos összeg, forrásszámla, befektetési adatok és hozamszámítás a felhasználó által megadandó; kamatot vagy támogatást nem feltételezünk. **Örs havi befizetése külön soron csökkenti a terv szerinti maradékot és a CF-et**, a családi megtakarítási cél teljesítésétől függetlenül.

#### 2.2.4 Anna havi számla-helyzetjelentése
- A felhasználó minden hónap **utolsó napján** megadja Anna számlájának egyenlegét. Adatok: számla, állapot dátuma, összeg, opcionális megjegyzés; a rögzítés ideje külön tárolható.
- Havi előzmények és az utolsó ismert egyenleg látszanak, mindig dátummal és „helyzetjelentés” jelöléssel. Hónapon belül nem állítjuk, hogy ez a mai egyenleg, mert Anna saját forgalmát nem követjük.
- A pillanatkép nem pénzmozgás: nem módosítja a havi bevételt, kiadást, CF-et vagy a megtakarítási cél teljesítését. Két hónap egyenlegkülönbségéből nem generálunk automatikusan ismeretlen költéseket vagy bevételeket.
- A főszámlára küldött hozzájárulás külön bevételi tétel marad. Nem vonjuk le még egyszer egy utólag megadott, azt már tükröző havi egyenlegből.
- Az Áttekintésen Anna utolsó ismert egyenlege külön kártyán, a teljes forgalommal követett számlák számított egyenlegeitől megkülönböztetve jelenik meg. A hiányzó havi jelentés látható; késve is pótolható a megfelelő hónapra.

### 2.3 Tétel mezői
- dátum, típus, kategória (+ alkategória), összeg, megjegyzés
- **számla** (kötelező, alapból a főszámla): melyik számlán történt a tétel; ebből jön a számla- és személyszűrés
- átvezetésnél forrás- és célszámla, valamint az összetartozó oldalak kapcsolata; megtakarításnál kapcsolódó befektetés/kedvezményezett (ha van)
- **jelölő: magán / üzleti** (alapból magán). Az AI-eszközök, tárhely, tanúsítvány stb. gyakran üzleti jellegűek, külön szűrhetők.
- hivatkozás ismétlődő sablonra (ha abból jött létre)
- hitelhez tartozó kiadásnál a konkrét hitel hivatkozása, hogy a különböző hitelek törlesztései külön és együtt is összesíthetők legyenek

### 2.4 Kategóriák (szerkeszthető, sorrendezhető, színezhető, alkategóriákkal)

A **Forrás** oszlop: **E** = Excel havi lapja, **É** = Excel éves lapja, **K** = a bankkivonatból, a valós költések alapján.

| # | Kategória | Alkategóriák | Forrás |
|---|---|---|---|
| 1 | élelmiszer | bolti bevásárlás, online rendelés, zöldség-gyümölcs, pék | E, K |
| 2 | étkezés kint | étterem, gyorsétterem, kávézó, kiszállítás | K |
| 3 | lakhatás – rezsi | áram/gáz, lakásfelszerelés (bútor, barkács), karbantartás | E, K |
| 4 | utazás | tömegközlekedés (bérlet, BudapestGO), szállás, nyaralás/külföldi költés | E, K |
| 5 | autó | üzemanyag, parkolás, autópálya-matrica, szerviz/javítás, autómosó | K |
| 6 | tisztálkodás | fodrász, tisztítószer, drogéria | E, K |
| 7 | ruházat | ruha, cipő | E, K |
| 8 | gyógyszer / vitamin | patika, vitamin | E, K |
| 9 | orvos / vizsgálat | labor, magánorvos, egyéb egészségügyi szolgáltatás | K |
| 10 | előfizetések | streaming, zene, Apple/felhő, AI és fejlesztői eszközök, tárhely/domain | E, K |
| 11 | sportolás | edzés, egészség (masszázs, sporttáplálék) | E |
| 12 | önfejlesztés | tanfolyam, tanúsítvány/újraminősítés, könyv | E, K |
| 13 | hitel | külön megadható hitelek törlesztései, extra törlesztés; a konkrét hitelt külön kapcsolat azonosítja | E, K |
| 14 | biztosítás | életbiztosítás, hitelfedezeti védelem, kezességvállalási díj, egyéb biztosítás | K |
| 15 | telefon | telefon, internet/TV | E, K |
| 16 | család | rendszeres családi átutalás, gyerek, ajándék | K |
| 17 | szórakozás | mozi, programok, játék | É, K |
| 18 | adó / hatósági / banki díj | adó, hatósági befizetés, csekk, banki költség | K |
| 19 | készpénz (felosztandó) | ATM-felvét, később kategóriákra bontható | K |
| 20 | egyéb / besorolatlan | amit nem sikerült besorolni | K |
| 21 | zsebpénz | személyenként (Isti, Anna) | K (a Zsebpénzek oldalról töltődik) |
| 22 | adakozás | – | É (alapból **kikapcsolva**) |
| 23 | egyetem | – | É (alapból **kikapcsolva**) |

Fontos: egyetlen kategórialista legyen, hogy a havi és éves nézet mindig egyezzen. A *készpénz (felosztandó)* kategóriánál az app emlékeztessen, hogy bontsa szét a tételt valódi kategóriákra. A **zsebpénz** kategória a Zsebpénzek oldalról (3.5) töltődik, és nem számolódhat kétszer a *család* kategóriával.

### 2.5 Havi terv (keret)
Hónaponként, kategóriánként egy tervezett összeg. Van egy **alapterv** (sablon), ami minden új hónapra átmásolódik, de hónaponként felülírható. Az új kategóriák (étkezés kint, autó, biztosítás, család, orvos / vizsgálat, adó stb.) keretét a felhasználó állítja be. **A „terv vs. tény” nézet elsősorban ezeket az újakat segít kikalibrálni.**

**Egyetlen közös családi keret** van kategóriánként; számlánként és személyenként külön részkeretek nem szükségesek. Szűréskor a költés adott része látszik, a családi keret és annak maradéka egyértelműen családi összesítőként jelenik meg. Szűrt költésből nem számítunk látszólagos személyes maradékot a teljes családi keret levonásával.

### 2.6 Beállítások
- év, évi nyitó összeg, megtakarítási arány (alapból 10%)
- személyek a zsebpénzhez (alapból: Isti, Anna)
- napi telefonos emlékeztető, alapból 19:00, Europe/Budapest; értesítési engedély és próbaküldés
- Anna havi utolsó napi egyenleg-emlékeztetője, alapból az esti 19:00-s jelzéshez kapcsolva, állítható időponttal

### 2.7 Hitelek
- **Két különböző meglévő hitel**, eltérő kamatokkal; nincs két hitelre korlátozás. Később új hitel, áruhitel vagy más részletfizetés külön felvehető, szerkeszthető és lezárható, az előzmények megőrzésével.
- Hitelenként külön megadható név, hitelező, eredeti összeg, nyilvántartott fennálló tartozás, kamat/kamatozás adatai, havi törlesztő, esedékesség, futamidő és megjegyzés. A kezdőadatokat a felhasználó tölti ki; a pontos szerződéses feltételeket nem találgatjuk.
- A kamat- és törlesztőváltozások előzménye megőrzendő. Automatikus amortizáció és tőke/kamat felbontás csak külön tisztázott szerződéses szabály alapján készülhet; jelenleg a kézi nyilvántartás és a tényleges törlesztések követése a cél.
- A korábbi „ingatlan rendszeres díja” hitelhez tartozik; nem rezsiként könyvelendő. A konkrét hitel a kézi rögzítéskor kiválasztható.
- Az `&&TF01` megjelölés az egyik hitelhez tartozik; a korábbi két levonás pontos hitelenkénti/szerep szerinti bontását még nem ismerjük.
- Az alszámlán elkülönített fedezet hitelhez kapcsolható, de nem törlesztés. A tényleges banki levonás kapcsolódik a hitel havi kiadásához.
- A hitel nyilvántartásában és a havi költésben ugyanaz a törlesztési tétel látszik, egyszeri elszámolással. A puszta pénzmozgásból nem következtetünk automatikusan a fennálló tőketartozás csökkenésére.
- Az új hitel a megadott kezdődátumtól részt vesz az esedékességi jelzésekben, hitelenkénti és összes hiteltörlesztési kimutatásban, havi/éves kiadásokban és CF-ben a ténylegesen rögzített törlesztéseivel. A hitel felvétele önmagában nem hoz létre kifizetett kiadást és nem írja át a korábbi hónapokat.

---

## 3. Oldalak

### 3.1 Irányítópult (kezdőoldal)
- Felül **nézetváltó**: összes számla (család) / számla szerint / személy szerint
- **Számlák állása**: számlánként a havi be- és kimenő forgalom, és mennyi ment a Raiffeisen- és a zsebpénz-számlára
- **Elkülönített egyenlegek**: a főszámlához tartozó alszámla, SZÉP-kártya és egészségpénztár külön kártyán; Örs babakötvényének havi befizetése és összesített értéke külön gyermekmegtakarításként
- **Anna helyzetjelentése**: utolsó ismert egyenleg dátummal, havi előzmények és az esedékes/hiányzó jelentés jelzése, számított napi egyenleg helyett
- Havi összkiadás, összbevétel, **megtakarítási cél / tényleges félretétel**, havi CF és **terv szerinti maradék**, valamint külön **valós számlaegyenlegek**. Örs befizetése és vagyona külön gyermekmegtakarítási kártyán látszik, nem a családi megtakarítási cél teljesítésében.
- Napi átlag költés, legdrágább nap (dátummal)
- Kategóriánként **keret-sáv**: elköltött / terv / maradék (zöld → sárga → piros)
- Kördiagram a kategóriák arányáról, napi kiadás oszlopdiagram a hónapra
- **Terv vs. tény** összefoglaló: mely kategóriák vannak túllépve
- Fix terhek a hónapban: mi fizetve, mi esedékes még (az ismétlődő tételekből)
- Nagy **„+ Kiadás”** és **„+ Bevétel”** gomb mindig elérhetően

### 3.2 Havi nézet (az Excel havi lapjainak megfelelője)
> **Telefonon** széles táblázat helyett napi lista jelenik meg (lásd 1.1). Az alábbi rács az asztali nézetre vonatkozik, telefonon a „Táblázat” kapcsolóval érhető el.

- Hónapválasztó (január–december, előző/következő nyilak) és számla/személy szűrő
- **Napi rács**: sorok = a hónap napjai (sorszám + napnév), oszlopok = kategóriák, utolsó oszlop = napi összes kiadás. Cellára kattintva szerkeszthető, vagy a napra kattintva megnyílik a napi tételek listája.
- A rács alatt: **Összkiadás** (kategóriánként), **Havi terv**, **Ennyit költhetek** (lásd 4. fejezet)
- Bevétel oszlopok: Munka, Egyéb, Összes
- **Megjegyzés** minden naphoz (az Excel „megjegyzések” oszlopa)
- A hónap napjainak száma automatikus (február, szökőév stb.)
- A mai nap kiemelve. Átvezetés és megtakarítás típusú tétel nem jelenik meg a kiadás-rácsban, csak külön sávban.

### 3.3 Tervezés
- Alapterv szerkesztése kategóriánként
- Hónaponkénti eltérés felülírása
- Egy gombbal „előző hónap tervének másolása”
- Javaslat a valós átlagból: „az elmúlt 3 hónap átlagos kiadása ebben a kategóriában”, egy kattintással átvehető tervnek

### 3.4 Éves összesítő
- Kategóriánként: **évi kiadás**, **havi átlag**, **napi átlag**, összegzés
- **Évi bevétel**: Munka, Egyéb, összesen, valamint havi és napi átlag
- Felosztás a megtakarítási arány szerint (alapból 90% felhasználható / 10% megtakarítás)
- **Évi / havi / napi CF**
- Hónapok közti összehasonlító diagram (kiadás, bevétel, CF havonta), és kategóriánként **terv vs. tény** az évre

### 3.5 Zsebpénzek
- Táblázat: sorok = 12 hónap, oszlopok = személyek (Isti, Anna, bővíthető)
- A havi zsebpénz a zsebpénz-módú számla (Revolut) feltöltéseiből **automatikusan** töltődik, Anna zsebpénze kézzel is felvehető
- Évi összeg személyenként
- A zsebpénz mindig kiadásnak számít (kategória: zsebpénz), a számla belső költéseit nem kell kategorizálni

### 3.6 Befektetéseim
Soronként egy befektetés:
- Neve, Összege, Dátuma, Kamatozása, Egyéb (megjegyzés)
- **Összeg + kamatozás** (automatikus számítás a kamatozásból és a dátumból)
- Jelenleg (aktuális érték, kézzel frissíthető)
- Pénztár (külön oszlop a készpénz/pénztár tételeknek)

**Örs babakötvényei** külön csoportban: kedvezményezett, MÁK-nyilvántartás, befektetések, havi tervezett és tényleges befizetés, éves befizetési összeg, aktuális érték. A havi befizetések a pénzmozgásokhoz kapcsolódnak, nem külön, újból hozzáadott összegek. A hozamszámítás az adott befektetés később megadott feltételeihez igazodik.

Alul **SZUMMA**: az „Összeg + kamatozás” és a „Pénztár” összege.

### 3.7 Kintlévőségeim
Általános befektetési kamatszámítás és személyes kölcsönök/kintlévőségek részletszabályai későbbre halasztva; a banki hitelek és Örs babakötvénye ettől függetlenül a jelenlegi feladat része. A Kintlévőségeim teljes modulja külön későbbi lépésben készül.

Két külön lista (telefonon külön füleken):
- **Kinek tartozom én?** – név, tartozás összege, dátum, megjegyzés
- **Ki tartozik nekem?** – név, tartozás összege, dátum, megjegyzés. Ide kerülnek automatikusan a `kölcsön adás` típusú tételek (pl. a cégnek adott tagi kölcsön), a visszafizetés levonja belőle.
- Mindkét lista tetején az **összeg**, és „kifizetve” jelölő. A kifizetett tétel megmarad az előzményekben, de kikerül az összegből.

### 3.8 Beállítások
- Kategóriák és alkategóriák, megtakarítási arány, évi nyitó összeg, új év indítása
- Telefonos emlékeztető bekapcsolása, értesítési engedély, próbaértesítés
- Mentés / visszaállítás, export (CSV és Excel)

### 3.9 Hiteleim
- Telefonon hitelenként külön kártya, lenyitható adatokkal és szerkesztéssel; a Továbbiak menüből elérhető.
- Hitelenként külön törlesztési előzmények, kamatadatok, havi esedékesség és fizetett/még esedékes jelzés.
- A havi nézetben a `hitel` kategória összesít, a részletek hitelenként bonthatók.
- Az alszámlás hitelfedezet elkülönítése és a valódi törlesztés külön látszik.
- **Új hitel felvétele** (áruhitel is), meglévő szerkesztése/lezárása; minden rögzített törlesztés automatikusan megjelenik a közös havi és éves számításokban, egyszer.

---

## 4. Számítási logika

Az Excelben hónapról hónapra kicsit eltérő képletek voltak. Ezeket **egyetlen közös szabályra** kell cserélni.

**Havi szinten** (csak `kiadás` típusú tételek számítanak költésnek)
- Az összevont családi nézetben a követett saját számlák közötti átvezetések sem költésnek, sem bevételnek nem számítanak. **Bevétel** a kívülről érkező pénz, valamint Anna főszámlára érkező hozzájárulása, mert az ő saját forgalmát nem követjük. Havi egyenleg-helyzetjelentése nem bevétel.
- **Alszámlás hitelfedezet**: a félretétel és a visszavezetés nem módosítja a családi bevételt, kiadást vagy CF-et; az egyes számlák egyenlegét módosítja. Kiadásként kizárólag a tényleges banki hitellevonás számít. Az átvezetett többlet nem növeli a hitelkiadást.
- **Örs babakötvény-befizetése** külön gyermekmegtakarítás, nem fogyasztási kiadás, nem a családi százalékos megtakarítási cél része, és nem növeli annak teljesítését. A tényleges befizetés csökkenti a forrásszámla valós egyenlegét, és külön soron a havi CF-et/terv szerinti maradékot is.
- **SZÉP-kártya és egészségpénztár**: külön egyenleg és forgalom; munkáltatói SZÉP-jóváírás külső bevétel/juttatás (Munka / SZÉP-juttatás), saját egészségpénztári befizetés belső átvezetés. A tényleges vásárlás kiadás. A SZÉP-juttatás kötött egyenlegként külön látszik; nem növeli a főszámla valós egyenlegét. Térítés/jóváírás további szabályai a későbbi használatkor tisztázandók; a feltöltés és a vásárlás nem eredményezhet kétszer elszámolt költést.
- **Napi kiadás** = a nap összes kiadása kategóriák szerint összegezve
- **Összkiadás (kategóriánként)** = a hónap kiadásainak összege kategóriánként; **havi összkiadás** = ezek összege
- **Összes bevétel** = Munka + Egyéb
- **Megtakarítási cél** = összes bevétel × megtakarítási arány (alapból 10%). Ez tervezett elkülönítés, önmagában nem hoz létre pénzmozgást.
- A családi megtakarítási cél **havi**: mindig az adott hónap saját bevételének megadott százaléka. Az arány évhez tartozó beállítás, az adott év mind a 12 hónapjára érvényes. A felületen a havi/éves célok, keretek és összesítések időszaka egyértelműen szerepeljen; a valós számlaegyenlegnél és nyitóegyenlegnél konkrét dátum tartozik az összeghez. Az évhez tartozó beállítások és az éves összesítés évválasztót használ, a havi nézetek év- és hónapválasztót.
- **Tényleges félretétel** = a családi megtakarítási célhoz kapcsolt, valóban rögzített nettó megtakarítási pénzmozgások összege. Örs befizetése és az ideiglenes alszámlás hitelfedezet nem tartozik ide; az összetartozó pénzmozgás és befektetési bejegyzés egyszer számít. A megtakarításból visszavett pénz csökkenti a nettó teljesítést.
- **Megtakarítási cél maradéka** = megtakarítási cél − tényleges félretétel; negatív érték túlteljesítés. Mindkét összeg és a különbség megjelenik.
- **Összbevétel (felhasználható)** = összes bevétel − megtakarítási cél. A tényleges félretételt a terv szerinti képlet nem vonja le még egyszer.
- **Havi CF** = felhasználható bevétel − havi összkiadás − Örs havi tényleges befizetése. A befizetés külön sor, nem újra hozzáadott fogyasztási kiadás. Példa: 1 000 000 Ft bevétel − 100 000 Ft családi megtakarítási cél − 600 000 Ft kiadás − 20 000 Ft Örsnek = 280 000 Ft CF.
- **Havi nyitó összeg** = az előző hónap záró összege (januárban az évi nyitó összeg)
- **Terv szerinti havi maradék (korábbi nevén havi záró összeg)** = havi nyitó + havi CF = havi nyitó + felhasználható bevétel − havi összkiadás − Örs havi tényleges befizetése.
- **Valós számlaegyenleg** = az adott számla valós nyitó egyenlege + minden tényleges bejövő pénzmozgás − minden tényleges kimenő pénzmozgás. A megtakarítási cél puszta meghatározása nem csökkenti. Belső átvezetés a forrás- és célszámlát módosítja, egyszer-egyszer. A valós nyitóegyenlegek és a terv szerinti évi nyitó összeg külön beállítások.
- A valós egyenleg és a terv szerinti maradék **külön mutatóként** szerepel; egyik sem helyettesíti a másikat. A számlacsoportok (szabad bankszámlák, kötött egyenlegek, gyermekmegtakarítás) külön összesíthetők.
- **Anna egyenlege kivétel a forgalomból számított egyenlegek között**: csak dátumozott havi pillanatkép. Nem generál pénzmozgást, és nem módosítja a terv szerinti éves/havi nyitó-záró láncot.
- **Napi átlag költés** = havi összkiadás ÷ a hónap napjainak száma (folyó hónapban a mai napot is beleértve az eltelt napok száma, ne fix 31)
- **Legdrágább nap** = a hónap legnagyobb napi összkiadása (+ melyik nap)

**„Ennyit költhetek” (keret átvitel)**
Kategóriánként: `előző hónap maradéka + az aktuális havi terv − az aktuális havi kiadás`. A maradék áthalmozódik hónapról hónapra az év során. Negatív érték = túllépés, pirossal. Kategóriánként kapcsoló: **átvitel be/ki** (pl. élelmiszernél lehet, hogy havonta nullázódjon).

**Terv vs. tény**: kategóriánként `tény − terv`, forintban és százalékban, havi és éves szinten.

**Éves szinten**
- Évi kiadás kategóriánként = a 12 hónap összege; havi átlag = ÷ 12; napi átlag = ÷ az év napjainak száma (365/366, egységesen)
- Évi bevétel Munka / Egyéb / összes, ugyanígy havi és napi bontásban
- **Évi CF = a 12 havi CF összege**, a családi megtakarítási cél és Örs külön befizetéseinek levonása után, egyezően a havi képlettel. Örs befizetései külön éves soron szerepelnek. Havi CF-átlag = évi CF ÷ 12; napi CF-átlag = évi CF ÷ az év napjainak száma.

---

## 5. Gyors rögzítés (kezelhetőség)

- **Gyors kiadás ablak** (telefonon alulról felcsúszó panel, a nagy **+** gombról): összeg → kategória (nagy, színes gombok) → kész. A dátum alapból a mai, módosítható. Megjegyzés opcionális.
- Az utolsó használt kategóriák elöl jelennek meg.
- A számmező tizedesvessző nélkül, egész forintra, számbillentyűzettel.
- Minden forintos összegmező **gépelés közben is szóközzel tagol**: `700000` → `700 000`. Ez a gyors rögzítésre, a havi/alapterv kereteire és a nyitóösszegekre is vonatkozik. A tagolás nem pont és nem vessző; mentéskor a tagolatlan egész szám kerül az adatbázisba. A mező közepén történő javítás/törlés és az engedélyezett negatív nyitóegyenleg is kezelhető.
- **Visszavonás** az utolsó rögzítésre.
- Tétel szerkesztése/törlése a napi listában, megerősítéssel.
- Keresés és szűrés: időszak, kategória, típus, szöveg a megjegyzésben, összeg szerint, magán/üzleti.
- **Esti rögzítés**: a 19:00-s értesítés az adott napi listára/gyors rögzítésre vezet. Egymás után több összeg is felvihető, javítható, ellenőrizhető; a dátum módosítható korábbi költések pótlásához. Revolutnál elegendő az összeg és a „kp” megjelölés, részletes költési cél nélkül.

---

## 6. Kézi rögzítés, ismétlődő tételek és korábbi besorolási háttér

**A kézi bevitel az aktuális működés.** A 6.1–6.2 kivonatos/MCC-szabályai megőrzött háttéranyagok egy esetleges későbbi importhoz, nem az első négy fázis kötelező funkciói. A felhasználói döntések és a kézzel megadott típus/hitelkapcsolat elsőbbséget élveznek a régi szöveges mintákkal szemben.

### 6.1 Besorolási szabályok (alapértelmezett, szerkeszthető)
A bankkivonat kártyás tételein a kereskedő neve előtt egy **4 jegyű kereskedői kód (MCC)** áll (pl. `5411`, `5814`). Ez megbízható besorolási kulcs. A szabály sorrendje: **1. kereskedő neve**, **2. közlemény**, **3. MCC**, ha egyik sem talál: *egyéb / besorolatlan*.

| MCC | Alapértelmezett besorolás |
|---|---|
| 5411, 5499, 5311 (bolt, vegyesbolt, áruház) | élelmiszer |
| 5812, 5814 | étkezés kint |
| 5541 | autó → üzemanyag |
| 7523, 4784 | autó → parkolás / autópálya-matrica |
| 7542 | autó → autómosó |
| 4131 | utazás → tömegközlekedés |
| 4900 | lakhatás – rezsi |
| 4814 | telefon |
| 4899, 5815, 5818 | előfizetések (streaming, digitális) |
| 5734 | előfizetések → AI és fejlesztői eszközök |
| 7372 | előfizetések → tárhely/domain |
| 5912 | gyógyszer / vitamin |
| 8071, 8099 | orvos / vizsgálat |
| 7297, 7298 | sportolás → egészség |
| 5977 | tisztálkodás → drogéria |
| 5661, 5651, 5691, 5699, 5137 | ruházat |
| 5712, 5722, 5714, 5200 | lakhatás → lakásfelszerelés |
| 6300 | biztosítás |
| 9399 | adó / hatósági / banki díj |
| 8299 | önfejlesztés → tanúsítvány |
| 7832, 7995 | szórakozás |
| 6011 | készpénz (felosztandó) |
| 6012 (pénzintézeti feltöltés, pl. Revolut) | zsebpénz (Isti), ha a számla zsebpénz-módú |
| 5262, 5331 (webshop) | egyéb / besorolatlan |

Kereskedő-név szabályok (példák a valós forgalomból): bolti láncok (Lidl, Penny, Tesco, Spar, Coop, Aldi, CBA, dm/Rossmann → drogéria), üzemanyag (OMV, MOL, Shell), McDonald's, Wolt, kifli.hu, MVM Next, Deichmann, IKEA, Disney+, HBO Max, SkyShowtime, Spotify, Apple, Anthropic, OpenAI, Forpsi, EC-Council. A **közleménybeli szavak** is szabály: `autójavítás` → autó/szerviz, `telefonszámla` → telefon, `kaja` / `hitel` / `törlesztő` → átvezetés/hitel (lásd lent), `tagi kölcsön` → kölcsön adás.

A felhasználó **egy kattintással új szabályt hozhat létre** egy besorolatlan tételből („minden ilyen kereskedő legyen ez a kategória”).

### 6.2 Átvezetések és nem-költés tételek felismerése
Az alábbi minták **alapból nem költések**, hanem `átvezetés` / `megtakarítás` / `kölcsön adás`:
- „Automatikus átvezetés fedezetre” saját számlára → `átvezetés`
- Páros mozgás a családi számlák között → `átvezetés` (2.2)
- Havi átutalás a **Raiffeisen családi számlára** (`kaja` közlemény) → `átvezetés` részletes módban, **élelmiszer kiadás** összevont módban
- Főszámla ↔ hozzá tartozó alszámla: `átvezetés` akkor is, ha a közlemény hitelre utal; csak a tényleges banki törlesztés `kiadás`
- `hitel`, `hiteltörlesztő`, `törleszto` közlemény → `kiadás` a **hitel** kategóriában, ha nem azonosított saját számlák közötti átvezetés
- A korábban „webkincstár” jellegűként leírt rendszeres utalás **Örs MÁK-befizetése**, nem hatósági díj: `megtakarítás`, kapcsolattal Örs befektetési nyilvántartásához. Más, ismeretlen MÁK-tételre nem általánosítható.
- Kerekítéses megtakarítás (WITHSAVE azonosítós apró átutalások) → `megtakarítás`, kategóriánként összevonva, hogy ne zsúfolja a listát
- **Revolut-feltöltés → Isti zsebpénze** (kategória: zsebpénz, 3.5)
- `tagi kölcsön` közlemény → `kölcsön adás`

### 6.3 Ismétlődő tételek (kézzel beállítható sablonok)
A korábbi kivonatos lista kiindulási javaslat, nem automatikusan betöltendő tényadat. A tényleges sablonokat, összegeket és esedékességet a felhasználó állítja be; a legutóbbi kézzel rögzített tényből később összeg javasolható. Sablonból létrehozott tétel nem jelölhető automatikusan kifizetettnek; a tényleges fizetést a felhasználó erősíti meg.

| Tétel | Kategória | Gyakoriság |
|---|---|---|
| Hitel 1 törlesztés (név és adatok kézzel megadva) | hitel / Hitel 1 | havi, külön összeg és esedékesség |
| Hitel 2 törlesztés (név és adatok kézzel megadva) | hitel / Hitel 2 | havi, külön összeg és esedékesség |
| Hitel és további összeg elkülönítése a CIB alszámlán | átvezetés | havi, változó összeggel |
| Hitel fedezetének visszavezetése a főszámlára | átvezetés | havi, hónap végén |
| Örs babakötvény-befizetése | megtakarítás / Örs befektetése | havi, felhasználó által megadott fix összeggel |
| Extra törlesztés | hitel | eseti |
| Hitelfedezeti védelem (2 szerződés) | biztosítás | havi, hónap elején |
| Szabad felhasználású kölcsön hitelfedezeti díja | biztosítás | havi, **kifutó** (jún. után nem szerepel) |
| Életbiztosítás | biztosítás | havi, hónap elején |
| Egyéb biztosítás (kártyás, Generali) | biztosítás | időszakos |
| Áram/gáz (MVM Next) | lakhatás – rezsi | havi, több részletben is |
| Korábbi „ingatlan rendszeres díja” | a megfelelő hitelhez kapcsolandó | nem önálló plusz tétel, ha a fenti törlesztések egyike |
| Telefonszámla | telefon | havi, 10-e körül |
| Internet („d-ugyfelka”, felhasználó szerint valószínűleg internet) | telefon → internet/TV | havi; az azonosítás előzetes |
| Streaming (Disney+, HBO Max, SkyShowtime, Spotify, DumaTV) | előfizetések | havi |
| Apple előfizetések | előfizetések | havi |
| AI-előfizetések (Claude, ChatGPT) | előfizetések | havi |
| Tárhely/domain | előfizetések | éves/havi |
| Tömegközlekedés (BudapestGO) | utazás | havi |
| Rendszeres családi átutalás | család | havi, 6–10. között |
| Rendszeres hatósági díj | adó / hatósági / banki díj | havi, **kifutó** (ápr–jún) |
| Csekkbefizetés (iCsekk) | adó / hatósági / banki díj | kb. kéthavonta |
| Banki tranzakciós díj (qvik) | adó / hatósági / banki díj | havi |
| Havi összeg a Raiffeisen családi számlára | átvezetés / élelmiszer (a számla módja szerint) | havi |
| Revolut-feltöltés (zsebpénz) | zsebpénz | havi, több részletben |
| Anna havi átutalása a főszámlára | bevétel / Egyéb / Anna hozzájárulása | havi |
| Tagi kölcsön (cégnek) | kölcsön adás | szabálytalan |

Az app a hónap elején felkínálja ezeket létrehozásra, egy kattintással jóváhagyható, és a hónap folyamán jelzi, ha egy fix tétel még nem érkezett meg.

### 6.4 Kezdőadatok kézi megadása
- A felhasználó megadja a kezdő évet, számlák és külön egyenlegek nyitó összegeit, terveit, a két hitel adatait és Örs havi befizetését.
- Korábbi banki tételek, Excel-összegek és a 2026.04.07–10.09 időszak automatikus betöltése nem követelmény. Az előzmények kézzel pótolhatók.
- A napi költések, bevételek és átvezetések kézzel rögzítendők. A rendszeres tételeket sablon segíti.
- Valós átlagok és tervjavaslatok a kézzel felvitt adatokból készülnek. Az adatok nélküli hónapok nem kezelhetők észrevétlenül teljes, nulla költésű megfigyelésként a háromhavi tervjavaslatban.

### 6.5 Felhasználói döntések és megmaradt nyitott kérdések

**Rögzített válaszok:** az „ingatlan rendszeres díja” hitelhez tartozik; két külön hitel van, külön kamatokkal és adatokkal. A „d-ugyfelka” valószínűleg internet (előzetes azonosítás). A rendszeres „webkincstár” utalás Örs MÁK-befizetése. Az `&&TF01` az egyik hitelhez tartozik, a pontos bontás még nincs megadva. Revolutnál csak az összegek követése kell, „kp” jelöléssel, költési cél nélkül. Az éves CF a havi CF-ek összege, megtakarítás után; a napi átlagba a mai nap beleszámít. Kerekítés nem kérhető automatikusan. Kézi kezdőadatok és esti rögzítés, 19:00-s telefonos értesítés; AWS/Docker; offline rögzítés nélkül. Kintlévőségek telefonon füleken; a háromlépéses cél a kezelési lépésekre vonatkozik.

**További elfogadott döntések:** valós számlaegyenleg és terv szerinti maradék egyaránt látszik, megtakarítási cél / tényleges félretétel bontással. Örs teljesen külön gyermekmegtakarítás, nem számít bele a családi megtakarítási célba. A kategóriakeretek közös családi keretek, személyes/számlánkénti részkeretek nélkül.

**Lezárt elszámolási kérdések:** Örs tényleges befizetése a családi megtakarítási célon felül, külön csökkenti a CF-et és a terv szerinti maradékot. Raiffeisenen a tényleges vásárlások kézzel rögzített kiadások, a feltöltés átvezetés. Annánál csak a főszámlás hozzájárulás bevétel, saját forgalma helyett hónap utolsó napi egyenleg-helyzetjelentés készül, emlékeztetővel. SZÉP-kártyára munkáltató fizet, egészségpénztárba a felhasználó; egészségpénztári vásárlás eddig nincs. A hitelnyilvántartás bővíthető, későbbi áruhitel/részletfizetés is felvehető, és a tényleges törlesztések részt vesznek a közös számításokban.

**A megmaradt kérdésekhez nem találgatunk:**
- Az eredeti 5. kérdés („saját névre, havi azonosítóval utalás”) és 7. kérdés („tervezett” szolgáltatói utalások) konkrét dátum/összeg/közlemény/kedvezményezett nélkül szerepel a dokumentumban. A munkaterületen nincs hozzájuk bankkivonat. Egyelőre nem azonosíthatók, kézi bevitel mellett nem akadályozzák a fejlesztést.
- Egészségpénztári utólagos térítés és esetleges adójóváírás részletes elszámolása későbbi ilyen használat esetén; most a saját befizetés és külön egyenleg követése szükséges. Szolgáltatók és nyitó egyenlegek kézzel megadhatók.
- A meglévő és később felvett hitelek szerződéses részletei, az alszámlán maradó többlet célja, Örs havi összege/esedékessége/forrásszámlája és nyitó befektetési értékei: a felhasználó adja meg az appban; az import kihagyása miatt banki azonosító nem előfeltétel.
- Nulla tervnél a százalékos terv–tény megjelenítése, valamint nem véges tizedes átlagok megjelenítése egész forintra kerekítés nélkül.
- Általános befektetések kamatszámítása és személyes kölcsönök/kintlévőségek részletszabályai későbbi feladatok; a jelenlegi banki hiteleket és babakötvényt ez nem halasztja el.

---

## 7. További bővítés (külön lépésben)

1. **Bankkivonat-import CSV-ből vagy PDF-ből, automatikus besorolási szabályokkal**, csak későbbi igény esetén. A 6.1–6.2 háttéranyaga ehhez felhasználható.
2. **Figyelmeztetés**: ha egy kategória a keret 80%-át elérte, és ha egy előfizetés összege változott.
3. **Üzleti/magán riport**: egy kattintásos összesítés az üzleti jelölésű tételekről egy időszakra.
4. **Általános Befektetéseim és Kintlévőségeim modulok** részletes kamat- és személyes kölcsönkezelése, a felhasználó későbbi döntése alapján. Örs babakötvényének követése és a két banki hitel már az első négy fázis része.

---

## 8. Technikai javaslat

A fejlesztő (Codex) választhat másik stacket, de ez a javaslat:

- **Laravel + Inertia.js + React**, TypeScript, Tailwind CSS
- **SQLite** (egyfelhasználós, könnyű mentés) az AWS-en futó alkalmazás tartós Docker-kötetén, migrációkkal és kezdőkategóriákkal/számlákkal. Pénzügyi kezdőösszegeket és sablonértékeket a felhasználó ad meg.
- Diagramok: Recharts
- **Mobile-first CSS**: az alapstílus telefonra készül, a `md:` / `lg:` töréspontok bővítik asztali nézetre
- **PWA**: manifest, service worker, ikonok és Web Push; offline rögzítési sor nélkül. HTTPS és eszközön megadott értesítési engedély szükséges. iPhone-on a kezdőképernyőre telepített webapp értesítése ellenőrizendő a tényleges készüléken. Forrás: [Apple Web Push dokumentáció](https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers), [MDN értesítési engedély](https://developer.mozilla.org/en-US/docs/Web/API/Notifications_API/Using_the_Notifications_API).
- **Szerveroldali napi ütemezés**: 19:00 Europe/Budapest Web Push küldés a regisztrált telefonra, téli/nyári időszámítás kezelésével, napi/eszközönkénti duplikált küldés kizárásával és próbaértesítéssel. Ez a webapp emlékeztetője, nem Codex-chat automatizálás.
- Egységtesztek a 4. fejezet számításaira és a kézi pénzmozgásokra: szökőév, 28/30/31 napos hónap, mai napot tartalmazó átlag, keretátvitel, megtakarítási arány, éves/havi CF egyezése, átvezetés kizárása, két hitel elkülönített és együttes törlesztési összege, részletes/összevont/zsebpénz mód dupla számolás nélkül
- Kiegészítő egységtesztek: változó összegű alszámla-feltöltés → hitelfedezet visszavezetése → egyszeri banki hitelkiadás; `hitel` közlemény nem írja felül a saját számlás átvezetést; SZÉP/egészségpénztár egyeztetés és dupla számolás kizárása a tisztázott szabályok szerint; Örs havi fix befizetésének és befektetési bejegyzésének egyszeri elszámolása
- További tesztesetek: Raiffeisen feltöltése átvezetés és vásárlása egyszeri kiadás; Anna hozzájárulása egyszeri bevétel, havi pillanatképe nem pénzmozgás és nem módosít CF-et; új harmadik/áruhitel kezdődátuma és törlesztései helyesen kerülnek az összesítőkbe; Örs befizetése külön csökkenti CF-et, de nem teljesíti a családi megtakarítási célt; egészségpénztári befizetés átvezetés, vásárlás csak tényleges rögzítéskor jelenik meg.
- Értesítési ütemezés tesztjei: minden hónap utolsó napja, szökőéves február, Europe/Budapest téli/nyári időszámítás és az azonos idejű napi/havi jelzések összevonása; a tényleges telefonos fogadás külön ellenőrizendő.
- **AWS-en Dockerben futó webapp és adatbázis**, környezeti változókból konfigurálva, tartós adattárolással; a napi emlékeztető és mentés ütemezése is a telepítés része
- Bejelentkezés: egy felhasználó; telefonon hosszú munkamenet és **passkey/biometrikus** belépés (WebAuthn), jelszó tartalékként; a felület nyilvános hálón se legyen védelem nélkül
- Napi automatikus adatbázis-mentés

---

## 9. Kész, ha…

- [ ] **Minden funkció használható 360 px széles telefonon**, vízszintes görgetés nélkül (a „Táblázat” nézet kivételével).
- [ ] Egy új kiadás felvétele telefonon, egy kézzel, legfeljebb 3 kezelési lépéssel és 10 másodperc alatt megvan; a számjegyek beírása nem külön lépés.
- [ ] Telepíthető PWA, kezdőképernyőről teljes képernyőn indul.
- [ ] Asztali böngészőben (1024 px fölött) a Havi nézet és az Áttekintés a teljes adatot mutatja.
- [ ] Lighthouse mobil: teljesítmény és hozzáférhetőség legalább 90.
- [ ] Automatikus képernyőtesztek (pl. Playwright) 360×800, 390×844, 430×932, 768 és 1280 px méreten.
- [ ] A havi rács, az „Ennyit költhetek”, a Havi CF és az éves összesítő egymással egyezik, kézi összeadás nélkül.
- [ ] Valós számlaegyenleg és terv szerinti maradék külön látszik; a megtakarítási cél és tényleges félretétel összehasonlítható, dupla levonás nélkül.
- [ ] Örs befizetése és vagyona nem teljesíti a családi megtakarítási célt; a kategóriakeretek közösek, szűréskor sem keletkeznek személyes részkeretek.
- [ ] 12 havi nézet, Zsebpénzek, Örs babakötvény-nyilvántartása, Hiteleim és Éves összesítő elkészült; általános befektetési és kintlévőségi bővítések későbbi lépésre fenntartva.
- [ ] A kategóriák szerkeszthetők, és a havi és éves nézetben ugyanazok.
- [ ] A bankszámlák (két CIB, a főszámlához rendelt alszámla, Raiffeisen, Revolut), a SZÉP-kártya és az egészségpénztár áttekinthetők, számla és személy szerint szűrhetők; a kötött egyenlegek külön is látszanak.
- [ ] Az alszámlára félretétel és a hitelfedezet visszavezetése átvezetés; a hitel csak a tényleges levonáskor számít kiadásnak, egyszer.
- [ ] Örs babakötvényei, havi fix befizetései, éves befizetési összesítője és aktuális értéke külön gyermekmegtakarításként követhetők, dupla elszámolás nélkül.
- [ ] A kezdőadatok és napi pénzmozgások kézzel rögzíthetők; az átvezetések és megtakarítások **nem** számítanak fogyasztási kiadásnak. Import nem szükséges.
- [ ] A meglévő hitelek és újonnan felvett áruhitel/részletfizetés külön megadhatók, külön kamatokkal és törlesztési előzményekkel; számuk nem korlátozott kettőre, összesítésük egyezik a havi és éves hitelkiadással.
- [ ] Raiffeisen feltöltése átvezetés, tényleges vásárlásai egyszeri kiadások; Anna hozzájárulása bevétel, dátumozott havi számla-helyzetjelentése külön adat.
- [ ] Anna egyenlegének megadására minden hónap utolsó napján jelzés érkezik; a pillanatkép nem generál bevételt, kiadást vagy ismeretlen forgalmat.
- [ ] Örs tényleges befizetése külön soron csökkenti a havi/éves CF-et és a terv szerinti maradékot, a családi megtakarítási cél teljesítése nélkül.
- [ ] Revolutnál az elköltött összegek „kp” jelöléssel, részletes költési cél nélkül követhetők; nincs újabb családi kiadás a már elszámolt feltöltés mellett.
- [ ] Napi telefonos emlékeztető küldése 19:00 Europe/Budapest szerint működik az engedélyezett készüléken; próbaértesítés és tényleges telefonos ellenőrzés megtörtént.
- [ ] Az AWS/Docker telepítés tartós adatbázissal, HTTPS-sel, működő mentéssel és szerveroldali ütemezéssel használható.
- [ ] A terv vs. tény nézet működik kategóriánként, havi és éves szinten.
- [ ] Mentés és visszaállítás, valamint export működik.
- [ ] A számítások, kézi átvezetések, hitelösszesítők és értesítési ütemezés releváns tesztekkel le vannak fedve.

---

## 10. Fejlesztési fázisok (jóváhagyott terv)

A felhasználó 2026.10.15-én jóváhagyta a **6. fázist: Figyelmeztetések és üzleti/magán riport**, majd külön kérte a felületi és esti jelzés mellett az azonnali értesítést is a keret elérésekor. Részletes működési szabályok és ellenőrzések: [PHASE-6.md](PHASE-6.md). A figyelmeztetési alap a havi terv és áthozott maradék; a 80% felületi/esti, a 100%-os elérés/túllépés azonnali jelzést ad a jelenlegi hónap új rögzítésekor, kategória/készülék/hónap szerint egyszer. Az üzleti és magán bontás összege egyezik a teljes időszakéval; a családi CF képlete változatlan.

Az első négy fázis után a felhasználó 2026.10.15-én külön jóváhagyta az **5. fázist: Működési állapot és mentések**. Beállításokban megjelenő ütemező-életjel, értesítési küldéselőzmény és szervermentési állapot; a kézi és napi automatikus mentés megkülönböztetésével, magyar időpontokkal és külön frissítéssel. Nincs új pénzügyi elszámolási szabály, import vagy kintlévőségi modul. Részletek: [PHASE-5.md](PHASE-5.md). A távoli szerver aktuális állapotát a frissítés utáni saját jelentések alapján kell ellenőrizni.

A felhasználó jóváhagyta a fejlesztés indítását és folytatását. Az 1. fázis elkészült: [PHASE-1.md](PHASE-1.md). A 2. fázis is elkészült: [PHASE-2.md](PHASE-2.md). A 3. fázis folytatását is jóváhagyta a felhasználó: a helyi funkciók elkészültek, [PHASE-3.md](PHASE-3.md). A felhasználó 2026.10.15-én sikeres AWS-telepítést jelzett: Ubuntu, buksza.duktor.hu, /opt/buksza; HTTPS /up és /belepes ellenőrzés: 200. A felhasználó képernyőképpel igazolta az iPhone-push megérkezését (2026.10.15.). Az ütemezett napi/hónap végi fogadás, az éles ütemező életjele és az első ütemezett mentés még ellenőrizendő. A felhasználó 2026.10.15-én jóváhagyta a 4. fázist; funkciói elkészültek: [PHASE-4.md](PHASE-4.md). A 4. fázis végén bemutató és megállás következik; új funkció külön jóváhagyással indul.

Minden képernyő először **360 px széles telefonra** készül, és az ellenőrzött mobilnézet után bővül asztalira. Minden fázis végén bemutató és az ellenőrzések eredményének ismertetése következik, majd megállás; a következő fázis a felhasználó jóváhagyásával indul. A specifikáció bővítése önmagában nem engedély a kódolás megkezdésére.

1. **Alap: kategóriák, havi nézet, számítások tesztekkel.** Projektalap, magyar formázás, telefonos navigáció, kategóriák, alapterv és havi felülírás, kézi kezdőadatok és napi kiadás/bevétel, esti rögzítési folyamat, alap-Áttekintés, közös havi és éves számítási modul. A 4. fejezet számításait egységtesztek fedik le, beleértve a megtakarítás utáni éves/havi CF egyezését és a mai napot tartalmazó átlagot. Az adatmodell előkészíti a számlákat, hiteleket és Örs befektetéseit; a nyitott számítási döntéseket megvalósításuk előtt tisztázzuk.
2. **Számlák, átvezetések és bővíthető hitelnyilvántartás.** Bankszámlák, CIB alszámla, SZÉP-kártya és egészségpénztár egyenlege és kézi forgalma; Raiffeisen-vásárlások és feltöltések elkülönítése; Anna főszámlás hozzájárulása és havi egyenleg-pillanatképei. Személy- és számlaszűrés, egyszer rögzítendő átvezetések, dupla költés kizárása. Hiteleim: külön kamatadatok, törlesztők, esedékesség és előzmények, új hitel/áruhitel felvétele és lezárása. Alszámlás hitelfedezet és valódi törlesztés elkülönítése. Örs megtakarítási pénzmozgásainak alapja.
3. **Esti rögzítés, ismétlődő sablonok és telefonos emlékeztetők.** Gyors egymás utáni kézi bevitel, rendszeres tételek jóváhagyása, hitelenkénti sablonok, Örs havi fix befizetése, kézi előzményekből tervjavaslat. PWA-telepítés és szerveroldali Web Push minden nap 19:00-kor Europe/Budapest szerint; hónap utolsó napján Anna egyenlegének megadására is jelzés, alapból a napi emlékeztetővel összevonva. Eszközregisztráció, próbaértesítés, hónaphossz/időzóna és telefonos működés ellenőrzése. AWS/Docker környezetben is ellenőrizendő. CSV-import és automatikus kivonatbesorolás helyett ezek a feladatok készülnek.
4. **Zsebpénzek, Örs babakötvénye és Éves összesítő.** Revolut „kp” összegkövetés és éves zsebpénzek; gyermekmegtakarítás havi terv/tény, éves befizetés és aktuális érték. Teljes Áttekintés, éves diagramok és összeg-egyeztetés, keresés/szűrés, export, mentés/visszaállítás, automatikus mentés és belépési kényelmi funkciók. AWS/Docker telepítés véglegesítése és a 9. fejezet elfogadási feltételeinek vizsgálata. Általános befektetési kamatszámítás és személyes kölcsön/kintlévőség modul későbbi lépésben; offline rögzítés nem készül.
