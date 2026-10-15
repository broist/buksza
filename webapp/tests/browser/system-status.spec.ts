import {test,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.use({actionTimeout:15000});

test('operational status is readable on phones and refreshes without touching settings',async({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.getByRole('heading',{name:'Szia, Isti!',exact:true})).toBeVisible();
    await page.goto('/beallitasok');
    const section=page.getByRole('region',{name:'Működési állapot és mentések'});
    await expect(section).toBeVisible();
    await expect(section).toContainText('A mentés állapota nem ellenőrizhető');
    await expect(section).toContainText('Még nincs értesítésküldés rögzítve.');
    const savings=page.getByLabel(/^Havi családi megtakarítási cél/);
    await savings.fill('17');
    const request=page.waitForRequest(req=>req.headers()['x-inertia-partial-data']==='systemStatus');
    await section.getByRole('button',{name:'Állapot frissítése',exact:true}).click();
    await request;
    await expect(section.getByRole('button',{name:'Állapot frissítése',exact:true})).toBeEnabled();
    await expect(savings).toHaveValue('17');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    expect(axe.violations).toEqual([]);
    await page.screenshot({path:'../docs/screenshots/system-status-360.png',fullPage:true});
    for(const width of [390,430,768,1280]) {
        await page.setViewportSize({width,height:900});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        await expect(section).toBeVisible();
    }
    await section.screenshot({path:'../docs/screenshots/system-status-1280.png'});
});
