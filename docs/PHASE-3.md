# 3. fázis – esti rögzítés, rendszeres tételek és emlékeztetők

Állapot (2026.10.15.): **a funkciók és az AWS/HTTPS telepítés elkészültek; a tényleges iPhone-os fogadási próba még hátravan.** A felhasználó sikeres telepítést jelzett; a `https://buksza.duktor.hu/up` és `/belepes` HTTPS-en 200 választ ad. A 4. fázis később elkészült moduljait a [PHASE-4.md](PHASE-4.md) ismerteti.

## A felületen kipróbálható

- **Továbbiak → Esti rögzítés:** napválasztó, aznap rögzített pénzmozgások, napi megjegyzés; kiadás/bevétel/átvezetés. A „Mentés és következő” új, külön azonosítójú tételt indít, megtartva a dátumot, számlát és kategóriát. Az összegek gépelés közben továbbra is szóközzel tagoltak.
- **Továbbiak → Rendszeres tételek:** saját havi, heti vagy éves sablonok, ismétlődési időköz, kezdő-/záródátum, kikapcsolás és dátumozott változatok. A rövid hónapokban a 29–31-i esedékesség a hónap utolsó napjára kerül.
- Külön sablon készíthető hitelenként, Örs MÁK-befizetésére, Anna hozzájárulására, előfizetésre, hitelfedezeti átvezetésre vagy más támogatott pénzmozgásra. Hitelkiadásnál a szerződés szerinti havi törlesztő/esedékesség követhető; az eltérő hitelszerződések és verziók külön számítanak.
- Sablonból **csak kifejezett fizetés-jóváhagyással** lesz tényleges tétel. A dátum és összeg módosítható; a dupla jóváhagyás nem dupláz pénzt. Meglévő kézi tétel is kapcsolható, az eredeti dátum/összeg megőrzésével. Törlés, visszavonás és újbóli jóváhagyás ugyanahhoz az esedékességhez kapcsolódik.
- **Havi nézet → A havi rögzítés teljessége:** korábbi hónap ellenőrzése után teljesnek jelölhető. Pénzmozgás felvétele/módosítása/törlése/visszaállítása újra ellenőrizendővé teszi az érintett hónapot.
- **Tervezés → Havi tervjavaslat:** a kiválasztott hónapot megelőző három, teljesnek jelölt hónap kategóriaátlaga. Évhatáron is működik. Hiányos hónapnál nincs félrevezető nullás javaslat; tört forintnál a pontos érték látható, az egész forintos keretről a felhasználó dönt. Átvétel után külön tervmentés szükséges.
- **Beállítások → Telefonos emlékeztetők:** napi és Anna hónap végi jelzése, alapból mindkettő 19:00 Budapest szerint; külön ki-/bekapcsolás és időpont. HTTPS-en támogatott eszköz regisztrálható, próbaértesítés küldhető és az eszköz letiltható. A helyi HTTP felületen egyértelműen látszik, hogy még nem kapcsolható be az iPhone-push.

## PWA és szerveroldali küldés

A Buksza manifestet, saját PNG-ikonokat, Apple kezdőképernyő-ikont és értesítési service workert kapott. Nincs offline pénzügyi adattárolás vagy rögzítési sor. Az iPhone-os telepítés/fogadás feltételeit és a szerver előkészítését a [PUSH-IPHONE.md](PUSH-IPHONE.md) tartalmazza.

A Docker `scheduler` szolgáltatás percenként ellenőriz; az automatikus kísérleteket eszköz/nap/jelzéstípus szerint egyedi adatbázis-bejegyzés védi az ismétléstől. Azonos időpontnál a két jelzés egy értesítésben érkezik. Anna már rögzített utolsó napi jelentése után nem küldünk új Anna-jelzést. Tízperces rövid kiesés pótolható; régi napokra nincs utólagos küldés.

A Web Push `aes128gcm` titkosítást és VAPID-aláírást használ. A privát kulcs és eszközelőfizetés titkosítva tárolt; nem kerül a felületi adatokba vagy hibás űrlap visszatöltésébe. Sikertelen/lejáró küldés állapota látszik; lejárt eszköz letiltott. A zárképernyős szöveg nem tartalmaz pénzügyi összeget.

## Ellenőrzések

- **53 PHPUnit teszt, 761 állítás sikeres.** A korábbi számítások és számla-/hiteltesztek mellett sablon-esedékesség, rövid/szökőéves hónap, heti/éves intervallum, hitelenkénti szerződéskövetés, változatok/lezárás, korai fizetés megőrzése, Örs egyszeri elszámolása, dupla jóváhagyás, meglévő tétel kapcsolása, pontos háromhavi javaslat és hónapteljesség érvénytelenítése.
- Napi időzóna, téli/nyári időváltás napjai, 28/29/30/31 napos hónap vége, kombinált/külön emlékeztetők, duplikációvédelem, hiba/lejárat, eszközjogosultság, szolgáltatói címellenőrzés és kulcsmegőrzés tesztelve. A valódi Web Push könyvtár titkosított kérését és 201/410 válaszkezelését hálózat nélküli HTTP-klienssel is ellenőriztük.
- **18 teljes Playwright teszt, majd további 1 tervjavaslat-teszt sikeres:** korábbi funkciók, két egymás utáni mentés/újratöltés, sablonfelvétel és módosított tényleges Örs-befizetés, emlékeztető-beállítások, manifest/ikonok, helyi HTTP-s telefonos „+”, három korábbi hónap teljesnek jelölése és az egész forintos havi javaslat átvétele. 360/390/430/768/1280 px-en nincs nem szándékos vízszintes túlcsordulás. Az új 360 px-es képernyők ellenőrzése után készült az asztali bővítés.
- Axe WCAG AA ellenőrzések sikeresek; az új sablonűrlapnál is. TypeScript/Vite build és Pint formázás sikeres.
- Lighthouse mobil: **98 teljesítmény, 100 hozzáférhetőség**, FCP 1,9 s, LCP 2,2 s, TBT 60 ms, CLS 0. Jelentés: [lighthouse-phase3-mobile.html](checks/lighthouse-phase3-mobile.html).
- Képernyőképek: `screenshots/phase3-esti-rogzites-360.png`, `phase3-rendszeres-360.png`, `phase3-sablon-360.png`, valamint a teljes képernyőteszt 1280 px-es képei. Minden képen kizárólag elkülönített QA-adat szerepel.
- A saját adatbázis migrációja előtt mentés készült: `webapp/database/before-phase3-20261014.sqlite`. A régi pénzügyi, számla-, hitel-, kategória-, terv- és felhasználói adatok megőrzését a `webapp/scripts/check-phase3-preservation.php` összehasonlítással igazolta. PHPUnit: `:memory:`, böngésző: `qa.sqlite`.

## Ami még nem ellenőrzött

Az iPhone-os fogadást, az AWS-en folyamatosan működő ütemező életjelét és az első ütemezett mentést még ellenőrizni kell. Az éles telepítés már működik **Ubuntu 24.04, buksza.duktor.hu**, `/opt/buksza` alatt, a meglévő Nginx mögött. A készülékpróba nélkül a telefonos értesítés SPEC-ben szereplő elfogadási pontja még nincs kipipálva. A telepítési ellenőrzések óta a backendtesztek száma 54 (797 állítás); a csomagolt Linux-forrásból épített képekkel HTTPS-es belépés, tételrögzítés, adat-/munkamenet-megőrzés és PWA-próba is sikeres.
