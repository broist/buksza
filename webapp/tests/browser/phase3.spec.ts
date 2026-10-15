import {test,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.use({actionTimeout:15000,reducedMotion:'reduce'});

test.beforeEach(async({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.locator('h1')).toHaveText('Szia, Isti!');
});

test('phone evening saves successive actual payments with distinct IDs and selected date',async({page})=>{
    const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto('/esti-rogzites?date=2026-08-30');
    await page.getByRole('button',{name:'Kiadás',exact:true}).click();
    await page.getByLabel('Összeg',{exact:true}).fill('1234');
    await page.getByRole('button',{name:/^élelmiszer$/i}).click();
    await page.getByRole('button',{name:'Mentés és következő',exact:true}).click();
    await expect(page.getByLabel('Összeg',{exact:true})).toHaveValue('');
    await expect(page.locator('dialog')).toBeVisible();
    await page.getByLabel('Összeg',{exact:true}).fill('5678');
    await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    await expect(page.locator('.transaction-row').filter({hasText:'1 234'})).toHaveCount(1);
    await expect(page.locator('.transaction-row').filter({hasText:'5 678'})).toHaveCount(1);
    await page.reload();
    await expect(page.getByLabel('Rögzítés napja')).toHaveValue('2026-08-30');
    await expect(page.locator('.transaction-row')).toHaveCount(2);
    expect(errors).toEqual([]);
    page.on('dialog',d=>d.accept());
    for(let i=0;i<2;i++){await page.getByRole('button',{name:'Tétel törlése',exact:true}).first().click();await expect(page.locator('.transaction-row')).toHaveCount(1-i);}
});

test('phone creates child template, confirms actual changed amount once and preserves it after reload',async({page})=>{
    const marker=`Örs havi sablon – QA ${Date.now()}`;
    await page.goto('/rendszeres?year=2026&month=8');
    await page.getByRole('button',{name:'Új sablon',exact:true}).click();
    await page.getByLabel('Sablon neve',{exact:true}).fill(marker);
    await page.getByLabel('Pénzmozgás típusa',{exact:true}).selectOption('child_saving');
    await page.getByLabel('Sablon összege (Ft)',{exact:true}).fill('15000');
    await page.getByLabel('Esedékesség napja a hónapban',{exact:true}).fill('10');
    await page.getByLabel('Sablon kezdete',{exact:true}).fill('2026-01-01');
    await page.getByRole('button',{name:'Sablon mentése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    const card=page.locator('.recurring-card').filter({hasText:marker});
    await expect(card).toContainText('15 000');
    await card.getByRole('button',{name:'Fizetés jóváhagyása',exact:true}).click();
    await page.getByLabel('Tényleges összeg (Ft)',{exact:true}).fill('16000');
    await page.getByLabel('Tényleges fizetés dátuma',{exact:true}).fill('2026-08-12');
    await page.getByRole('button',{name:'Tényleges fizetés rögzítése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    await expect(card).toContainText('TELJESÍTVE');
    await expect(card).toContainText('16 000');
    await page.reload();await expect(card).toContainText('TELJESÍTVE');
    await expect(card).toContainText('16 000');
    await page.goto('/esti-rogzites?date=2026-08-12');
    const actual=page.locator('.transaction-row').filter({hasText:'16 000'});
    await expect(actual).toHaveCount(1);
    page.on('dialog',d=>d.accept());await actual.getByRole('button',{name:'Tétel törlése',exact:true}).click();
    await expect(actual).toHaveCount(0);
});

test('phone phase3 routes, reminder controls and template editor fit 360px and meet WCAG AA',async({page})=>{
    for(const route of ['/rendszeres','/esti-rogzites','/beallitasok','/tervezes']){
        await page.goto(route);await expect(page.locator('h1')).toBeVisible();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
        const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(axe.violations).toEqual([]);
        await page.screenshot({path:`../docs/screenshots/phase3-${route.slice(1)}-360.png`,fullPage:true});
    }
    await page.goto('/beallitasok');
    await expect(page.getByRole('button',{name:'Ezen a telefonon engedélyezem',exact:true})).toBeDisabled();
    await page.getByLabel('Napi jelzés időpontja',{exact:true}).fill('19:00');
    await page.getByRole('button',{name:'Emlékeztetők mentése',exact:true}).click();
    await expect(page.getByRole('status')).toContainText('Emlékeztetők elmentve');
    await page.goto('/rendszeres');await page.getByRole('button',{name:'Új sablon',exact:true}).click();
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(axe.violations).toEqual([]);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    await page.screenshot({path:'../docs/screenshots/phase3-sablon-360.png',fullPage:true});
});

test('PWA manifest icons and network-only worker are served',async({page,request})=>{
    const manifest=await (await request.get('/manifest.webmanifest')).json();
    expect(manifest.name).toBe('Buksza');expect(manifest.display).toBe('standalone');
    for(const icon of manifest.icons){const response=await request.get(icon.src);expect(response.ok()).toBeTruthy();expect(response.headers()['content-type']).toContain('image/png');}
    const worker=await (await request.get('/sw.js')).text();expect(worker).toContain("addEventListener('push'");expect(worker).not.toContain('caches.open');
    await expect(page.locator('link[rel="apple-touch-icon"]')).toHaveAttribute('href','/icons/apple-touch-icon.png');
});

test('phone explicitly completes three past months and accepts an exact monthly plan suggestion',async({page})=>{
    for(const month of [1,2,3]){
        await page.goto(`/honap?year=2026&month=${month}`);
        await page.getByRole('button',{name:'A hónap rögzítése teljes',exact:true}).click();
        await expect(page.getByRole('button',{name:'Még nincs minden rögzítve',exact:true})).toBeVisible();
    }
    await page.goto('/tervezes?year=2026&month=4');
    await page.getByText('Havi tervjavaslat · az előző három hónap átlaga',{exact:true}).click();
    const suggestion=page.locator('.suggestion-row').filter({hasText:'élelmiszer'}).first();
    await expect(suggestion).toContainText('0 Ft');
    await page.getByLabel('élelmiszer kerete',{exact:true}).fill('9999');
    await suggestion.getByRole('button',{name:'Havi tervhez',exact:true}).click();
    await expect(page.getByLabel('élelmiszer kerete',{exact:true})).toHaveValue('0');
    await expect(page.locator('.plan-suggestion')).toContainText('2026-01, 2026-02, 2026-03');
});
