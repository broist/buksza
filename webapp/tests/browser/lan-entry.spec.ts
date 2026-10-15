import { test, expect } from '@playwright/test';

// An HTTP hostname outside localhost reproduces the phone's LAN security context.
test.use({
    baseURL: 'http://buksza.test:18081',
    isMobile: true,
    hasTouch: true,
    launchOptions: { args: ['--host-resolver-rules=MAP buksza.test 127.0.0.1'] },
});

test('phone plus button opens and saves an entry over plain LAN HTTP', async ({page}) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/belepes');
    await expect(page).toHaveTitle('Belépés · Buksza');
    await expect(page.locator('.auth-art .brand')).toHaveText('Buksza');
    await page.screenshot({path: '../docs/screenshots/buksza-login-phone.png', animations: 'disabled'});
    expect(await page.evaluate(() => window.isSecureContext)).toBe(false);
    expect(await page.evaluate(() => typeof crypto.randomUUID)).toBe('undefined');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó', {exact: true}).fill('only-for-qa-2026');
    await page.getByRole('button', {name: 'Belépés', exact: true}).click();
    await expect(page.getByRole('heading', {name: 'Szia, Isti!'})).toBeVisible();

    await page.getByRole('button', {name: 'Új tétel', exact: true}).tap();
    await expect(page.locator('dialog')).toBeVisible();
    await page.screenshot({path: '../docs/screenshots/buksza-lan-entry-phone.png', animations: 'disabled'});
    await page.getByLabel('Összeg', {exact: true}).fill('1234');
    await page.getByRole('button', {name: 'élelmiszer', exact: true}).click();
    await page.getByRole('button', {name: 'Tétel mentése', exact: true}).click();
    await expect(page.locator('dialog')).not.toBeVisible();
    await expect(page.getByRole('status')).toContainText('Tétel elmentve.');
    await page.getByRole('button', {name: 'Visszavonás', exact: true}).click();
    expect(errors).toEqual([]);
});
