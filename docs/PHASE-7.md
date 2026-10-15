# 7. fázis – Személyes kölcsönök és megtakarítási célzsebek

A felhasználó 2026.10.15-én jóváhagyta a hiányzó pénzügyi funkciók befejezését. Válaszai: egyelőre kamatmentes személyes kölcsönök; tőkemozgások külön CF-sorokon. Általános befektetése jelenleg nincs: most hathavi **jelenlegi közös jövedelemnek** megfelelő biztonsági tartalékot és előre tervezett nagyobb lakásfelújítási összegeket szeretne külön célzsebekben követni. Termékspecifikus kamatot, hozamot vagy adót nem feltételezünk.

## Kintlévőségeim

Továbbiak → Kintlévőségeim. Külön fül: „Ki tartozik nekem?” / „Kinek tartozom én?”. Korlátlan számú névvel, kezdettel, opcionális határidővel és megjegyzéssel ellátott nyilvántartás. A korábbról fennálló tőke nyitóadat, nem új pénzmozgás. Új folyósítás, további kölcsön és részleges/teljes visszafizetés önálló kézi mozgás. Nulla fennálló összegnél „Rendezve”; az előzmények megmaradnak. Új folyósítás újra növelheti a tőkét. Bankhitelek továbbra is a Hiteleim modulban.

| Típus | Bankszámla | CF | Fennálló tőke |
|---|---|---|---|
| Kölcsön adása (`loan_given`) | − | − | nekem tartoznak + |
| Visszakapott tőke (`loan_given_return`) | + | + | nekem tartoznak − |
| Kölcsön felvétele (`loan_received`) | + | + | én tartozom + |
| Kapott kölcsön törlesztése (`loan_repaid`) | − | − | én tartozom − |

Ezek nem fogyasztási kiadások és nem Munka/Egyéb bevételek; nem emelik a százalékos megtakarítási célt. Havi CF = eddigi CF + visszakapott tőke + felvett kölcsön − adott kölcsön − személyes törlesztés. Az éves CF a 12 havi CF összege. A havi/éves felületen és az exportban külön sorok. Részletes saját bank-/alszámla választható, SZÉP/EP/Anna-pillanatkép/zsebpénzes számla nem.

## Megtakarítási célok

Továbbiak → Megtakarítási célok. Kétféle cél:

- Hathavi jövedelemtartalék: kézzel megadott aktuális közös **havi jövedelemalap × 6**. Ez teljes célösszeg, nem havi befizetési terv. A jövedelemalap módosítható; az app nem következtet a még részben rögzített hónapból és nem módosítja észrevétlenül a célt.
- Felújítás / egyéb cél: név (például Ablakcsere), teljes célösszeg, opcionális felhasználási nap és megjegyzés. Több külön munkára több külön zseb vehető fel.

A kezdőnap előtt már félretett összeg kézi nyitóadat; a meglévő számlanyitó azt már tartalmazza. Ugyanezt ne add meg nyitóként és kapcsolt korábbi félretételként is. A zseb kimutatott egyenlege az adott kezdőadat és a rögzített mozgások alapján készül, nem banki ellenőrzés vagy automatikus piaci értékbecslés.

„Félreteszek” / „Visszaveszek” egyetlen közös `saving` / `saving_return` tétel, a havi tényleges félretételbe egyszer számít. A célkapcsolat külön kapcsolótábla, nem második összeg. A havi százalékos terv külön marad, a célösszeg nem új CF-levonás. Örs befizetése nem kapcsolható családi zsebhez.

A megtakarítás helye kifejezetten választható:

- ugyanazon bankszámlán elkülönítve: a bankegyenleg és havi bankforgalom változatlan, a zseb és a tényleges félretétel módosul;
- saját megtakarítási számla: a valódi kétoldali mozgást egyszer könyveljük;
- külső félretétel külön számlanyilvántartás nélkül: csak a fizető/fogadó bankszámla oldala számított.

Az azonos számlán történő elkülönítés a célzsebből indítható. Egy már meglévő családi félretétel utólag is hozzárendelhető, új könyvelés nélkül; egy teljes tétel egy célhoz tartozik. A célok közti dátumozott átcsoportosítás a zsebeket módosítja, **sem bankszámlát, sem havi félretételt, sem CF-et** nem változtat. Előzménye mindkét oldalon megmarad; következetes állapot mellett megerősítéssel törölhető.

Felújításnál előbb felszabadítod az összeget a zsebből, majd a tényleges vásárlást egyszer rögzíted a megfelelő kiadási kategóriában. A zseb nyitóösszege vagy a cél meghatározása önmagában nem vásárlás. A bankegyenleg az ugyanazon számlán maradó zsebek pénzét is tartalmazza; a zsebeket nem adjuk még egyszer a számlacsoportokhoz.

