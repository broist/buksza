# 4. fázis – Zsebpénzek, Örs babakötvénye és Éves összesítő

Állapot: a fejlesztési funkciók elkészültek. A felhasználó 2026.10.15-én jóváhagyta a folytatást. Az új szerverkiadást a Gitből letöltött telepítővel kell feltenni.

## Elkészült

- **Továbbiak → Zsebpénzek:** 12 hónap, személyenkénti és éves összegek. A Revolut/zsebpénz-módú feltöltés már elszámolt kiadását használja; a belső `kp` költés külön összeg, nem új családi kiadás. Anna vagy más nyilvántartott személy közvetlen zsebpénze a meglévő kiadásrögzítővel felvehető. Számlaegyenleg és állapotdátum is látszik.
- **Továbbiak → Örs babakötvényei:** több külön befektetés, MÁK-számla, sorozat, kedvezményezett, nyilvántartási kezdőnap és korábbi befizetett tőke. A kezdő tőkébe már külön rögzített pénzmozgást nem szabad ismét beírni.
- **Dátumozott havi befizetési terv:** egy megadott hónaptól új havi összeg érvényes; a régebbi hónapok terve nem íródik át. A 0 Ft megszünteti a további havi tervet. Havi/éves terv, tényleges befizetés és eltérés külön látszik.
- **Meglévő befizetés hozzárendelése:** megfelelő MÁK-célú, valódi `Örs befizetése` kapcsolható egy babakötvényhez. Többszöri mentés sem hoz létre új pénzmozgást. A még nem kapcsolt befizetés az éves összegben már szerepel. A kapcsolat bontható; a kapcsolt tétel típusát/számláját nem lehet észrevétlenül másra változtatni.
- **MÁK-értékjelentés:** kézzel megadott aktuális érték és dátum, előzményekkel. Ez nem bevétel, befizetés vagy családi megtakarítás. Nincs kitalált kamatszámítás. Korábbi hónapban csak az addig ismert érték látszik; a hiányzó érték nem nulla, a még nem indult befektetés tőkéje nem jelenik meg múltbeli vagyonként.
- **Éves összesítő:** bevétel/kiadás/CF, terv szerinti záró maradék, családi cél/tényleges félretétel, felhasználható bevétel, Örs külön befizetése. Havi/napi átlagok, Munka/Egyéb forrásbontás, 12 havi összehasonlító diagram, kategória-terv–tény és hitelenkénti törlesztések. A közös `Calculator`/`Exact` számításait használja; nincs automatikus kerekítés.
- **Keresés az éves tételekben:** dátum/összeg/kategória/megjegyzés, típus, személy, számla és üzleti jelölés; lapozható tétellista, meglévő szerkesztés/törlés/visszavonás. A szűrés nem módosítja a közös családi keretet és a teljes éves számokat.
- **Teljesebb Áttekintés:** teljesített/még rögzítendő rendszeres tételek és Örs befektetési értékjelentése; közvetlen továbblépés a részletekre.
- **CSV/Excel-export:** a teljes kiválasztott év. Hat Excel-munkalap: Összegzés, Hónapok, Kategóriák, Tételek, Zsebpénzek, Örs babakötvényei. A CSV UTF-8, pontosvesszős; az export API munkalaponként is kérhető. A pontos törtérték szöveg marad. A szöveges cellákból nem hajtunk végre képleteket; CSV-ben a képletkezdő szöveget védjük. A nyers tételek külön jelzik az átvezetést, belső költést és díjat.
- **Beállítások → Pénzügyek mentése és visszaállítása:** titkosított `.buksza` fájl a pénzügyekkel, tervekkel, előzményekkel és törölt, visszavonható tételekkel. Visszaállítás előtt jelenlegi jelszó és külön jelölőnégyzet szükséges. Automatikus védőmentés készül; ha ez nem írható ki, a visszaállítás el sem indul. Sérült/idegen fájl vagy hibás hivatkozás esetén nincs részleges visszaállítás. A belépés, passkeyk és értesítési eszközök nem íródnak felül. A legutóbbi visszaállítás előtti mentés külön letölthető.
- **Beállítások → Passkey-belépés:** saját névvel új passkey, jelenlegi jelszó megerősítésével; a belépési oldalon „Belépés passkeyjel”. Resident credential és készülékes felhasználóellenőrzés kötelező. Egyszer használható, rövid életű kihívás, pontos origin/RP-, aláírás- és felhasználóellenőrzés, próbálkozáskorlát, törlés. A jelszó tartalék marad; biometrikus adatot a Buksza nem kap.

## Kipróbálás

