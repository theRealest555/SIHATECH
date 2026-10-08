import { beforeEach, expect, it, vi } from 'vitest';
import { configureStore } from '@reduxjs/toolkit';
import auth, { setCredentials } from './authSlice';
import user, { fetchUserProfile, updateUserPassword, updateUserProfile, uploadUserPhoto } from './userSlice';
import axios from '../../api/axios';

vi.mock('../../api/axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
beforeEach(() => vi.resetAllMocks());
const current = { id: 7, role: 'patient', email: 'old@example.test', email_verified_at: '2100-01-01' };
function store(account = current) {
    const state = configureStore({ reducer: { auth, user } });
    state.dispatch(setCredentials({ user: account }));
    return state;
}

it('immediately updates the authentication gate when a profile email change clears verification', async () => {
    const state = store();
    const updated = { ...current, email: 'new@example.test', email_verified_at: null };
    axios.put.mockResolvedValue({ data: { user: updated, email_verification_required: true } });
    const result = await state.dispatch(updateUserProfile({ email: updated.email, current_password: 'test-password' })).unwrap();
    expect(result.email_verification_required).toBe(true);
    expect(state.getState().auth.isEmailVerified).toBe(false);
    expect(state.getState().auth.user).toEqual(updated);
    expect(state.getState().user.profile.user).toEqual(updated);
    expect(JSON.stringify(state.getState())).not.toContain('test-password');
    expect(axios.put.mock.calls[0][0]).toBe('/api/patient/profile');
});

it('keeps doctor authentication metadata while updating contact details', async () => {
    const account = { ...current, role: 'medecin', doctor: { id: 19, is_verified: true } };
    const state = store(account);
    axios.put.mockResolvedValue({ data: { user: { ...current, role: 'medecin', prenom: 'Sara' } } });
    await state.dispatch(updateUserProfile({ prenom: 'Sara' })).unwrap();
    expect(state.getState().auth.user.doctor).toEqual(account.doctor);
    expect(state.getState().auth.user.prenom).toBe('Sara');
    expect(axios.put.mock.calls[0][0]).toBe('/api/doctor/profile');
});

it('preserves the old identity on a rejected change and displays the actionable password error', async () => {
    const state = store();
    axios.put.mockRejectedValue({ response: { data: { message: 'Invalid data.', errors: { current_password: ['Confirm your current password.'] } } } });
    await expect(state.dispatch(updateUserProfile({ email: 'new@example.test' })).unwrap()).rejects.toMatchObject({ message: 'Confirm your current password.' });
    expect(state.getState().auth.user).toEqual(current);
    expect(state.getState().auth.isEmailVerified).toBe(true);
});

it('uploads multipart data and keeps the photo path consistent in profile and authentication state', async () => {
    const state = store();
    state.dispatch(fetchUserProfile.fulfilled({ user: current }, 'initial-profile'));
    const updated = { ...current, photo: 'users/new.jpg' };
    axios.post.mockResolvedValue({ data: { user: updated, path: updated.photo, photo_url: 'https://api.example.test/storage/users/new.jpg' } });
    await state.dispatch(uploadUserPhoto(new Blob(['photo'], { type: 'image/png' }))).unwrap();
    expect(axios.post.mock.calls[0][0]).toBe('/api/patient/profile/photo');
    expect(axios.post.mock.calls[0][1]).toBeInstanceOf(FormData);
    expect(axios.post.mock.calls[0]).toHaveLength(2);
    expect(state.getState().auth.user.photo).toBe('users/new.jpg');
    expect(state.getState().user.profile.user.photo).toBe('users/new.jpg');
});

it('preserves the existing photo when the backend rejects the file', async () => {
    const account = { ...current, photo: 'users/original.jpg' };
    const state = store(account);
    state.dispatch(fetchUserProfile.fulfilled({ user: account }, 'initial-profile'));
    axios.post.mockRejectedValue({ response: { data: { errors: { photo: ['Choose a JPEG, PNG or WebP image.'] } } } });
    await expect(state.dispatch(uploadUserPhoto(new Blob(['invalid']))).unwrap()).rejects.toMatchObject({ message: 'Choose a JPEG, PNG or WebP image.' });
    expect(state.getState().auth.user.photo).toBe(account.photo);
    expect(state.getState().user.profile.user.photo).toBe(account.photo);
});

it('keeps the current browser identity after a successful password change', async () => {
    const state = store();
    axios.put.mockResolvedValue({ data: { reauthentication_required: false, api_tokens_revoked: true } });
    await state.dispatch(updateUserPassword({ current_password: 'old', password: 'new' })).unwrap();
    expect(state.getState().auth.user).toEqual(current);
});

it('clears authentication when the password response requires a new sign-in', async () => {
    const state = store();
    axios.put.mockResolvedValue({ data: { reauthentication_required: true } });
    await state.dispatch(updateUserPassword({ current_password: 'old', password: 'new' })).unwrap();
    expect(state.getState().auth.user).toBeNull();
});
