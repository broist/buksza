# 2. fázis – Számlák és Hiteleim

2026. október 14. A felhasználó „folytasd a többi fejlesztést” jóváhagyásával.

## Elkészült

- **Számlák:** CIB főszámla, Anna havi jelentése, Raiffeisen, Revolut, CIB alszámla, SZÉP, egészségpénztár és Örs MÁK-számlája. Szerkeszthető név, tulajdonos, szolgáltató, szerep, nyitóegyenleg és annak dátuma. Új számla, családi megtakarítási számla, archiválás és alszámla–főszámla kapcsolat. A korábbi főszámla adatai megmaradtak; új pénzügyi kezdőösszegek és hitelek nem lettek kitalálva.
- **Egyenlegek:** külön banki, kötött, családi megtakarítási és gyermekmegtakarítási összesítők, számlánként havi be-/kimenő forgalommal. A nyitódátum előtti állás nem nulla, hanem ismeretlen. Örs egyenlege itt a nyitóösszeg és rögzített befizetések nyilvántartása; automatikus hozam nincs.
- **Átvezetés:** egyszer rögzítendő forrás, cél, összeg és dátum. Mindkét egyenleg változik; a családi bevétel/kiadás nem duplázódik. Az opcionális átutalási díj a forrás banki költségeként külön számít, és az egész mozgással együtt szerkeszthető/törölhető/visszavonható.
- **Elszámolási mód:** részletes vásárlásrögzítés, összevont feltöltés és zsebpénz. Raiffeisen alapból részletes; Revolut feltöltése egyszer zsebpénzkiadás, belső költése `kp` összegkövetés. A módváltás dátumozott; régi tételeket nem osztályoz át automatikusan. Összevont/zsebpénzes forrásról általános átvezetés helyett jelenleg belső összegkövetés érhető el; a forrásoldali különleges visszatérítés elszámolását nem találgatjuk.
- **SZÉP/egészségpénztár:** a munkáltatói SZÉP-bevétel Munka forrás, a saját egészségpénztári befizetés átvezetés. A valódi vásárlás a kiválasztott kategória kiadása. A kötött egyenlegek külön látszanak.
- **Megtakarítás:** családi félretétel és visszavétel választható megtakarítási számlával; Örs befizetése MÁK-célhoz kapcsolódik. A családi cél, tényleges félretétel és Örs külön CF-levonása nem számít kétszer.
- **Anna:** csak a főszámlás hozzájárulás bevétel. Külön hónap utolsó napi egyenlegjelentés, megjegyzés, javítás, havi előzmények, dátumozott utolsó ismert egyenleg és hiányzó/esedékes havi jelentés jelzése. A pillanatkép nem pénzmozgás.
- **Hiteleim:** korlátlan számú hitel/áruhitel, név, hitelező, eredeti összeg, kézzel megadott fennálló tartozás és állapotdátuma, éves kamat, szerződéses kamatleírás, havi törlesztő, esedékességi nap, kezdő/vég/lezárási dátum, megjegyzés. A kamat- és törlesztőfeltételek dátumozott előzménye megmarad. A havi esedékesség napján érvényes feltétel számít; későbbi törlesztőváltozás nem írja át a korábbi esedékességet. Az esedékességi nap megváltoztatása hónap elejétől adható meg; rövid hónapban a hónap utolsó napjára igazodik.
- **Törlesztés és fedezet:** a valódi hitelkiadás konkrét hitelhez kapcsolható; havi és éves összesítés, fizetett/még rögzítendő összeg, havi tétel- és fedezetátvezetési előzmény. Az alszámlás fedezet és többlet nem törlesztés. A puszta törlesztési összeg nem csökkenti automatikusan a nyilvántartott tőkét.
- **Szűrés:** Áttekintés/Hónap/Számlák számla és személy szerint. A napi lista, költési részösszeg és diagram szűrt; a családi CF, megtakarítási cél és keretek egyértelműen közösek maradnak. A havi táblázat kezeli a feltöltésből elszámolt kiadást és banki díjat is.

