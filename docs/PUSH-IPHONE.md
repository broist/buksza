# Buksza értesítések iPhone-on

## Jelenlegi állapot

A szerveroldali napi/hónap végi ütemezés, titkosított Web Push küldés, eszközregisztráció és próbaértesítés elkészült. A helyi HTTP/Wi-Fi-cím használható a pénzügyek rögzítésére, de erről az iPhone nem regisztrálható Web Push-ra. A tényleges telefonos fogadás még nincs ellenőrizve: az AWS-szerver nincs előkészítve.

A felhasználó megadta a telepítés célját: **Ubuntu, `buksza.duktor.hu`, `35.158.45.80`** (2026.10.15-i döntés). A DNS-t a felhasználó Cloudflare-ben kezeli; a telepítő IP-ellenőrzése miatt kezdetben DNS only rekord szükséges. A felhasználó egy saját maga által futtatható scriptet kért; elkészült a dist/buksza-telepito.sh egyfájlos Ubuntu-telepítő. Használat: [TELEPITES-UBUNTU.md](TELEPITES-UBUNTU.md). A szerverre történő tényleges telepítés és készülékpróba továbbra is hátravan.

## Szerver előkészítése

A végleges AWS-telepítésnél szükséges:

1. Domain vagy más, érvényes HTTPS-tanúsítvánnyal elérhető állandó cím; a Buksza ezen a címen nyíljon meg.
2. Az `app` és a `scheduler` azonos `APP_URL=https://buksza.duktor.hu`, `APP_KEY` és tartós adatbázis használatával fusson. A HTTPS proxy és biztonságos munkamenet konfigurációja a végleges AWS-telepítés része.
3. Migrációk után `php artisan buksza:push-keys`. A parancs csak hiányzó kulcsot hoz létre; a meglévőt nem cseréli le. A privát kulcs az adatbázisban titkosítva tárolt, ezért az alkalmazáskulcs megőrzése is szükséges költöztetéskor/mentéskor.
4. Folyamatosan futó `php artisan schedule:work` külön Docker-szolgáltatásban. A helyi `compose.yaml` már tartalmazza a `scheduler` szolgáltatást. A Beállítások oldalon látszik, érkezett-e friss ütemező-életjel.
5. A kiszolgáló elérje a böngésző értesítési szolgáltatóját HTTPS-en. A PWA ikonok, manifest és `/sw.js` ugyanazon az alkalmazáscímen legyenek elérhetők.

Az Ubuntu-telepítő külön PHP-FPM/Nginx/Caddy környezetet és napi mentést készít. A helyi fejlesztői kiszolgálás ettől külön marad; a tényleges AWS/HTTPS elérés a szerveren igazolandó.

## Telefonos beállítás és átvételi próba

1. iOS 16.4 vagy újabb rendszeren nyisd meg a HTTPS-es Bukszát Safariban.
2. Megosztás → **Hozzáadás a Főképernyőhöz**, majd a Buksza ikonról indítsd el.
3. Jelentkezz be; Továbbiak → Beállítások → Telefonos emlékeztetők → **Ezen a telefonon engedélyezem**. A rendszer engedélykérését fogadd el.
4. **Próbaértesítés**: a szolgáltató sikeres átvétele után külön ellenőrizd a telefonon a megjelenést és a jelzés megnyitását. A szolgáltatói siker önmagában nem igazolja a készüléken való fogadást.
5. Alapból minden nap **19:00 Europe/Budapest** a költésrögzítési jelzés. Hónap utolsó napján Anna jelentése is ugyanebben a jelzésben szerepel. Ha az adott napi Anna-jelentés már megvan, csak a napi költésrögzítési jelzés érkezik.

Az időpont és a két emlékeztető külön állítható. Az eszközönkénti Kikapcsolás leállítja a szerver küldéseit; az engedély később a telefon beállításaiban is visszavonható. Lejárt előfizetést a szerver kikapcsol. Újraregisztrálás az engedélyező gombbal lehetséges.

## Ellenőrzött küldési viselkedés

- Az ütemező percenként ellenőriz, és tízperces ablakban pótolhatja a rövid kiesés miatt elmaradt jelzést. Hosszabb leállás után régi emlékeztetőket nem küld.
- Eszköz/nap/emlékeztetőtípus szerint egy automatikus küldési kísérlet engedélyezett. Egyforma időpontnál a napi és Anna-jelzés egy üzenet. Sikertelen kísérlet is naplózott, nem ismételjük automatikusan az esetleg már átvett küldést; külön próbaértesítés kérhető.
- A telefon zárképernyőjén általános rögzítési szöveg jelenik meg, pénzügyi összegek nélkül.
- A service worker nem gyorsítótáraz pénzügyi oldalakat, és nem tárol offline rögzítési sort. Az app használatához internet kell.

Források: [Apple WebKit: Web Push for Web Apps on iOS and iPadOS](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/), [Apple Web Push dokumentáció](https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers), [MDN: PushManager.subscribe](https://developer.mozilla.org/en-US/docs/Web/API/PushManager/subscribe).
