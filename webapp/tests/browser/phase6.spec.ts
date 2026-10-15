import {test,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.use({actionTimeout:15000,reducedMotion:'reduce'});
test.beforeEach(async({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.getByRole('heading',{name:'Szia, Isti!',exact:true})).toBeVisible();
});

test('phase six phone routes and warning controls fit 360px and meet WCAG AA',async({page})=>{
    for(const route of ['/?year=2026&month=10','/uzleti-riport?from=2026-01-01&to=2026-12-31','/rendszeres?year=2026&month=8','/beallitasok']) {
        await page.goto(route);
        await expect(page.locator('h1')).toBeVisible();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
        const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
        expect(axe.violations).toEqual([]);
    }
    await expect(page.getByLabel('Keretek és összegváltozások összefoglalója az esti jelzésben')).toBeVisible();
    await page.getByRole('button',{name:'Továbbiak',exact:true}).click();
    await page.locator('dialog').getByRole('link',{name:'Üzleti / magán riport',exact:true}).click();
    await expect(page.locator('h1')).toHaveText('Üzleti / magán riport');
    await page.screenshot({path:'../docs/screenshots/phase6-business-360.png',fullPage:true});
    for(const width of [390,430,768,1280]) {
        await page.setViewportSize({width,height:900});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    await page.screenshot({path:'../docs/screenshots/phase6-business-1280.png',fullPage:true});
});

test('phone records a business payment, shows a budget warning and exports the exact report',async({page,context})=>{
    const marker=`Üzleti riport QA ${Date.now()}`;
    await page.goto('/tervezes?year=2024&month=5');
    await page.getByLabel('élelmiszer kerete',{exact:true}).fill('1000');
    await page.getByRole('button',{name:'Havi terv mentése',exact:true}).click();
    await expect(page.getByRole('status')).toContainText('mentve');
    await page.goto('/honap?year=2024&month=5');
    await page.getByRole('button',{name:'Új tétel',exact:true}).click();
    await page.getByLabel('Összeg',{exact:true}).fill('800');
    await page.getByRole('button',{name:'élelmiszer',exact:true}).click();
    await page.getByRole('button',{name:'Dátum, megjegyzés és részletek',exact:true}).click();
    await page.getByLabel('Dátum',{exact:true}).fill('2024-05-15');
    await page.getByLabel('Megjegyzés',{exact:true}).fill(marker);
    await page.getByLabel('Üzleti tétel',{exact:true}).check();
    await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    const warnings=page.getByRole('region',{name:'Havi figyelmeztetések',exact:true});
    await expect(warnings).toContainText('legalább 80%-a elfogyott');
    await page.goto('/uzleti-riport?from=2024-05-01&to=2024-05-31&group=business');
    await expect(page.locator('.business-entry').filter({hasText:marker})).toHaveCount(1);
    await expect(page.getByRole('region',{name:'Üzleti időszaki összesítő'})).toContainText('800');
    await page.getByRole('button',{name:'Magán',exact:true}).click();
    await expect(page.locator('.business-entry').filter({hasText:marker})).toHaveCount(0);
    await page.getByRole('button',{name:'Üzleti',exact:true}).click();
    const [download]=await Promise.all([page.waitForEvent('download'),page.getByRole('link',{name:'Riport · Excel',exact:true}).click()]);
    expect(download.suggestedFilename()).toBe('buksza-riport-2024-05-01-2024-05-31.xlsx');
    const csv=await context.request.get('/uzleti-riport/export?from=2024-05-01&to=2024-05-31&group=business&format=csv');
    expect(await csv.text()).toContain(marker);
    const entry=page.locator('.business-entry').filter({hasText:marker});
    await entry.getByRole('button',{name:'Tétel szerkesztése',exact:true}).click();
    await page.getByLabel('Üzleti tétel',{exact:true}).uncheck();
    await page.getByRole('button',{name:'Módosítás mentése',exact:true}).click();
    await expect(entry).toHaveCount(0);
    await page.goto('/honap?year=2024&month=5');
    await page.locator('.day-row').filter({has:page.locator('.day-number').filter({hasText:/^15$/})}).click();
    page.once('dialog',dialog=>dialog.accept());
    await page.locator('.transaction-row').filter({hasText:marker}).getByRole('button',{name:'Tétel törlése',exact:true}).click();
    await expect(warnings).not.toContainText('legalább 80%-a elfogyott');
});

test('phone shows a confirmed recurring amount change without changing its template',async({page})=>{
    const marker=`Előfizetés változás QA ${Date.now()}`;
    await page.goto('/rendszeres?year=2026&month=8');
    await page.getByRole('button',{name:'Új sablon',exact:true}).click();
    await page.getByLabel('Sablon neve',{exact:true}).fill(marker);
    await page.getByLabel('Sablon összege (Ft)',{exact:true}).fill('1000');
    await page.getByLabel('Esedékesség napja a hónapban',{exact:true}).fill('15');
    await page.getByLabel('Sablon kezdete',{exact:true}).fill('2026-01-01');
    await page.getByLabel('Megjegyzés',{exact:true}).fill(marker);
    await page.getByRole('button',{name:'Sablon mentése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    const card=page.locator('.recurring-card').filter({hasText:marker});
    await card.getByRole('button',{name:'Fizetés jóváhagyása',exact:true}).click();
    await page.getByLabel('Tényleges összeg (Ft)',{exact:true}).fill('1200');
    await page.getByLabel('Tényleges fizetés dátuma',{exact:true}).fill('2026-08-15');
    await page.getByRole('button',{name:'Tényleges fizetés rögzítése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    const change=page.locator('.insight-item').filter({hasText:marker});
    await expect(change).toContainText('1 200');
    await expect(change).toContainText('1 000');
    await page.reload();await expect(change).toContainText('Növekedés');
    await page.goto('/esti-rogzites?date=2026-08-15');
    const row=page.locator('.transaction-row').filter({hasText:marker});
    page.once('dialog',dialog=>dialog.accept());await row.getByRole('button',{name:'Tétel törlése',exact:true}).click();
    await page.goto('/rendszeres?year=2026&month=8');
    await expect(page.locator('.insight-item').filter({hasText:marker})).toHaveCount(0);
});
