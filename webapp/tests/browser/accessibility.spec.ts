import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('360px light and dark screens meet WCAG AA checks',async({page})=>{
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();
    await expect(page.locator('h1')).toHaveText('Szia, Isti!');
    for(const route of ['/','/honap','/tervezes','/beallitasok','/szamlak','/hitelek']) {
        await page.goto(route);await expect(page.locator('h1')).toBeVisible();
        const results=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
        expect(results.violations,JSON.stringify(results.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})))).toEqual([]);
    }
    await page.goto('/');await page.getByRole('button',{name:'Sötét téma',exact:true}).first().click();
    const dark=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(dark.violations).toEqual([]);
    await page.getByRole('button',{name:'Új tétel',exact:true}).click();
    const sheet=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();expect(sheet.violations).toEqual([]);
});
