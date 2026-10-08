// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import FindDoctorPage from '../pages/patient/FindDoctorPage';
import DoctorProfilePage from '../pages/patient/DoctorProfilePage';
import PatientAppointmentsPage from '../pages/patient/AppointmentsPage';
import DoctorAppointmentsPage from '../pages/doctor/AppointmentsPage';
import { useAuth } from '../hooks/useAuth';
import * as doctors from '../services/doctorService';
import * as patients from '../services/patientService';

vi.mock('../hooks/useAuth', () => ({ useAuth: vi.fn() }));
vi.mock('../services/doctorService', () => ({
    getLanguages: vi.fn(), getSpecialities: vi.fn(), searchDoctorsAdvanced: vi.fn(),
    getPublicDoctorDetails: vi.fn(), getDoctorSlots: vi.fn(), getDoctorAppointments: vi.fn(),
    updateAppointmentStatus: vi.fn(), markAppointmentNoShow: vi.fn(),
}));
vi.mock('../services/patientService', () => ({
    bookAppointment: vi.fn(), getPatientAppointments: vi.fn(), cancelAppointment: vi.fn(),
}));

const patient = { id: 20, role: 'patient', email_verified_at: '2100-01-01T00:00:00Z' };
const date = '2100-01-07';
const doctor = { id: 9, name: 'Sara Ahmed', speciality: 'Cardiology', location: 'Fes', languages: ['French'], photo: null, rating: { total_reviews: 0 } };
const appointment = {
    id: 31, doctor_name: doctor.name, patient_name: 'Amine Patient', speciality: doctor.speciality,
    starts_at: `${date}T09:00:00+00:00`, statut: 'en_attente', can_be_cancelled: true, is_past: false,
};
const listResponse = (rows, meta = {}) => ({ data: { data: rows, meta: { timezone: 'UTC', current_page: 1, last_page: 1, total: rows.length, ...meta } } });
const slotsResponse = times => ({ data: { data: times, meta: { date, timezone: 'UTC', is_on_leave: false } } });

function show(path = `/doctors/9?date=${date}`) {
    return render(<MemoryRouter initialEntries={[path]}><Routes>
        <Route path="/doctors" element={<FindDoctorPage />} />
        <Route path="/doctors/:doctorId" element={<DoctorProfilePage />} />
        <Route path="/patient/appointments" element={<PatientAppointmentsPage />} />
        <Route path="/doctor/appointments" element={<DoctorAppointmentsPage />} />
    </Routes></MemoryRouter>);
}

beforeEach(() => {
    vi.resetAllMocks();
    useAuth.mockReturnValue({ user: patient });
    doctors.getSpecialities.mockResolvedValue({ data: { data: [{ id: 3, nom: 'Cardiology' }] } });
    doctors.getLanguages.mockResolvedValue({ data: { data: [{ id: 4, nom: 'French' }] } });
    doctors.searchDoctorsAdvanced.mockResolvedValue(listResponse([doctor], { today: '2100-01-01' }));
    doctors.getPublicDoctorDetails.mockResolvedValue({ data: {
        data: { ...doctor, speciality: { nom: 'Cardiology' }, description: 'Heart health specialist.', languages: [{ nom: 'French' }] },
        meta: { timezone: 'UTC', today: '2100-01-01' },
    } });
    doctors.getDoctorSlots.mockResolvedValue(slotsResponse(['09:00', '09:30']));
    patients.getPatientAppointments.mockResolvedValue(listResponse([appointment]));
    doctors.getDoctorAppointments.mockResolvedValue(listResponse([appointment]));
});
afterEach(() => cleanup());

it('offers no-show only for confirmed past visits and requires an attendance confirmation', async () => {
    doctors.getDoctorAppointments.mockResolvedValue(listResponse([
        { ...appointment, is_past: true, can_be_cancelled: false },
        { ...appointment, id: 32, statut: 'confirmé', is_past: true, can_be_cancelled: false },
        { ...appointment, id: 33, statut: 'confirmé' },
    ]));
    const user = userEvent.setup(); show('/doctor/appointments');
    await screen.findByRole('button', { name: 'Mark no-show' });
    expect(within(screen.getByRole('article', { name: 'Appointment 31' })).queryByRole('button', { name: 'Mark no-show' })).toBeNull();
    expect(within(screen.getByRole('article', { name: 'Appointment 33' })).queryByRole('button', { name: 'Mark no-show' })).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Mark no-show' }));
    expect(screen.getByText(/only if you have checked that the patient did not attend/)).toBeTruthy();
    expect(doctors.markAppointmentNoShow).not.toHaveBeenCalled();
    await user.click(screen.getByRole('button', { name: 'Keep unchanged' }));
    expect(doctors.markAppointmentNoShow).not.toHaveBeenCalled();
    await user.click(screen.getByRole('button', { name: 'Mark no-show' }));
    doctors.markAppointmentNoShow.mockResolvedValue({});
    doctors.getDoctorAppointments.mockResolvedValue(listResponse([{ ...appointment, id: 32, statut: 'no_show', is_past: true, can_be_cancelled: false }]));
    await user.click(screen.getByRole('button', { name: 'Confirm change' }));
    await screen.findByText('Appointment updated: No-show.');
    expect(doctors.markAppointmentNoShow).toHaveBeenCalledWith(32);
    expect(screen.queryByRole('button', { name: 'Mark no-show' })).toBeNull();
});