1. Frissítsd a szervert a [telepítési útmutató](TELEPITES-UBUNTU.md) Git-frissítési lépéseivel.
2. Telefonon Továbbiak → Zsebpénzek / Örs babakötvényei. Add meg a saját MÁK-adataidat; a meglévő befizetést rendeld hozzá, újra rögzíteni nem kell.
3. Éves → válassz évet; nézd meg a havi/kategória bontást és az Excel-exportot.
4. Beállítások → tölts le pénzügyi mentést. Visszaállítást valódi visszaállítási szándékkal indíts: a mentés időpontja utáni pénzügyi változások helyére a mentett állapot kerül.
5. Beállítások → Passkey-belépés → add meg a jelenlegi jelszavadat, és hozz létre kulcsot az eszközödön. A következő belépésnél használható a passkey-gomb.

A pénzügyi mentés ugyanazon alkalmazáskulcshoz és pénzügyi adatmodellhez tartozik; az `/opt/buksza/app.env` megőrzése szükséges. Feltöltés: legfeljebb 10 MB; kibontott pénzügyi adat: legfeljebb 20 MB. A napi szervermentés teljes SQLite-adatbázist és konfigurációt ment; az alkalmazásból visszaállítható pénzügyi mentés ettől külön szolgáltatás.

## Ellenőrzések

- **66 backendteszt, 937 állítás sikeres.** Teljes regresszió, zsebpénz/kp, több babakötvény, tervelőzmény, nem kapcsolt befizetés, hiányzó és múltbeli érték, dupla kapcsolás, hibás számla/típus/dátum, export és pontos CF, titkosítás, jelszó/hozzáférés, sérülés/hibás hivatkozás miatti teljes visszagörgetés és a védőmentés sikertelensége.
- **24 teljes Playwright teszt sikeres.** Először az új 360 px-es képernyők és Axe WCAG AA ellenőrzése, utána asztali bővítés; teljes alkalmazás 360/390/430/768/1280 px-en, túlcsordulás nélkül. Babakötvény-felvétel, terv és érték megadása, exportletöltés, visszaállítás és keresés is valódi böngészőből ellenőrizve.
- **WebAuthn Chromium virtuális hitelesítővel:** regisztráció, resident credential, ellenőrzött aláírt belépés, ismételt válasz elutasítása, jelszóval védett törlés. Nem csak az API-választ mockoltuk.
- **Független Excel-ellenőrzés:** az openpyxl megnyitotta mind a hat munkalapot; a havi bevétel/kiadás/pontos CF egyezik az éves összeggel, nincs végrehajtható képlet a szövegekből.
- **Lighthouse mobil: 96 teljesítmény, 100 hozzáférhetőség.** FCP 2,0 s, LCP 2,4 s, TBT 60 ms, CLS 0. TypeScript/Vite build és PHP Pint sikeres.
- A csomagolt telepítő forrásából Linuxban, szigorú jogosultságokkal is elkészül a PHP-FPM/Nginx kép. Az Excelhez szükséges ZIP-támogatás a képek része.
- **Éles beállítású, elkülönített HTTPS-integráció:** PHP-FPM/Nginx, privát első indítás, tényleges tételrögzítés, újraindítás utáni adat- és munkamenet-megőrzés, meglévő Push-kulcs és PWA, a 4. fázis oldalai, Excel/titkosított mentés és valódi passkey-regisztráció/belépés sikeres. Ez helyi, saját localhost-tanúsítványos QA, nem a felhasználó éles adatain végzett teszt.
- Saját adatbázismentés: `database/before-phase4-20261015.sqlite`. A megőrzési ellenőrzés minden korábbi felhasználói, pénzügyi, terv-, hitel-, rendszeres tétel- és értesítési adat eredeti mezőit összehasonlította. Saját adatbázis nem lett alaphelyzetbe állítva; PHPUnit `:memory:`, böngészőteszt `qa.sqlite`.

## Készüléken és éles szerveren még ellenőrizendő

- iPhone-on az ütemezett napi/hónap végi jelzés tényleges fogadása. Az értesítés megérkezését a felhasználó 2026.10.15-én már képernyőképpel igazolta.
- Az éles ütemező életjele és az első automatikus napi szervermentés.
- Face ID/passkey valódi iPhone-on; a protokollt virtuális készülékkel teszteltük.
- A 3 kezelési lépéses / 10 másodperces gyors rögzítés tényleges egykezes kényelme a saját telefonon. Az automatikus teszt nem helyettesíti ezt a használati próbát.

A 4. fázis végén megállunk. Új funkció, általános befektetési kamatszámítás vagy Kintlévőségeim új jóváhagyással indul.

Passkey-könyvtár: [lbuchs/WebAuthn](https://github.com/lbuchs/WebAuthn).
