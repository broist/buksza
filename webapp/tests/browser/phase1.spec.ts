import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const out=path.resolve('..','docs','screenshots');
test.beforeEach(async ({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.getByRole('heading',{name:'Szia, Isti!'})).toBeVisible();
});

test('mobile first: all screens fit the SPEC viewport sizes',async({page})=>{
    fs.mkdirSync(out,{recursive:true});
    for(const [width,height] of [[360,800],[390,844],[430,932],[768,1024],[1280,900]]) {
        await page.setViewportSize({width,height});
        for(const route of ['/','/honap','/tervezes','/beallitasok','/eves','/szamlak','/hitelek','/rendszeres','/esti-rogzites']) {
            await page.goto(route);await expect(page.locator('h1')).toBeVisible();
            const dimensions=await page.evaluate(()=>({width:innerWidth,body:document.documentElement.scrollWidth}));
            expect(dimensions.body,`${route} at ${width}px has no horizontal overflow`).toBeLessThanOrEqual(dimensions.width);
            if(width===360||width===1280) await page.screenshot({path:path.join(out,`${route==='/'?'overview':route.slice(1)}-${width}.png`),fullPage:true});
            if(width===360&&route==='/') await page.screenshot({path:path.join(out,'overview-phone.png')});
        }
    }
});

test('quick entry saves, edits, deletes, undoes and persists after reload',async({page})=>{
    const marker=`Böngészőteszt-kiadás-${Date.now()}`;
    const errors:string[]=[];page.on('pageerror',error=>errors.push(error.message));
    await page.getByRole('button',{name:'Új tétel',exact:true}).click();
    await page.getByLabel('Összeg',{exact:true}).fill('1234');
    await page.getByRole('button',{name:'élelmiszer',exact:true}).click();
    await page.getByRole('button',{name:'Dátum, megjegyzés és részletek'}).click();
    await page.getByLabel('Megjegyzés',{exact:true}).fill(marker);
    await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
    await expect(page.locator('dialog')).not.toBeVisible();
    await expect(page.getByRole('status')).toContainText('Tétel elmentve.');
    await page.reload();await page.goto('/honap');
    await page.getByLabel('Keresés a havi tételekben').fill(marker);
    await expect(page.locator('.transaction-row')).toHaveCount(1);
    await page.getByRole('button',{name:'Tétel szerkesztése',exact:true}).click();
    await page.getByLabel('Összeg',{exact:true}).fill('2345');
    await page.getByRole('button',{name:'Módosítás mentése'}).click();
    await expect(page.locator('.transaction-row')).toContainText('2 345');
    page.once('dialog',dialog=>dialog.accept());await page.getByRole('button',{name:'Tétel törlése',exact:true}).click();
    await expect(page.locator('.transaction-row')).toHaveCount(0);
    await page.getByRole('button',{name:'Visszavonás',exact:true}).click();
    await expect(page.locator('.transaction-row')).toHaveCount(1);
    page.once('dialog',dialog=>dialog.accept());await page.getByRole('button',{name:'Tétel törlése',exact:true}).click();
    expect(errors).toEqual([]);
});

test('income and both savings are distinct; keyboard-sized sheet keeps save reachable',async({page})=>{
    for(const [type,value] of [['Bevétel','10000'],['Családi félretétel','1000'],['Örs babakötvény-befizetése','2000']]) {
        await page.goto('/');await page.getByRole('button',{name:'Új tétel',exact:true}).click();
        if(type==='Bevétel') await page.getByRole('button',{name:'Bevétel',exact:true}).click();
        else {await page.getByRole('button',{name:'Félretétel',exact:true}).click();await page.getByLabel('Megtakarítás típusa').selectOption({label:type});}
        await page.getByLabel('Összeg',{exact:true}).fill(value);
        await page.setViewportSize({width:360,height:450});
        const save=page.getByRole('button',{name:'Tétel mentése',exact:true});await expect(save).toBeInViewport();
        await save.click();await expect(page.locator('dialog')).not.toBeVisible();
        await page.setViewportSize({width:360,height:800});
        await page.getByRole('button',{name:'Visszavonás',exact:true}).click();
    }
});

