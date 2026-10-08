import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';

async function signIn(page, email, role) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('E2E-only-password-2026!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/${role}/dashboard$`));
}
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1cAAAAASUVORK5CYII=', 'base64');

test('private credential upload, administrator review, verification and rejection', async ({ page, browser, baseURL }) => {
    test.setTimeout(60000);
    await signIn(page, 'credentials@e2e.test', 'doctor');
    await page.getByRole('link', { name: 'Documents', exact: true }).click();
    await page.getByLabel('File', { exact: true }).setInputFiles({ name: 'e2e-licence.png', mimeType: 'image/png', buffer: png });
    const upload = page.waitForResponse(response => response.url().endsWith('/api/doctor/documents') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Upload document', exact: true }).click();
    const uploaded = await upload;
    expect(uploaded.status()).toBe(201);
    const document = (await uploaded.json()).document;
    expect(document).not.toHaveProperty('file_path');
    await expect(page.getByText('Medical licence · Awaiting review', { exact: true })).toBeVisible();
    const ownDownload = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Download e2e-licence.png', exact: true }).click();
    expect(await readFile(await (await ownDownload).path())).toEqual(png);

    const adminContext = await browser.newContext({ baseURL });
    const otherContext = await browser.newContext({ baseURL });
    try {
        // An unauthenticated visitor cannot retrieve the private API resource.
        const url = `http://localhost:8310/api/doctor/documents/${document.id}/download`;
        expect((await otherContext.request.get(url, { headers: { Accept: 'application/json' } })).status()).toBe(401);
        const other = await otherContext.newPage();
        await signIn(other, 'doctor@e2e.test', 'doctor');
        expect((await otherContext.request.get(url, { headers: { Accept: 'application/json', Origin: baseURL } })).status()).toBe(404);
        const admin = await adminContext.newPage();
        await signIn(admin, 'reviewer@e2e.test', 'admin');
        await admin.getByRole('link', { name: 'Doctor verification', exact: true }).click();
        await admin.getByRole('button', { name: 'Review Dr. E2E Credentials', exact: true }).click();
        await expect(admin.getByRole('button', { name: 'Verify doctor', exact: true })).toBeDisabled();
        const adminDownload = admin.waitForEvent('download');
        await admin.getByRole('button', { name: 'Download e2e-licence.png', exact: true }).click();
        expect(await readFile(await (await adminDownload).path())).toEqual(png);
        await admin.getByRole('button', { name: 'Approve e2e-licence.png', exact: true }).click();
        await admin.getByRole('button', { name: 'Confirm decision', exact: true }).click();
        await expect(admin.getByText('Medical licence · Approved', { exact: true })).toBeVisible();
        await admin.getByRole('button', { name: 'Verify doctor', exact: true }).click();
        await admin.getByRole('button', { name: 'Confirm decision', exact: true }).click();
        await expect(admin.getByRole('button', { name: 'Revoke verification', exact: true })).toBeVisible();
        await page.reload();
        await expect(page.getByText('Medical licence · Approved', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Delete e2e-licence.png', exact: true })).toHaveCount(0);

        await admin.getByRole('button', { name: 'Reject e2e-licence.png', exact: true }).click();
        await expect(admin.getByRole('button', { name: 'Confirm decision', exact: true })).toBeDisabled();
        await admin.getByLabel('Reason', { exact: true }).fill('E2E replacement credential required');
        await admin.getByRole('button', { name: 'Confirm decision', exact: true }).click();
        await expect(admin.getByText('Medical licence · Rejected', { exact: true })).toBeVisible();
        await expect(admin.getByRole('button', { name: 'Verify doctor', exact: true })).toBeDisabled();
        await page.reload();
        await expect(page.getByText('Review feedback: E2E replacement credential required', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Delete e2e-licence.png', exact: true }).click();
        await page.getByRole('button', { name: 'Confirm deletion', exact: true }).click();
        await expect(page.getByText('No documents uploaded.', { exact: true })).toBeVisible();
    } finally {
        await adminContext.close();
        await otherContext.close();
    }
});
