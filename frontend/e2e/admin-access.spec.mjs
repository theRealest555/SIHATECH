import { test, expect } from '@playwright/test';

test('administrator reasons, competing decisions, restoration and audit history', async ({ page, context }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('reviewer@e2e.test');
    await page.getByLabel('Password', { exact: true }).fill('E2E-only-password-2026!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await page.getByRole('link', { name: 'Users', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/users$/);
    await page.getByRole('button', { name: 'Deactivate E2E Fixture', exact: true }).click();
    const confirm = page.getByRole('button', { name: 'Confirm status change', exact: true });
    await expect(confirm).toBeDisabled();
    await page.getByLabel('Reason for deactivation').fill('   ');
    await expect(confirm).toBeDisabled();
    await page.getByLabel('Reason for deactivation').fill('Stale decision must not be recorded');
    await expect(confirm).toBeEnabled();

    const competing = await context.newPage();
    await competing.goto('/admin/users');
    await competing.getByRole('button', { name: 'Deactivate E2E Fixture', exact: true }).click();
    await competing.getByLabel('Reason for deactivation').fill('  E2E administrative access review  ');
    const committed = competing.waitForResponse(response => response.url().endsWith('/api/admin/users/2/status') && response.request().method() === 'PUT');
    await competing.getByRole('button', { name: 'Confirm status change', exact: true }).click();
    expect((await committed).status()).toBe(200);
    await expect(competing.getByText('Account deactivated. Existing access was revoked; account history is preserved.')).toBeVisible();

    const conflict = page.waitForResponse(response => response.url().endsWith('/api/admin/users/2/status') && response.request().method() === 'PUT');
    await confirm.click();
    expect((await conflict).status()).toBe(409);
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('alert')).toContainText('Account access changed in another session.');
    await page.getByRole('button', { name: 'Reload users', exact: true }).click();
    await page.getByRole('button', { name: 'Activate E2E Fixture', exact: true }).click();
    await expect(page.getByLabel('Reason for deactivation')).toHaveCount(0);
    await page.getByRole('button', { name: 'Confirm status change', exact: true }).click();
    await expect(page.getByText('Account activated. Previously revoked access remains revoked.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Deactivate E2E Fixture', exact: true })).toBeVisible();

    await competing.goto('/admin/audit-logs');
    await competing.getByRole('combobox', { name: /^Action/ }).selectOption('updated_user_status');
    await competing.getByRole('button', { name: 'Filter history', exact: true }).click();
    await expect(competing.getByText('Account status: Active → Inactive', { exact: true })).toBeVisible();
    await expect(competing.getByText('Account status: Inactive → Active', { exact: true })).toBeVisible();
    await expect(competing.getByText('E2E administrative access review', { exact: true })).toBeVisible();
    await expect(competing.getByText('Existing sessions and API tokens revoked.', { exact: true })).toHaveCount(1);
    await expect(competing.getByText('Stale decision must not be recorded')).toHaveCount(0);
    await expect(competing.locator('tbody tr')).toHaveCount(2);
    await competing.close();
});