test('plans, category editing and month navigation work on a phone',async({page})=>{
    await page.goto('/tervezes');await page.getByLabel('élelmiszer kerete',{exact:true}).fill('155000');
    await page.getByRole('button',{name:'Havi terv mentése',exact:true}).click();
    await expect(page.getByRole('status')).toContainText('Havi terv elmentve.');
    await page.goto('/beallitasok');await page.getByRole('button',{name:'élelmiszer szerkesztése',exact:true}).click();
    await page.getByLabel('Név',{exact:true}).fill('élelmiszer teszt');
    await page.getByRole('button',{name:'Kategória mentése'}).click();await expect(page.locator('.category-settings')).toContainText('élelmiszer teszt');
    await page.getByRole('button',{name:'élelmiszer teszt szerkesztése',exact:true}).click();
    await page.getByLabel('Név',{exact:true}).fill('élelmiszer');await page.getByRole('button',{name:'Kategória mentése'}).click();
    await page.goto('/honap');
    const month=Number(await page.getByLabel('Hónap',{exact:true}).inputValue());
    await page.getByRole('button',{name:'Következő hónap'}).click();
    await expect(page.getByLabel('Hónap',{exact:true})).toHaveValue(String(month===12?1:month+1));
});

test('yearly views change year while preserving the selected monthly context',async({page})=>{
    for(const route of ['/beallitasok','/eves']) {
        await page.goto(`${route}?year=2026&month=8`);
        await expect(page.getByLabel('Hónap',{exact:true})).toHaveCount(0);
        await page.getByRole('button',{name:'Következő év',exact:true}).click();
        await expect(page.getByLabel('Év',{exact:true})).toHaveValue('2027');
        await expect.poll(()=>new URL(page.url()).searchParams.get('month')).toBe('8');
        await page.getByRole('button',{name:'Előző év',exact:true}).click();
        await expect(page.getByLabel('Év',{exact:true})).toHaveValue('2026');
        await page.locator('.bottom-nav').getByRole('link',{name:'Hónap',exact:true}).click();
        await expect(page.getByLabel('Hónap',{exact:true})).toHaveValue('8');
        await expect(page.getByLabel('Év',{exact:true})).toHaveValue('2026');
    }
});

test('dark theme persists and Escape dismisses a sheet',async({page})=>{
    await page.getByRole('button',{name:'Sötét téma',exact:true}).first().click();
    await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
    await page.reload();await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
    await page.screenshot({path:path.join(out,'overview-dark-360.png'),fullPage:true});
    await page.getByRole('button',{name:'Új tétel',exact:true}).click();await expect(page.locator('dialog')).toBeVisible();
    await page.keyboard.press('Escape');await expect(page.locator('dialog')).not.toBeVisible();
});

test('desktop grid opens a prefilled cell and supports direct editing',async({page})=>{
    await page.setViewportSize({width:1280,height:900});await page.goto('/honap');
    await expect(page.locator('table')).toBeVisible();
    await page.getByRole('button',{name:'28. nap, élelmiszer: 0 Ft',exact:true}).click();
    await expect(page.getByRole('button',{name:'élelmiszer',exact:true})).toHaveClass('chosen');
    await page.getByLabel('Összeg',{exact:true}).fill('5000');
    await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
    await page.getByRole('button',{name:'28. nap, élelmiszer: 5000 Ft',exact:true}).click();
    await expect(page.getByLabel('Összeg',{exact:true})).toHaveValue('5 000');
    await page.keyboard.press('Escape');
    await page.getByRole('button',{name:'Visszavonás',exact:true}).click();
    await expect(page.getByRole('button',{name:'28. nap, élelmiszer: 0 Ft',exact:true})).toBeVisible();
});
