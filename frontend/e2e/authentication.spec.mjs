import { test, expect } from '@playwright/test';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

async function signIn(page, email, password) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/patient\/dashboard$/);
}
async function mailedLink(recipient, pattern) {
    let url;
    await expect.poll(async () => {
        const directory = path.join(process.env.SIHATECH_E2E_RUN_DIR, 'mail');
        for (const file of await readdir(directory)) {
            if (!file.endsWith('.json')) continue;
            const mail = JSON.parse(await readFile(path.join(directory, file), 'utf8'));
            if (!mail.recipients.includes(recipient)) continue;
            const decoded = mail.text.replace(/=\r\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16))).replaceAll('&amp;', '&');
            url = decoded.match(pattern)?.[0];
            if (url) return true;
        }
        return false;
    }).toBe(true);
    return url;
}

test('SMTP password recovery revokes sessions, consumes the link and verifies a changed email', async ({ page, browser, baseURL }) => {
    test.setTimeout(90000);
    const original = 'E2E-only-password-2026!';
    const changed = 'E2E-recovered-password-2026!';
    await signIn(page, 'security@e2e.test', original);
    const oldContext = await browser.newContext({ baseURL });
    const recoveryContext = await browser.newContext({ baseURL });
    try {
        const old = await oldContext.newPage();
        await signIn(old, 'security@e2e.test', original);
        const recovery = await recoveryContext.newPage();
        await recovery.goto('/forgot-password');
        await recovery.getByLabel('Email address', { exact: true }).fill('security@e2e.test');
        const requested = recovery.waitForResponse(response => response.url().endsWith('/api/forgot-password') && response.request().method() === 'POST');
        await recovery.getByRole('button', { name: 'Send Reset Link', exact: true }).click();
        expect((await requested).status()).toBe(200);
        const resetUrl = await mailedLink('security@e2e.test', /http:\/\/localhost:4310\/reset-password\/[^\s"<>]+/);
        await recovery.goto(resetUrl);
        await recovery.getByLabel('New Password', { exact: true }).fill(changed);
        await recovery.getByLabel('Confirm New Password', { exact: true }).fill(changed);
        const reset = recovery.waitForResponse(response => response.url().endsWith('/api/reset-password') && response.request().method() === 'POST');
        await recovery.getByRole('button', { name: 'Reset Password', exact: true }).click();
        expect((await reset).status()).toBe(200);
        await page.reload();
        await expect(page).toHaveURL(/\/login(?:\?|$)/);
        await old.reload();
        await expect(old).toHaveURL(/\/login(?:\?|$)/);
        // A consumed recovery link cannot perform another password change.
        await recovery.reload();
        await recovery.getByLabel('New Password', { exact: true }).fill('E2E-replay-password-2026!');
        await recovery.getByLabel('Confirm New Password', { exact: true }).fill('E2E-replay-password-2026!');
        const replay = recovery.waitForResponse(response => response.url().endsWith('/api/reset-password') && response.request().method() === 'POST');
        await recovery.getByRole('button', { name: 'Reset Password', exact: true }).click();
        expect((await replay).status()).toBe(422);
        await signIn(page, 'security@e2e.test', changed);
        await page.getByRole('link', { name: 'Profile', exact: true }).click();
        await page.getByRole('button', { name: 'Edit Profile', exact: true }).click();
        await page.getByLabel('Email', { exact: true }).fill('security-new@e2e.test');
        await page.getByLabel('Current password to change email', { exact: true }).fill(changed);
        await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
        await expect(page).toHaveURL(/\/verify-email$/);
        await expect(page.getByText('security-new@e2e.test', { exact: true })).toBeVisible();
        const verification = await mailedLink('security-new@e2e.test', /http:\/\/localhost:8310\/[^\s"<>]+signature=[a-f0-9]+/);
        await page.goto(verification);
        await expect(page).toHaveURL(/\/patient\/dashboard$/);
        await page.getByRole('link', { name: 'Profile', exact: true }).click();
        await expect(page.getByLabel('Email', { exact: true })).toHaveValue('security-new@e2e.test');
    } finally {
        await oldContext.close();
        await recoveryContext.close();
    }
});
