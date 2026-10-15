import { test, expect } from '@playwright/test';

test.beforeEach(async ({page}) => {
    await page.goto('/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');
    await page.getByLabel('Jelszó', {exact: true}).fill('only-for-qa-2026');
    await page.getByRole('button', {name: 'Belépés', exact: true}).click();
    await expect(page.getByRole('heading', {name: 'Szia, Isti!'})).toBeVisible();
});

test('opening balances and monthly plans show spaces and save the original number', async ({page}) => {
    await page.goto('/beallitasok');
    const opening = page.getByLabel('Terv szerinti éves nyitó összeg');
    const balance = page.getByLabel('CIB főszámla valós nyitóegyenlege');
    await opening.fill('700000');
    await expect(opening).toHaveValue('700 000');
    await balance.fill('-1234567');
    await expect(balance).toHaveValue('-1 234 567');
    await page.getByRole('button', {name: 'Kezdőadatok mentése'}).click();
    await expect(page.getByRole('status')).toContainText('Kezdőadatok elmentve.');
    await page.reload();
    await expect(opening).toHaveValue('700 000');
    await expect(balance).toHaveValue('-1 234 567');

    await page.goto('/tervezes');
    const plan = page.getByLabel('élelmiszer kerete', {exact: true});
    await plan.fill('700000');
    await expect(plan).toHaveValue('700 000');
    await page.getByRole('button', {name: 'Havi terv mentése'}).click();
    await expect(page.getByRole('status')).toContainText('Havi terv elmentve.');
    await page.reload();
    await expect(plan).toHaveValue('700 000');
    await expect(page.locator('.save-bar')).toContainText('1 170 000');
});

test('quick entry groups while typing and allows editing and deleting beside separators', async ({page}) => {
    await page.getByRole('button', {name: 'Új tétel', exact: true}).click();
    const amount = page.getByLabel('Összeg', {exact: true});
    await amount.pressSequentially('700000', {delay: 20});
    await expect(amount).toHaveValue('700 000');

    await amount.evaluate((input: HTMLInputElement) => input.setSelectionRange(1, 1));
    await amount.press('9');
    await expect(amount).toHaveValue('7 900 000');
    await expect.poll(() => amount.evaluate((input: HTMLInputElement) => input.selectionStart)).toBe(3);
    await amount.press('Backspace');
    await expect(amount).toHaveValue('700 000');

    await amount.evaluate((input: HTMLInputElement) => input.setSelectionRange(4, 4));
    await amount.press('Backspace');
    await expect(amount).toHaveValue('70 000');
    await amount.evaluate((input: HTMLInputElement) => input.setSelectionRange(2, 2));
    await amount.press('Delete');
    await expect(amount).toHaveValue('7 000');

    await amount.fill('1.234.567');
    await expect(amount).toHaveValue('1 234 567');
    await amount.fill('');
    await expect(amount).toHaveValue('');
    await expect(page.getByRole('button', {name: 'Tétel mentése', exact: true})).toBeDisabled();

    await amount.fill('700000');
    await page.getByRole('button', {name: 'élelmiszer', exact: true}).click();
    await page.getByRole('button', {name: 'Tétel mentése', exact: true}).click();
    await expect(page.locator('dialog')).not.toBeVisible();
    await page.getByRole('button', {name: 'Visszavonás', exact: true}).click();
});