it('reloads a competing attendance decision after a no-show conflict', async () => {
    doctors.getDoctorAppointments.mockResolvedValueOnce(listResponse([{ ...appointment, statut: 'confirmé', is_past: true, can_be_cancelled: false }]))
        .mockResolvedValue(listResponse([{ ...appointment, statut: 'terminé', is_past: true, can_be_cancelled: false }]));
    doctors.markAppointmentNoShow.mockRejectedValue({ response: { status: 409, data: { message: 'Only a confirmed appointment can be marked as no-show.' } } });
    const user = userEvent.setup(); show('/doctor/appointments');
    await user.click(await screen.findByRole('button', { name: 'Mark no-show' }));
    await user.click(screen.getByRole('button', { name: 'Confirm change' }));
    await screen.findByText('Completed');
    expect(screen.getByRole('alert').textContent).toContain('Only a confirmed appointment');
    expect(screen.queryByText('Appointment updated: No-show.')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Mark no-show' })).toBeNull();
});

describe('Real doctor search and booking screens', () => {
    it('maps API filter fields and preserves submitted filters during pagination', async () => {
        doctors.searchDoctorsAdvanced.mockImplementation(params => Promise.resolve(listResponse([doctor], { today: '2100-01-01', current_page: params.page, last_page: 2, total: 13 })));
        const user = userEvent.setup();
        show('/doctors');
        await screen.findByRole('heading', { name: 'Dr. Sara Ahmed' });
        await user.type(screen.getByLabelText('Doctor name'), 'Sara');
        await user.selectOptions(screen.getByLabelText('Speciality'), '3');
        await user.selectOptions(screen.getByLabelText('Language'), '4');
        await user.type(screen.getByLabelText('Location'), 'Fes');
        await user.type(screen.getByLabelText('Show slots on date'), date);
        await user.click(screen.getByRole('button', { name: 'Search' }));
        await waitFor(() => expect(doctors.searchDoctorsAdvanced).toHaveBeenCalledTimes(2));
        expect(doctors.searchDoctorsAdvanced.mock.calls[1][0]).toMatchObject({ name: 'Sara', speciality_id: '3', location: 'Fes', language_ids: ['4'], date, page: 1 });
        await screen.findByRole('button', { name: 'Next' });
        await user.clear(screen.getByLabelText('Doctor name'));
        await user.type(screen.getByLabelText('Doctor name'), 'Draft not submitted');
        await user.click(screen.getByRole('button', { name: 'Next' }));
        await waitFor(() => expect(doctors.searchDoctorsAdvanced).toHaveBeenCalledTimes(3));
        expect(doctors.searchDoctorsAdvanced.mock.calls[2][0]).toMatchObject({ name: 'Sara', page: 2, date });
        expect(screen.getByRole('link', { name: 'View profile and book' }).getAttribute('href')).toBe(`/doctors/9?date=${date}`);
    });

    it('books the selected real slot and displays the pending appointment', async () => {
        patients.bookAppointment.mockResolvedValue({ data: { data: { id: 31 } } });
        const user = userEvent.setup();
        show();
        await user.click(await screen.findByLabelText('09:00'));
        await user.click(screen.getByRole('button', { name: 'Request appointment' }));
        await screen.findByRole('heading', { name: 'My appointments' });
        expect(patients.bookAppointment).toHaveBeenCalledWith({ doctor_id: '9', date_heure: `${date} 09:00:00` });
        expect(await screen.findByText('Awaiting confirmation')).toBeTruthy();
        expect(screen.getByText(/Your appointment request was sent/)).toBeTruthy();
    });

    it('refreshes taken slots after a booking conflict without claiming success', async () => {
        doctors.getDoctorSlots.mockResolvedValueOnce(slotsResponse(['09:00', '09:30'])).mockResolvedValueOnce(slotsResponse(['09:30']));
        patients.bookAppointment.mockRejectedValue({ response: { status: 409, data: { message: 'This time slot is no longer available' } } });
        const user = userEvent.setup();
        show();
        await user.click(await screen.findByLabelText('09:00'));
        await user.click(screen.getByRole('button', { name: 'Request appointment' }));
        expect(await screen.findByRole('alert')).toBeTruthy();
        await screen.findByLabelText('09:30');
        expect(screen.queryByLabelText('09:00')).toBeNull();
        expect(screen.getByRole('button', { name: 'Request appointment' }).disabled).toBe(true);
        expect(patients.bookAppointment).toHaveBeenCalledTimes(1);
    });

    it('requires email verification before enabling booking', async () => {
        useAuth.mockReturnValue({ user: { ...patient, email_verified_at: null } });
        show();
        await screen.findByLabelText('09:00');
        expect(screen.queryByRole('button', { name: 'Request appointment' })).toBeNull();
        expect(screen.getByRole('link', { name: 'Verify your email' }).getAttribute('href')).toBe('/verify-email');
        expect(patients.bookAppointment).not.toHaveBeenCalled();
    });

    it('ignores an old slot response after the patient chooses another date', async () => {
        let resolveOld;
        doctors.getDoctorSlots.mockImplementationOnce(() => new Promise(resolve => { resolveOld = resolve; }))
            .mockResolvedValueOnce(slotsResponse(['11:00']));
        const user = userEvent.setup();
        show();
        const input = await screen.findByLabelText('Appointment date');
        await waitFor(() => expect(doctors.getDoctorSlots).toHaveBeenCalledTimes(1));
        await user.clear(input);
        await user.type(input, '2100-01-08');
        await screen.findByLabelText('11:00');
        resolveOld(slotsResponse(['09:00']));
        await waitFor(() => expect(screen.queryByLabelText('09:00')).toBeNull());
        expect(screen.getByLabelText('11:00')).toBeTruthy();
    });

    it('shows a search failure instead of sample doctors and allows retry', async () => {
        doctors.searchDoctorsAdvanced.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(listResponse([doctor]));
        const user = userEvent.setup();
        show('/doctors');
        expect(await screen.findByRole('alert')).toBeTruthy();
        expect(screen.queryByRole('heading', { name: 'Dr. Sara Ahmed' })).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Try again' }));
        expect(await screen.findByRole('heading', { name: 'Dr. Sara Ahmed' })).toBeTruthy();
    });
});

describe('Real appointment management', () => {
    it('displays clinic time even when the appointment offset differs from the browser timezone', async () => {
        patients.getPatientAppointments.mockResolvedValue(listResponse([
            { ...appointment, starts_at: '2026-10-08T09:00:00+01:00' },
        ], { timezone: 'Africa/Casablanca' }));
        show('/patient/appointments');
        await screen.findByRole('heading', { name: 'Dr. Sara Ahmed' });
        const time = document.querySelector('time');
        expect(time.textContent).toContain('9:00');
        expect(time.getAttribute('datetime')).toBe('2026-10-08T09:00:00+01:00');
    });

    it('cancels a pending booking only after confirmation and server success', async () => {
        patients.cancelAppointment.mockResolvedValue({});
        patients.getPatientAppointments.mockResolvedValueOnce(listResponse([appointment])).mockResolvedValueOnce(listResponse([]));
        const user = userEvent.setup();
        show('/patient/appointments');
        await user.click(await screen.findByRole('button', { name: 'Cancel appointment' }));
        expect(patients.cancelAppointment).not.toHaveBeenCalled();
        await user.click(screen.getByRole('button', { name: 'Confirm change' }));
        await screen.findByText('No appointments found for this filter.');
        expect(patients.cancelAppointment).toHaveBeenCalledWith(31);
        expect(screen.getByText('Appointment updated: Cancelled.')).toBeTruthy();
    });

    it('keeps the server status visible when cancellation fails', async () => {
        patients.cancelAppointment.mockRejectedValue({ response: { status: 409, data: { message: 'This appointment can no longer be cancelled.' } } });
        const user = userEvent.setup();
        show('/patient/appointments');
        await user.click(await screen.findByRole('button', { name: 'Cancel appointment' }));
        await user.click(screen.getByRole('button', { name: 'Confirm change' }));
        expect(await screen.findByRole('alert')).toBeTruthy();
        expect(await screen.findByText('Awaiting confirmation')).toBeTruthy();
        expect(screen.queryByText('Appointment updated: Cancelled.')).toBeNull();
    });

    it('confirms requests with the API status and offers completion only for past visits', async () => {
        doctors.updateAppointmentStatus.mockResolvedValue({});
        doctors.getDoctorAppointments.mockResolvedValueOnce(listResponse([appointment])).mockResolvedValueOnce(listResponse([
            { ...appointment, statut: 'confirmé' },
            { ...appointment, id: 32, statut: 'confirmé', is_past: true, can_be_cancelled: false },
        ]));
        const user = userEvent.setup();
        show('/doctor/appointments');
        await user.click(await screen.findByRole('button', { name: 'Confirm appointment' }));
        await user.click(screen.getByRole('button', { name: 'Confirm change' }));
        await screen.findByRole('button', { name: 'Mark completed' });
        expect(doctors.updateAppointmentStatus).toHaveBeenCalledWith(31, 'confirmé');
        expect(within(screen.getByRole('article', { name: 'Appointment 31' })).queryByRole('button', { name: 'Mark completed' })).toBeNull();
        expect(within(screen.getByRole('article', { name: 'Appointment 32' })).getByRole('button', { name: 'Mark completed' })).toBeTruthy();
    });
});