## Következetesség, előzmények, mentés

A nézet a választott hónap utolsó napjáig rögzített állást mutatja, évhatáron át is. Célösszegek és nevek az aktuális kézi beállítások; nem verziózott múltbeli céltervek. Azonos napi összegeket napi zárásként összesítünk. A részfizetés/kivétel nem okozhat negatív fennálló összeget egyik érintett napon sem. Ezt új mozgás, módosítás, törlés, visszavonás, kapcsolatbontás, nyitóadat-változás és ismétlődő tétel visszaállítása is ellenőrzi. A művelet egy adatbázis-tranzakcióban történik. A pénzmozgás és az átcsoportosítás UUID-val ismételhető, duplázás nélkül.

Az új táblák: `finance_positions`, `position_transactions`, `goal_transfers`. A meglévő pénzügyi táblák és Örs adatai változatlan szerkezetűek. Új titkosított mentés mindent tartalmaz; az első hat fázis mentései is visszaállíthatók (az akkor még nem létező cél-/kölcsönmodul üresre áll), előtte a teljes jelenlegi állapot biztonsági másolata elkészül. Belépési és értesítési adatok nem cserélődnek.

Az éves Excel további Nyilvántartások és Célátcsoportosítások munkalapokat tartalmaz, a meglévő munkalapok sorrendje megmarad. A tételekhez a kapcsolt kölcsön/cél neve is tartozik. A kimutatás év végéig fennálló összegeket tartalmaz, a célátcsoportosítások kezdettől látszanak; a valódi éves mozgások az éves Tételek munkalapon vannak. A Nyilvántartások külön CSV-ben is letölthető.

A telepítési próba egy korábbi kérésilimit-ütközést is feltárt: az export, mentés, passkey és push végpontok közös alapkulcson fogyasztották a saját limitjeiket, így több letöltés után a passkey-regisztráció 429 választ kaphatott. Most műveletcsoportonként külön előtaggal futnak. A passkey-regisztráció saját limitje továbbra is érvényes; az elkülönítést külön regressziós teszt ellenőrzi.

## Ellenőrzés és határ

Először 360 px-es megvalósítás és böngészős használat, csak utána 768/1024 px-es bővítés. 360/390/430/768/1280 px, világos/sötét és WCAG AA ellenőrzések. Külön memóriabeli backendtesztek és elkülönített `qa.sqlite` böngészőtesztek; saját adatokból nem készül minta. Fejlesztés előtti helyi konzisztens adatbázis-mentés: `database/before-phase7-20261015.sqlite` (nem publikált).

A jelenleg megadott kézi pénzügyi igények ezzel lefedhetők. Konkrét befektetési termékek kamat-/hozamszámítása új igény lesz, amikor lesz ilyen termék és ismert feltételek. A távoli mentés, valós szerver-visszaállítási próba, ütemezett napi/hónap végi push és tényleges azonnali keretjelzés iPhone-fogadása külön üzemeltetési/használati ellenőrzés marad. A fázis vége bemutató és megállás.

2026.10.15-i ellenőrzési eredmények:

- PHPUnit: **102 teszt, 1415 állítás**, memóriabeli adatbázissal; Pint és TypeScript/Vite build sikeres.
- Playwright: a teljes 32 forgatókönyv futtatása feltárt egy keresési regressziót (a találatok mellett más pénzmozgások is látszottak). Javítás után a keresés és mind a négy új mobil/asztali eset célzott újrafuttatása sikeres (5/5); az éves, eltérő hónaphoz tartozó kölcsön szerkesztésének kiegészített próbája külön is sikeres. 360/390/430/768/1280 px, világos/sötét, WCAG AA. A teljes készlet további 31 esete sikeres volt.
- Service worker: **4 Node-teszt sikeres**.
- Csomagolt Linux image: az alkalmazás www-data felhasználóként indul, a forrás számára nem írható; telepítőellenőrző összeg rendben.
- Külön HTTPS/FPM Docker-próba: védett első belépés, valódi kiadás, újraindítás utáni megőrzés, PWA, új cél/kölcsön tényleges mentése, export, titkosított mentés, működési állapot, virtuális hitelesítővel valós WebAuthn-regisztráció és belépés sikeres. Ez helyi telepítési próba, nem az AWS-szerver távoli tesztje.
- Saját helyi adatbázis mind a 16 korábbi pénzügyi táblája tartalmilag megegyezik a fázis előtti konzisztens pillanatképpel.
