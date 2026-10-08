import { test, expect } from '@playwright/test';

const weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
function futureDay(daysAhead) {
    const day = new Date();
    day.setUTCDate(day.getUTCDate() + daysAhead);
    return { date: day.toISOString().slice(0, 10), weekday: weekdayNames[day.getUTCDay()] };
}

test('schedule and leave changes preserve bookings and reconcile stale edits', async ({ page, context, browser, baseURL }) => {
    test.setTimeout(60000);
    const pending = futureDay(14);
    const confirmed = futureDay(15);
    const leaveStart = futureDay(16).date;
    const leaveEnd = futureDay(17).date;
    await page.goto('/login');
    await page.getByLabel('Email address').fill('availability@e2e.test');
    await page.getByLabel('Password', { exact: true }).fill('E2E-only-password-2026!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/doctor\/dashboard$/);
    await page.getByRole('link', { name: 'Availability', exact: true }).click();
    await expect(page.getByLabel(pending.weekday, { exact: true })).toHaveValue('09:00-10:00');

    for (const bookedDay of [pending, confirmed]) {
        await page.getByLabel(bookedDay.weekday, { exact: true }).fill('10:00-11:00');
        const rejected = page.waitForResponse(response => response.url().endsWith('/api/doctor/schedule') && response.request().method() === 'PUT');
        await page.getByRole('button', { name: 'Save schedule', exact: true }).click();
        expect((await rejected).status()).toBe(409);
        await expect(page.getByRole('alert')).toContainText('Le nouvel horaire entre en conflit');
        await page.getByRole('button', { name: 'Reload availability', exact: true }).click();
        await expect(page.getByLabel(bookedDay.weekday, { exact: true })).toHaveValue('09:00-10:00');

        await page.getByLabel('Start date', { exact: true }).fill(bookedDay.date);
        await page.getByLabel('End date', { exact: true }).fill(bookedDay.date);
        const leaveRejected = page.waitForResponse(response => response.url().endsWith('/api/doctor/leaves') && response.request().method() === 'POST');
        await page.getByRole('button', { name: 'Add leave', exact: true }).click();
        expect((await leaveRejected).status()).toBe(409);
        await expect(page.getByRole('alert')).toContainText('La période de congé entre en conflit');
        await page.getByRole('button', { name: 'Reload availability', exact: true }).click();
        await expect(page.getByText('No upcoming leave.', { exact: true })).toBeVisible();
    }

    // Capture an older revision in a second tab before saving a compatible edit.
    const stale = await context.newPage();
    await stale.goto('/doctor/availability');
    await expect(stale.getByLabel(pending.weekday, { exact: true })).toHaveValue('09:00-10:00');
    await page.getByLabel(pending.weekday, { exact: true }).fill('09:00-11:00');
    await page.getByRole('button', { name: 'Save schedule', exact: true }).click();
    await expect(page.getByText('Weekly schedule saved.', { exact: true })).toBeVisible();
    await expect(page.getByLabel(pending.weekday, { exact: true })).toHaveValue('09:00-11:00');
    await stale.getByLabel(confirmed.weekday, { exact: true }).fill('09:00-12:00');
    const conflict = stale.waitForResponse(response => response.url().endsWith('/api/doctor/schedule') && response.request().method() === 'PUT');
    await stale.getByRole('button', { name: 'Save schedule', exact: true }).click();
    expect((await conflict).status()).toBe(409);
    await stale.getByRole('button', { name: 'Reload availability', exact: true }).click();
    await expect(stale.getByLabel(pending.weekday, { exact: true })).toHaveValue('09:00-11:00');
    await expect(stale.getByLabel(confirmed.weekday, { exact: true })).toHaveValue('09:00-10:00');
    await stale.close();

    const publicContext = await browser.newContext({ baseURL });
    try {
        const visitor = await publicContext.newPage();
        await visitor.goto(`/doctors/2?date=${pending.date}`);
        await expect(visitor.getByRole('radio', { name: '10:30', exact: true })).toBeVisible();
        await expect(visitor.getByRole('radio', { name: '09:00', exact: true })).toHaveCount(0);
        await page.getByLabel('Start date', { exact: true }).fill(leaveStart);
        await page.getByLabel('End date', { exact: true }).fill(leaveEnd);
        await page.getByLabel('Reason (optional, private)', { exact: true }).fill('E2E private leave reason');
        await page.getByRole('button', { name: 'Add leave', exact: true }).click();
        await expect(page.getByText('Leave added.', { exact: true })).toBeVisible();
        await page.reload();
        await expect(page.getByText('E2E private leave reason', { exact: true })).toBeVisible();
        for (const date of [leaveStart, leaveEnd]) {
            await visitor.goto(`/doctors/2?date=${date}`);
            await expect(visitor.getByText('This doctor is on leave on this date.', { exact: true })).toBeVisible();
            await expect(visitor.getByRole('radio')).toHaveCount(0);
            await expect(visitor.getByText('E2E private leave reason')).toHaveCount(0);
        }
        await page.getByRole('button', { name: 'Remove leave', exact: true }).click();
        await page.getByRole('button', { name: 'Keep leave', exact: true }).click();
        await expect(page.getByText('E2E private leave reason', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Remove leave', exact: true }).click();
        await page.getByRole('button', { name: 'Confirm removal', exact: true }).click();
        await expect(page.getByText('Leave removed.', { exact: true })).toBeVisible();
        await expect(page.getByText('No upcoming leave.', { exact: true })).toBeVisible();
        await visitor.reload();
        await expect(visitor.getByRole('radio', { name: '09:00', exact: true })).toBeVisible();

        await page.getByRole('link', { name: 'Appointments', exact: true }).click();
        await expect(page.getByRole('article')).toHaveCount(2);
        await expect(page.getByText('Awaiting confirmation', { exact: true })).toBeVisible();
        await expect(page.getByText('Confirmed', { exact: true })).toBeVisible();
    } finally {
        await publicContext.close();
    }
});
