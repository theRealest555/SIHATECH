import { test, expect } from '@playwright/test';

async function signIn(page, email, role) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('E2E-only-password-2026!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/${role}/dashboard$`));
}

test('patient search, booking, doctor confirmation, cancellation and slot release', async ({ page, browser, baseURL }) => {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() + 7);
    const bookingDate = date.toISOString().slice(0, 10);
    await signIn(page, 'booking@e2e.test', 'patient');
    await page.getByRole('link', { name: 'Find a doctor', exact: true }).click();
    await page.getByLabel('Doctor name').fill('E2E Clinician');
    await page.getByLabel('Show slots on date').fill(bookingDate);
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(page.getByText(`2 available slots on ${bookingDate} (UTC)`, { exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'View profile and book', exact: true }).click();
    await expect(page.getByLabel('Appointment date')).toHaveValue(bookingDate);
    await expect(page.getByRole('button', { name: 'Request appointment', exact: true })).toBeDisabled();
    await page.getByRole('radio', { name: '09:00', exact: true }).check();
    const bookingResponse = page.waitForResponse(response => response.url().endsWith('/appointments') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Request appointment', exact: true }).click();
    const created = await bookingResponse;
    expect(created.status()).toBe(201);
    const appointment = (await created.json()).data;
    expect(appointment.patient_id).toBe(2);
    expect(appointment.doctor_id).toBe(1);
    await expect(page).toHaveURL(/\/patient\/appointments$/);
    const patientCard = page.getByRole('article', { name: `Appointment ${appointment.id}`, exact: true });
    await expect(patientCard.getByText('Awaiting confirmation', { exact: true })).toBeVisible();
    await expect(patientCard.getByRole('heading', { name: 'Dr. E2E Clinician', exact: true })).toBeVisible();

    // A separate cookie jar proves the doctor action uses the doctor's session.
    const doctorContext = await browser.newContext({ baseURL });
    try {
        const doctor = await doctorContext.newPage();
        await signIn(doctor, 'doctor@e2e.test', 'doctor');
        await doctor.getByRole('link', { name: 'Appointments', exact: true }).click();
        const doctorCard = doctor.getByRole('article', { name: `Appointment ${appointment.id}`, exact: true });
        await expect(doctorCard.getByRole('heading', { name: 'E2E Booking', exact: true })).toBeVisible();
        await expect(doctorCard.getByText('Awaiting confirmation', { exact: true })).toBeVisible();
        await doctorCard.getByRole('button', { name: 'Confirm appointment', exact: true }).click();
        await doctorCard.getByRole('button', { name: 'Confirm change', exact: true }).click();
        await expect(doctor.getByText('Appointment updated: Confirmed.', { exact: true })).toBeVisible();
        await expect(doctorCard.getByText('Confirmed', { exact: true })).toBeVisible();

        await page.reload();
        await expect(patientCard.getByText('Confirmed', { exact: true })).toBeVisible();
        await page.goto(`/doctors/1?date=${bookingDate}`);
        await expect(page.getByRole('radio', { name: '09:30', exact: true })).toBeVisible();
        await expect(page.getByRole('radio', { name: '09:00', exact: true })).toHaveCount(0);

        await page.getByRole('link', { name: 'Appointments', exact: true }).click();
        await patientCard.getByRole('button', { name: 'Cancel appointment', exact: true }).click();
        await patientCard.getByRole('button', { name: 'Confirm change', exact: true }).click();
        await expect(page.getByText('Appointment updated: Cancelled.', { exact: true })).toBeVisible();
        await expect(page.getByText('No appointments found for this filter.', { exact: true })).toBeVisible();
        await page.getByRole('combobox', { name: 'Show', exact: true }).selectOption('cancelled');
        await expect(patientCard.getByText('Cancelled', { exact: true })).toBeVisible();
        await expect(patientCard.getByRole('button', { name: 'Cancel appointment', exact: true })).toHaveCount(0);

        await doctor.reload();
        await expect(doctor.getByText('No appointments found for this filter.', { exact: true })).toBeVisible();
        await doctor.getByRole('combobox', { name: 'Show', exact: true }).selectOption('cancelled');
        await expect(doctorCard.getByText('Cancelled', { exact: true })).toBeVisible();
        await page.goto(`/doctors/1?date=${bookingDate}`);
        await expect(page.getByRole('radio', { name: '09:00', exact: true })).toBeVisible();
        await expect(page.getByRole('radio', { name: '09:30', exact: true })).toBeVisible();
    } finally {
        await doctorContext.close();
    }
});