## Kipróbálás

Frissítsd a böngészőt. Telefonon **Továbbiak → Számlák / Hiteleim**, asztalin az oldalsávból. Számlák: `/szamlak`; Hiteleim: `/hitelek`.

1. Add meg a számlák nyitóegyenlegét és pontos dátumát.
2. Vedd fel a két meglévő hiteledet a szerződés szerinti kezdődátummal és külön adatokkal.
3. A **+** panelen választható Átvezetés; a hitelkiadásnál a konkrét hitel. A hitelkártya Törlesztés gombja ezt előre kiválasztja.
4. Korábbi hitelkiadást a havi tétel szerkesztésében rendelj hitelhez, ne rögzítsd újra. A még hozzárendelés nélküli havi hitelkiadás a Hiteleim oldalon látszik.
5. Anna jelentését a Számlák oldalon, a megfelelő hónap utolsó napjára add meg. A főszámlás hozzájárulás ettől külön bevétel.

## Ellenőrzések és adatmegőrzés

- **38 PHPUnit teszt, 554 állítás:** a korábbi számítások/CRUD mellett átvezetés két egyenlege, egyszeri díj és vásárlás, három hitel, alszámlás oda-vissza fedezet, SZÉP/egészségpénztár, Anna pillanatképe és hozzájárulása, zsebpénz/összevont mód, módváltási előzmény, Örs és családi megtakarítás, kamat-/törlesztőverzió, szökőéves esedékesség, lezárás, archiválás, számla-/személyszűrés és hibás kapcsolatok.
- **14 teljes Playwright teszt sikeres**, az utolsó esedékességi/űrlap-pontosítás után a kapcsolódó 4 teszt ismét sikeres: tételmentés helyi HTTP-n, számlafelvétel, Anna-jelentés, hitelfelvétel, törlesztés, kapcsolt fedezet és visszavonás, `kp`, Örs-befizetés, szűrés. Minden oldal ellenőrizve 360/390/430/768/1280 px szélességen; előbb a 360 px-es új képernyők, utána asztali bővítés.
- **Axe WCAG AA:** világos és sötét alapnézet, új oldalak, számla- és hitelszerkesztő; hibajelzés nélkül.
- **Lighthouse mobil: 98 teljesítmény, 100 hozzáférhetőség.** FCP 1,8 s; LCP 2,0 s; TBT 40 ms; CLS 0. Jelentés: [lighthouse-phase2-mobile.html](checks/lighthouse-phase2-mobile.html).
- TypeScript/Vite build és PHP Pint formázás sikeres. Képek: `screenshots/szamlak-360.png`, `hitelek-1280.png`, `phase2-hitel-adatok-360.png`; kizárólag elkülönített QA-mintaadatokkal.
- A saját adatbázis frissítése előtt SQLite-mentés készült: `webapp/database/before-phase2-20261014.sqlite`. A korábbi felhasználói, főszámla-, kategória-, terv-, beállítás-, tétel- és megjegyzésadatok a mentéssel összehasonlítva változatlanok. Ellenőrző CLI: `php scripts/check-phase2-preservation.php`. Ezt csak a fejlesztési mentés változatlan adataival való összevetésére használjuk; későbbi saját szerkesztések természetesen eltérhetnek.
- PHPUnit csak `:memory:`, böngészős QA csak `qa.sqlite`; a saját adatbázis nem lett alaphelyzetbe állítva.

## Megállás a fázis végén

A **3. fázis** következik: esti rögzítési folyamat, rendszeres sablonok, hitelenkénti és Örs-befizetési sablon, PWA és napi 19:00-s/hónap végi telefonos Web Push. A mostani esedékességi jelzés a felületen látszik, aktív telefonos push még nincs. AWS/HTTPS, teljes éves/befektetési/zsebpénz-riport és mentés/visszaállítás később készül.

A 2. fázis lezárult; a 3. fázis a felhasználó következő jóváhagyásával indul.
