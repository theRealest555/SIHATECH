// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import PatientProfile from '../pages/patient/Profile';
import DoctorProfile from '../pages/doctor/Profile';
import { updateUserProfile } from '../redux/slices/userSlice';
const fixture = vi.hoisted(() => ({ state: null, dispatch: vi.fn() }));
vi.mock('react-redux', () => ({ useSelector: selector => selector(fixture.state), useDispatch: () => fixture.dispatch }));
vi.mock('../redux/slices/userSlice', async original => ({ ...await original(), updateUserProfile: vi.fn(data => ({ type: 'save-profile', payload: data })) }));
vi.mock('react-toastify', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
const snapshot = role => ({ user: { id: 1, nom: 'Patient', prenom: 'Preview', email: 'preview@example.test', role, email_verified_at: '2026-10-01', date_de_naissance: '1990-01-02T00:00:00.000000Z', sexe: 'homme', telephone: '0611111111' }, patient: { medecin_favori_id: null }, doctor: { id: 1, speciality_id: 2, description: 'Practice', horaires: { lundi: ['09:00-12:00'] }, is_verified: true }, profile_revision: 'original-revision' });
beforeEach(() => { vi.clearAllMocks(); fixture.dispatch.mockReturnValue({ unwrap: () => Promise.reject({ message: 'This profile changed in another session.', errors: {} }) }); });
afterEach(cleanup);
for (const [role, Component] of [['patient', PatientProfile], ['medecin', DoctorProfile]]) {
    it(`${role} retains a draft and its revision when the profile refreshes during editing`, async () => {
        const user = userEvent.setup(); const profile = snapshot(role);
        fixture.state = { auth: { user: profile.user }, user: { profile, status: 'succeeded', error: null }, doctor: { specialities: [{ id: 2, nom: 'Cardiology' }], documents: [], doctors: [], status: 'succeeded' }, patient: { appointments: [], status: 'succeeded', error: null } };
        const view = render(<MemoryRouter><Component /></MemoryRouter>);
        await user.click(screen.getByRole('button', { name: 'Edit Profile' }));
        expect(updateUserProfile).not.toHaveBeenCalled();
        const name = screen.getByLabelText('Last Name'); await user.clear(name); await user.type(name, 'Local draft');
        fixture.state.user.profile = { ...profile, user: { ...profile.user, nom: 'Newer server name', photo: 'users/refresh.png' }, profile_revision: 'newer-revision' };
        view.rerender(<MemoryRouter><Component /></MemoryRouter>);
        expect(name.value).toBe('Local draft');
        await user.click(screen.getByRole('button', { name: 'Save Changes' }));
        await waitFor(() => expect(updateUserProfile).toHaveBeenCalled());
        expect(updateUserProfile.mock.calls[0][0]).toMatchObject({ nom: 'Local draft', expected_profile_revision: 'original-revision' });
        if (role === 'medecin') expect(updateUserProfile.mock.calls[0][0]).not.toHaveProperty('horaires');
        fixture.state.user.error = 'This profile changed in another session.';
        view.rerender(<MemoryRouter><Component /></MemoryRouter>);
        expect(name.value).toBe('Local draft');
        await user.click(screen.getByRole('button', { name: 'Reload profile and discard draft' }));
        await waitFor(() => expect(name.value).toBe('Newer server name'));
    });
}
