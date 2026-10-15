import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.use({actionTimeout:15000});

test.beforeEach(async ({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.getByRole('heading',{name:'Szia, Isti!'})).toBeVisible();
});

test('phase 2 phone screens and account editor fit 360px and meet WCAG AA',async({page})=>{
    for(const route of ['/szamlak','/hitelek']) {
        await page.goto(route);await expect(page.locator('h1')).toBeVisible();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
        const result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
        expect(result.violations).toEqual([]);
        await page.screenshot({path:`../docs/screenshots/phase2-${route.slice(1)}-360.png`,fullPage:true,animations:'disabled'});
    }
    await page.goto('/szamlak');
    await page.getByRole('button',{name:'CIB alszámla – Isti szerkesztése',exact:true}).click();
    await expect(page.getByLabel('Kapcsolódó főszámla')).toHaveValue('1');
    const result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    expect(result.violations).toEqual([]);
    await page.keyboard.press('Escape');
});

test('phone manages savings account, Anna snapshot, loan and its actual payment and reserve',async({page})=>{
    const marker=String(Date.now());
    const errors:string[]=[];page.on('pageerror',error=>errors.push(error.message));
    await page.goto('/szamlak?year=2026&month=8');
    await page.getByRole('button',{name:'Havi helyzetjelentés',exact:true}).click();
    await page.getByLabel('Számlaegyenleg (Ft)',{exact:true}).fill('54321');
    await page.getByRole('button',{name:'Helyzetjelentés mentése',exact:true}).click();
    await expect(page.locator('.snapshot-balance')).toContainText('54 321');
    await expect(page.locator('.snapshot-balance')).toContainText('2026. 08. 31.');
    await page.reload();await expect(page.locator('.snapshot-balance')).toContainText('54 321');

    await page.getByRole('button',{name:'Új számla',exact:true}).click();
    await page.getByLabel('Számla neve',{exact:true}).fill(`Családi tartalék ${marker}`);
    await page.getByLabel('Számla típusa',{exact:true}).selectOption('saving');
    await page.getByLabel('Nyitóegyenleg dátuma').fill('2026-01-01');
    await page.getByRole('button',{name:'Számla mentése',exact:true}).click();
    await expect(page.locator('dialog')).not.toBeVisible();
    await expect(page.locator('.account-management').filter({hasText:`Családi tartalék ${marker}`})).toHaveCount(1);

    await page.goto('/hitelek');
    await page.getByRole('button',{name:'Új hitel',exact:true}).click();
    await page.getByLabel('Hitel neve',{exact:true}).fill(`Teszt áruhitel ${marker}`);
    await page.getByLabel('Hitel kezdete',{exact:true}).fill('2026-01-01');
    await page.getByLabel('Havi törlesztőrészlet (Ft)',{exact:true}).fill('50000');
    await page.getByLabel('Éves kamat (%), ha ismert',{exact:true}).fill('7.5');
    await page.getByLabel('Havi esedékesség napja').fill('25');
    await page.getByRole('button',{name:'Hitel mentése',exact:true}).click();
    await expect(page.locator('dialog')).not.toBeVisible();
    const loan=page.locator('.loan-card').filter({has:page.getByRole('heading',{name:`Teszt áruhitel ${marker}`,exact:true})});
    await expect(loan).toContainText('50 000');
    await loan.getByRole('button',{name:`Teszt áruhitel ${marker} szerkesztése`,exact:true}).click();
    const result=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    expect(result.violations).toEqual([]);
    await page.getByLabel('Havi esedékesség napja').fill('26');
    await expect(page.getByLabel('Ezek a feltételek ettől a dátumtól érvényesek')).toHaveValue(/-01$/);
    await page.keyboard.press('Escape');
    await loan.getByRole('button',{name:'Törlesztés',exact:true}).click();
    await expect(page.getByLabel('Melyik hitel törlesztése?')).toHaveValue(/\d+/);
    await page.getByLabel('Összeg',{exact:true}).fill('50000');
    await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
    await expect(loan).toContainText('A havi törlesztő rögzítve.');
    await page.reload();await expect(loan).toContainText('A havi törlesztő rögzítve.');

    await page.getByRole('button',{name:'Új tétel',exact:true}).click();
    await page.getByRole('button',{name:'Átvezetés',exact:true}).click();
    await page.getByLabel('Összeg',{exact:true}).fill('60000');
    await page.getByLabel('Célszámla',{exact:true}).selectOption('5');
    await page.getByLabel('Hitelhez tartozó fedezet (opcionális)',{exact:true}).selectOption({label:`Teszt áruhitel ${marker}`});
    await page.getByLabel('Átutalási díj (Ft)',{exact:true}).fill('100');
    await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
    await loan.locator('summary').filter({hasText:'Törlesztési előzmények'}).click();
    await expect(loan).toContainText('60 000');
    await expect(loan).toContainText('A havi törlesztő rögzítve.');
    await page.getByRole('button',{name:'Visszavonás',exact:true}).click();
    await expect(loan).not.toContainText('60 000');
    expect(errors).toEqual([]);
    await page.screenshot({path:'../docs/screenshots/phase2-hitel-adatok-360.png',fullPage:true,animations:'disabled'});
    await page.goto('/honap');
    await page.getByLabel('Keresés a havi tételekben').fill('50000');
    const payment=page.locator('.transaction-row').filter({hasText:`Teszt áruhitel ${marker}`});
    page.once('dialog',dialog=>dialog.accept());
    await payment.getByRole('button',{name:'Tétel törlése',exact:true}).click();
    await expect(payment).toHaveCount(0);
});

test('phone uses pocket kp, child and linked family savings, and filters without inventing personal budgets',async({page})=>{
    for(const [kind,account,target] of [['expense','4',null],['child_saving','1','8'],['saving','1',null]]) {
        await page.goto('/');await page.getByRole('button',{name:'Új tétel',exact:true}).click();
        if(kind!=='expense') {await page.getByRole('button',{name:'Félretétel',exact:true}).click();await page.getByLabel('Megtakarítás típusa').selectOption(kind!);}
        await page.getByLabel('Rögzítés számlája').selectOption(account!);
        if(target) await page.getByLabel('Célszámla',{exact:true}).selectOption(target);
        if(kind==='expense') await expect(page.locator('dialog')).toContainText('kp · Csak az egyenleg csökken.');
        await page.getByLabel('Összeg',{exact:true}).fill('2000');
        await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
        await expect(page.locator('dialog')).not.toBeVisible();
        await page.getByRole('button',{name:'Visszavonás',exact:true}).click();
    }
    await page.goto('/honap');
    await page.getByLabel('Számla',{exact:true}).selectOption('3');
    await expect(page.locator('.scope-controls')).toContainText('Szűrt havi tételek');
    await expect(page.locator('.scope-controls')).toContainText('az egész család számai maradnak');
    await page.getByRole('button',{name:'Következő hónap',exact:true}).click();
    await expect(page.getByLabel('Számla',{exact:true})).toHaveValue('3');
    await page.getByRole('button',{name:'Szűrők törlése',exact:true}).click();
    await expect(page.getByLabel('Számla',{exact:true})).toHaveValue('');
});
