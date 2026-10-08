import { expect, it } from 'vitest';
import { configureStore } from '@reduxjs/toolkit';
import { createAppReducer } from './store';
import { clearCredentials, logout, setCredentials } from './slices/authSlice';
import { fetchUserProfile } from './slices/userSlice';
import { fetchPatientAppointments } from './slices/patientSlice';

const first = { id: 1, role: 'patient', email_verified_at: '2100-01-01' };
const second = { ...first, id: 2 };
const profile = { user: first, patient: { id: 11 } };
function stateWithPrivateData() {
    const store = configureStore({ reducer: createAppReducer() });
    store.dispatch(setCredentials({ user: first }));
    store.dispatch(fetchUserProfile.pending('profile-request'));
    store.dispatch(fetchUserProfile.fulfilled(profile, 'profile-request'));
    store.dispatch(fetchPatientAppointments.pending('visits-request'));
    store.dispatch(fetchPatientAppointments.fulfilled([{ id: 91 }], 'visits-request'));
    return store;
}

it('clears private profile and appointment state when the session expires', () => {
    const store = stateWithPrivateData();
    store.dispatch(clearCredentials());
    expect(store.getState().auth.user).toBeNull();
    expect(store.getState().user.profile).toBeNull();
    expect(store.getState().patient.appointments).toEqual([]);
});

it('clears private state on normal sign-out and switching accounts', () => {
    const store = stateWithPrivateData();
    store.dispatch(logout.fulfilled(null, 'logout-request'));
    expect(store.getState().user.profile).toBeNull();
    store.dispatch(setCredentials({ user: second }));
    expect(store.getState().auth.user.id).toBe(2);
    expect(store.getState().patient.appointments).toEqual([]);
});

it('ignores an old profile response arriving after another user signs in', () => {
    const store = stateWithPrivateData();
    store.dispatch(fetchUserProfile.pending('old-response'));
    store.dispatch(setCredentials({ user: second }));
    store.dispatch(fetchUserProfile.fulfilled(profile, 'old-response'));
    expect(store.getState().user.profile).toBeNull();
    expect(store.getState().auth.user.id).toBe(2);
});

it('ignores an old response after the same user signs out and signs back in', () => {
    const store = stateWithPrivateData();
    store.dispatch(fetchPatientAppointments.pending('old-visits'));
    store.dispatch(clearCredentials());
    store.dispatch(setCredentials({ user: first }));
    store.dispatch(fetchPatientAppointments.fulfilled([{ id: 99 }], 'old-visits'));
    expect(store.getState().patient.appointments).toEqual([]);
});

it('keeps private state when contact information changes for the same account', () => {
    const store = stateWithPrivateData();
    store.dispatch(setCredentials({ user: { ...first, photo: 'users/new.jpg' } }));
    expect(store.getState().user.profile).toEqual(profile);
    expect(store.getState().patient.appointments).toEqual([{ id: 91 }]);
});
