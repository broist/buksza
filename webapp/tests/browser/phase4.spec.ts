import {test,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.use({actionTimeout:15000});

test.beforeEach(async({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.locator('h1')).toHaveText('Szia, Isti!');
});

test('phase four phone layouts fit 360px and are accessible before desktop expansion',async({page})=>{
    await page.setViewportSize({width:360,height:800});
    for(const route of ['/zsebpenzek','/befektetesek','/eves','/beallitasok']){
        await page.goto(`${route}?year=2026&month=10`);
        await expect(page.locator('h1')).toBeVisible();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
        const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
        expect(axe.violations).toEqual([]);
        await page.screenshot({path:`../docs/screenshots/phase4-${route.slice(1)}-360.png`,fullPage:true});
    }
});

test('phone creates a bond plans monthly funding and records value without another payment',async({page})=>{
    await page.goto('/befektetesek?year=2026&month=10');
    const originalAnnual=(await page.locator('.p4-metric').first().textContent())!;
    await page.getByRole('button',{name:'Új babakötvény',exact:true}).click();
    await page.getByLabel('Megnevezés',{exact:true}).fill(`QA babakötvény ${Date.now()}`);
    await page.getByLabel('Sorozat',{exact:true}).fill('2044/S');
    await page.getByLabel('Nyilvántartás kezdete',{exact:true}).fill('2026-01-01');
    await page.getByLabel('Kezdő befizetett tőke (Ft)',{exact:true}).fill('50000');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    await page.getByRole('button',{name:'Babakötvény mentése',exact:true}).click();
    await expect(page.locator('dialog')).toHaveCount(0);
    const card=page.locator('.p4-list>.card').last();
    await card.getByRole('button',{name:'Havi terv',exact:true}).click();
    await page.getByLabel('Havi tervezett befizetés (Ft)',{exact:true}).fill('10000');
    await page.getByLabel('Ettől a hónaptól',{exact:true}).fill('2026-01');
    await page.getByRole('button',{name:'Mentés',exact:true}).click();
    await expect(card).toContainText('120 000');
    await card.getByRole('button',{name:'MÁK-érték megadása',exact:true}).click();
    await page.getByLabel('Teljes befektetési érték (Ft)',{exact:true}).fill('70000');
    await page.getByRole('button',{name:'Mentés',exact:true}).click();
    await expect(card).toContainText('70 000');
    await expect(page.locator('.p4-metric').first()).toHaveText(originalAnnual);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(axe.violations).toEqual([]);
    await page.screenshot({path:'../docs/screenshots/phase4-babakotveny-adatok-360.png',fullPage:true});
});

test('year export downloads actual XLSX and encrypted finance backup',async({page,context})=>{
    await page.goto('/eves?year=2026');
    const [xlsx]=await Promise.all([page.waitForEvent('download'),page.getByRole('link',{name:'Teljes év · Excel',exact:true}).click()]);
    expect(xlsx.suggestedFilename()).toBe('buksza-2026.xlsx');
    const xlsxResponse=await context.request.get('/export?year=2026&format=xlsx');expect((await xlsxResponse.body()).subarray(0,2).toString()).toBe('PK');
    await page.goto('/beallitasok');
    const [backup]=await Promise.all([page.waitForEvent('download'),page.getByRole('link',{name:'Pénzügyi mentés letöltése',exact:true}).click()]);
    expect(backup.suggestedFilename()).toMatch(/\.buksza$/);
    expect(await (await context.request.get('/mentes')).text()).not.toContain('qa@example.test');
});

test('real WebAuthn virtual authenticator registers verifies signs in and rejects replay',async({page,context})=>{
    const client=await context.newCDPSession(page);
    await client.send('WebAuthn.enable');
    const {authenticatorId}=await client.send('WebAuthn.addVirtualAuthenticator',{options:{protocol:'ctap2',transport:'internal',hasResidentKey:true,hasUserVerification:true,isUserVerified:true,automaticPresenceSimulation:true}});
    const keyName=`QA virtuális passkey ${Date.now()}`;
    try{
        await page.goto('/beallitasok');
        await page.getByLabel('Passkey neve',{exact:true}).fill(keyName);
        await page.getByLabel('Jelenlegi jelszó a passkey kezeléséhez',{exact:true}).fill('only-for-qa-2026');
        await page.getByRole('button',{name:'Új passkey hozzáadása',exact:true}).click();
        await expect(page.locator('.p4-month').filter({hasText:keyName})).toBeVisible();
        await page.getByRole('button',{name:'Továbbiak',exact:true}).click();
        await page.locator('dialog').getByRole('button',{name:'Kilépés',exact:true}).click();
        await expect(page.getByRole('button',{name:'Belépés passkeyjel',exact:true})).toBeVisible();
        const assertion=page.waitForRequest(request=>request.url().endsWith('/passkey/login'));
        await page.getByRole('button',{name:'Belépés passkeyjel',exact:true}).click();
        const signed=await assertion;
        await expect(page.locator('h1')).toHaveText('Szia, Isti!');
        const replay=await context.request.post('/passkey/login',{data:signed.postDataJSON(),headers:{Accept:'application/json','X-XSRF-TOKEN':decodeURIComponent((await context.cookies()).find(c=>c.name==='XSRF-TOKEN')!.value)}});
        expect(replay.status()).toBe(422);
        await page.goto('/beallitasok');
        await page.getByLabel('Jelenlegi jelszó a passkey kezeléséhez',{exact:true}).fill('only-for-qa-2026');
        page.once('dialog',dialog=>dialog.accept());
        await page.locator('.p4-month').filter({hasText:keyName}).getByRole('button',{name:'Passkey törlése',exact:true}).click();
        await expect(page.locator('.p4-month').filter({hasText:keyName})).toHaveCount(0);
    }finally{await client.send('WebAuthn.removeVirtualAuthenticator',{authenticatorId});}
});

test('phone restores a downloaded backup with password and keeps the authenticated account',async({page,context})=>{
    const backup=await context.request.get('/mentes');
    expect(backup.ok()).toBeTruthy();
    const buffer=await backup.body();
    const note=`restore-qa-${Date.now()}`;
    const csrf=decodeURIComponent((await context.cookies()).find(c=>c.name==='XSRF-TOKEN')!.value);
    const created=await context.request.post('/tetelek',{data:{client_id:crypto.randomUUID(),date:'2026-10-15',amount:4800,type:'expense',category_id:1,account_id:1,business:false,note},headers:{'X-XSRF-TOKEN':csrf}});
    expect(created.ok()).toBeTruthy();
    await page.goto('/eves?year=2026&month=10');
    await page.getByLabel('Keresés',{exact:true}).fill(note);
    await expect(page.locator('.transaction-row')).toHaveCount(1);
    await page.goto('/beallitasok');
    await page.getByText('Korábbi pénzügyi mentés visszaállítása',{exact:true}).click();
    await page.getByLabel('Mentésfájl',{exact:true}).setInputFiles({name:'teszt.buksza',mimeType:'application/octet-stream',buffer});
    await page.getByLabel('Jelenlegi jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByLabel('A jelenlegi pénzügyi adatokat a mentésre cserélem.',{exact:true}).check();
    await page.getByRole('button',{name:'Mentés visszaállítása',exact:true}).click();
    await expect(page.getByRole('status')).toContainText('Pénzügyek visszaállítva.');
    await expect(page.getByRole('link',{name:'Legutóbbi visszaállítás előtti mentés',exact:true})).toBeVisible();
    await page.goto('/eves?year=2026&month=10');
    await page.getByLabel('Keresés',{exact:true}).fill(note);
    await expect(page.locator('.transaction-row')).toHaveCount(0);
});
