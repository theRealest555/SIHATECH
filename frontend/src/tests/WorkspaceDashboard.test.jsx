// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import DoctorDashboard from '../pages/doctor/DashboardPage';
import PatientDashboard from '../pages/patient/DashboardPage';
import * as doctors from '../services/doctorService';
import * as patients from '../services/patientService';

vi.mock('../services/doctorService', () => ({ getDoctorAppointments: vi.fn(), getDoctorDocuments: vi.fn() }));
vi.mock('../services/patientService', () => ({ getPatientAppointments: vi.fn() }));
const visit = { id: 31, doctor_name: 'Sara Ahmed', patient_name: 'Amine Patient', speciality: 'Cardiology', starts_at: '2100-01-07T09:00:00+01:00', statut: 'en_attente' };
const response = (rows = [], total = rows.length) => ({ data: { data: rows, meta: { total, timezone: 'Africa/Casablanca' } } });
beforeEach(() => {
    vi.resetAllMocks();
    doctors.getDoctorAppointments.mockResolvedValue(response([visit], 9));
    patients.getPatientAppointments.mockResolvedValue(response([visit], 9));
    doctors.getDoctorDocuments.mockResolvedValue({ data: { documents: [{ status: 'pending' }, { status: 'rejected' }] } });
});
afterEach(cleanup);
const show = component => render(<MemoryRouter>{component}</MemoryRouter>);

it('loads the patient’s next visits and the server total, with working account links', async () => {
    show(<PatientDashboard />);
    await screen.findByText('Sara Ahmed');
    expect(screen.getByRole('heading', { name: 'Upcoming appointments (9)' })).toBeTruthy();
    expect(screen.getByText('Awaiting confirmation')).toBeTruthy();
    expect(screen.getByText(/Clinic time: Africa\/Casablanca/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Manage appointments' }).getAttribute('href')).toBe('/patient/appointments');
    expect(screen.queryByText('Health Records')).toBeNull();
    expect(patients.getPatientAppointments.mock.calls[0][0]).toEqual({ period: 'upcoming', per_page: 3 });
    expect(doctors.getDoctorAppointments).not.toHaveBeenCalled();
});
it('shows the doctor’s patient names and actual credential counts', async () => {
    show(<DoctorDashboard />);
    await screen.findByText('Amine Patient');
    expect(screen.getByText('Approved:').textContent).toBe('Approved: 0');
    expect(screen.getByText('Awaiting review:').textContent).toBe('Awaiting review: 1');
    expect(screen.getByText('Rejected:').textContent).toBe('Rejected: 1');
    expect(screen.queryByRole('link', { name: /Patient Records/ })).toBeNull();
    expect(screen.getByRole('link', { name: 'Manage appointments' }).getAttribute('href')).toBe('/doctor/appointments');
});
it('renders a truthful empty state for a new account', async () => {
    patients.getPatientAppointments.mockResolvedValue(response());
    show(<PatientDashboard />);
    await screen.findByText('No upcoming appointments.');
    expect(screen.getByRole('heading', { name: 'Upcoming appointments (0)' })).toBeTruthy();
});
it('keeps successful appointments visible when credential loading fails', async () => {
    doctors.getDoctorDocuments.mockRejectedValue(new Error('offline'));
    show(<DoctorDashboard />);
    await screen.findByText('Amine Patient');
    expect((await screen.findByRole('alert')).textContent).toBe('Unable to load document status.');
    expect(screen.queryByText('Approved:')).toBeNull();
});
it('offers refresh after an error instead of displaying sample appointment numbers', async () => {
    patients.getPatientAppointments.mockRejectedValueOnce(new Error('offline')).mockResolvedValue(response());
    show(<PatientDashboard />);
    await screen.findByRole('alert');
    expect(screen.queryByRole('heading', { name: /Upcoming appointments \(/ })).toBeNull();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Refresh dashboard' }));
    await screen.findByText('No upcoming appointments.');
    expect(patients.getPatientAppointments).toHaveBeenCalledTimes(2);
});
it('aborts old requests and ignores a late response when refreshing', async () => {
    let finish;
    patients.getPatientAppointments.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; })).mockResolvedValue(response());
    show(<PatientDashboard />);
    await userEvent.setup().click(screen.getByRole('button', { name: 'Refresh dashboard' }));
    await screen.findByText('No upcoming appointments.');
    finish(response([visit], 99));
    await waitFor(() => expect(patients.getPatientAppointments.mock.calls[0][1].signal.aborted).toBe(true));
    expect(screen.queryByText('Sara Ahmed')).toBeNull();
});
