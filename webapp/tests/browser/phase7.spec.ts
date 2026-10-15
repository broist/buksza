import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.use({actionTimeout:15000,reducedMotion:'reduce'});
test.beforeEach(async({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.getByRole('heading',{name:'Szia, Isti!',exact:true})).toBeVisible();
});

test('phase seven phone navigation and empty pages fit 360px and WCAG AA',async({page})=>{
    await page.getByRole('button',{name:'Továbbiak',exact:true}).click();
    await page.locator('dialog').getByRole('link',{name:'Megtakarítási célok',exact:true}).click();
    for(const path of ['/megtakaritasi-celok','/kintlevosegeim']){
        await page.goto(path+'?year=2026&month=10');
        await expect(page.locator('h1')).toHaveText(path.includes('celok')?'Megtakarítási célok':'Kintlévőségeim');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
        expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
        await page.screenshot({path:`../docs/screenshots/phase7-${path.slice(1)}-empty-360.png`,fullPage:true});
    }
});

test('phone creates a loan records partial repayment edits it and retains settled history',async({page})=>{
    const name=`Kölcsön QA ${Date.now()}`;
    await page.goto('/kintlevosegeim?year=2026&month=10');
    await page.getByRole('button',{name:'Új személyes kölcsön',exact:true}).click();
    await page.getByLabel('Partner / kölcsön neve',{exact:true}).fill(name);
    await page.getByLabel('Nyilvántartás kezdete',{exact:true}).fill('2026-01-01');
    await page.getByRole('button',{name:'Nyilvántartás mentése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    const card=page.getByRole('region',{name,exact:true});
    await card.getByRole('button',{name:'További kölcsön',exact:true}).click();
    await page.getByLabel('Összeg (Ft)',{exact:true}).fill('100000');
    await expect(page.getByLabel('Összeg (Ft)',{exact:true})).toHaveValue('100 000');
    await page.getByLabel('Dátum',{exact:true}).fill('2026-10-01');
    await page.getByRole('button',{name:'Pénzmozgás mentése',exact:true}).click();
    await expect(card.locator('.position-balance')).toContainText('100 000');
    await card.getByRole('button',{name:'Részben / teljesen visszafizetve',exact:true}).click();
    await page.getByLabel('Összeg (Ft)',{exact:true}).fill('25000');
    await page.getByLabel('Dátum',{exact:true}).fill('2026-10-02');
    await page.getByRole('button',{name:'Pénzmozgás mentése',exact:true}).click();
    await expect(card.locator('.position-balance')).toContainText('75 000');
    await card.locator('summary').click();
    await card.locator('.position-entry').filter({hasText:'Adott kölcsön visszakapott tőkéje'}).getByRole('button',{name:'Mozgás szerkesztése',exact:true}).click();
    await page.getByLabel('Összeg (Ft)',{exact:true}).fill('100000');
    await page.getByRole('button',{name:'Pénzmozgás mentése',exact:true}).click();
    await expect(card.locator('.position-balance')).toContainText('Rendezve');
    await expect(card.locator('.position-entry')).toHaveCount(2);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    await page.screenshot({path:'../docs/screenshots/phase7-loan-history-360.png',fullPage:true});
    // The annual ledger can edit October even when the month context is January.
    await page.goto('/eves?year=2026&month=1');
    await page.getByLabel('Keresés',{exact:true}).fill(name);
    await page.getByRole('combobox',{name:'Tételtípus',exact:true}).selectOption('loan_given');
    await expect(page.locator('.transaction-row')).toHaveCount(1);
    await page.getByRole('button',{name:'Tétel szerkesztése',exact:true}).click();
    await page.getByLabel('Összeg (Ft)',{exact:true}).fill('120000');
    await page.getByRole('button',{name:'Pénzmozgás mentése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    await page.goto('/kintlevosegeim?year=2026&month=10');
    await expect(card.locator('.position-balance')).toContainText('20 000');
});

test('phone creates six-month reserve and renovation goals and reallocates existing savings once',async({page})=>{
    const reserve=`Biztonsági tartalék QA ${Date.now()}`;
    const renovation=`Ablakcsere QA ${Date.now()}`;
    await page.goto('/megtakaritasi-celok?year=2026&month=10');
    for(const [name,kind] of [[reserve,'reserve'],[renovation,'project']]){
        await page.getByRole('button',{name:'Új megtakarítási cél',exact:true}).click();
        await page.getByLabel('Nyilvántartás típusa',{exact:true}).selectOption(kind);
        await page.getByLabel('Cél neve',{exact:true}).fill(name);
        await page.getByLabel('Nyilvántartás kezdete',{exact:true}).fill('2026-01-01');
        if(kind==='reserve')await page.getByLabel('Jelenlegi közös havi jövedelem (Ft)',{exact:true}).fill('700000');
        else await page.getByLabel('Teljes célösszeg (Ft)',{exact:true}).fill('500000');
        expect(await page.locator('dialog').evaluate(e=>e.scrollWidth)).toBeLessThanOrEqual(360);
        await page.getByRole('button',{name:'Nyilvántartás mentése',exact:true}).click();
        await expect(page.locator('dialog')).toHaveCount(0);
    }
    const card=page.getByRole('region',{name:reserve,exact:true});
    const project=page.getByRole('region',{name:renovation,exact:true});
    await expect(card).toContainText('4 200 000');
    await card.getByRole('button',{name:'Félreteszek',exact:true}).click();
    await page.getByLabel('Összeg (Ft)',{exact:true}).fill('100000');
    await page.getByLabel('Dátum',{exact:true}).fill('2026-10-01');
    await page.getByRole('button',{name:'Pénzmozgás mentése',exact:true}).click();
    await expect(card.locator('.position-balance')).toContainText('100 000');
    await card.getByRole('button',{name:'Másik célra átcsoportosítok',exact:true}).click();
    await page.getByLabel('Másik cél',{exact:true}).selectOption({label:renovation});
    await page.getByLabel('Átcsoportosítás összege (Ft)',{exact:true}).fill('30000');
    await page.getByLabel('Átcsoportosítás dátuma',{exact:true}).fill('2026-10-02');
    await page.getByRole('button',{name:'Átcsoportosítás mentése',exact:true}).click();
    await expect(card.locator('.position-balance')).toContainText('70 000');
    await expect(project.locator('.position-balance')).toContainText('30 000');
    await project.getByRole('button',{name:'Visszaveszek',exact:true}).click();
    await page.getByLabel('Összeg (Ft)',{exact:true}).fill('40000');
    await page.getByLabel('Dátum',{exact:true}).fill('2026-10-03');
    await page.getByRole('button',{name:'Pénzmozgás mentése',exact:true}).click();
    await expect(page.locator('dialog')).toContainText('negatív zsebegyenleget');
    await page.getByLabel('Összeg (Ft)',{exact:true}).fill('10000');
    await page.getByRole('button',{name:'Pénzmozgás mentése',exact:true}).click();
    await expect(project.locator('.position-balance')).toContainText('20 000');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
    await page.screenshot({path:'../docs/screenshots/phase7-goals-360.png',fullPage:true});
});


test('verified phone pages expand to tablet and desktop without overflow in either theme',async({page})=>{
    for(const route of ['/megtakaritasi-celok','/kintlevosegeim']){
        await page.goto(route+'?year=2026&month=10');
        for(const width of [360,390,430,768,1280]){
            await page.setViewportSize({width,height:900});
            expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        }
        await page.screenshot({path:'../docs/screenshots/phase7-'+route.slice(1)+'-1280.png',fullPage:true});
        await page.getByRole('button',{name:'Sötét téma',exact:true}).first().click();
        expect((await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze()).violations).toEqual([]);
        await page.getByRole('button',{name:'Világos téma',exact:true}).first().click();
    }
});
