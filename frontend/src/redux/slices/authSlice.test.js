import { beforeEach, describe, expect, it, vi } from 'vitest';
import { configureStore } from '@reduxjs/toolkit';
import reducer, { login, register, logout, checkAuth, setCredentials } from './authSlice';
import axios from '../../api/axios';

vi.mock('../../api/axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
const store = () => configureStore({ reducer: { auth: reducer } });
const user = { id: 7, role: 'medecin', email_verified_at: '2026-10-06T00:00:00Z' };

describe('SPA session authentication', () => {
  beforeEach(() => vi.resetAllMocks());

  it('loads the nested user response without a stored token', async () => {
    axios.get.mockResolvedValue({ data: { user, role: user.role } });
    const state = store();
    await state.dispatch(checkAuth()).unwrap();
    expect(state.getState().auth.user).toEqual(user);
    expect(state.getState().auth.isAuthenticated).toBe(true);
    expect(state.getState().auth).not.toHaveProperty('token');
  });

  it('fetches CSRF before login and obtains the authoritative user', async () => {
    axios.get.mockResolvedValueOnce({}).mockResolvedValueOnce({ data: { user } });
    axios.post.mockResolvedValue({ data: { token: null } });
    const state = store();
    await state.dispatch(login({ email: 'doctor@example.com', password: 'test', isAdmin: false })).unwrap();
    expect(axios.get.mock.calls[0]).toEqual(['/sanctum/csrf-cookie']);
    expect(axios.post).toHaveBeenCalledWith('/api/login', { email: 'doctor@example.com', password: 'test' });
    expect(axios.get.mock.invocationCallOrder[0]).toBeLessThan(axios.post.mock.invocationCallOrder[0]);
    expect(state.getState().auth.user.role).toBe('medecin');
  });

  it('uses the admin login endpoint without sending the UI flag', async () => {
    axios.get.mockResolvedValueOnce({}).mockResolvedValueOnce({ data: { user: { ...user, role: 'admin' } } });
    axios.post.mockResolvedValue({});
    await store().dispatch(login({ email: 'admin@example.com', password: 'test', isAdmin: true })).unwrap();
    expect(axios.post).toHaveBeenCalledWith('/api/admin/login', { email: 'admin@example.com', password: 'test' });
  });

  it('registers using the same session and response contract', async () => {
    axios.get.mockResolvedValueOnce({}).mockResolvedValueOnce({ data: { user } });
    axios.post.mockResolvedValue({});
    const state = store();
    const account = { nom: 'Test', prenom: 'Doctor', role: 'medecin', speciality_id: 1 };
    await state.dispatch(register(account)).unwrap();
    expect(axios.post).toHaveBeenCalledWith('/api/register', account);
    expect(state.getState().auth.user).toEqual(user);
  });

  it('treats a 401 session check as signed out', async () => {
    axios.get.mockRejectedValue({ response: { status: 401 } });
    const state = store();
    state.dispatch(setCredentials({ user }));
    await state.dispatch(checkAuth()).unwrap();
    expect(state.getState().auth.user).toBeNull();
  });

  it('clears auth only after the server confirms logout', async () => {
    const state = store();
    state.dispatch(setCredentials({ user }));
    axios.post.mockRejectedValueOnce(new Error('Network failure'));
    await state.dispatch(logout());
    expect(state.getState().auth.user).toEqual(user);
    axios.post.mockResolvedValueOnce({});
    await state.dispatch(logout()).unwrap();
    expect(state.getState().auth.user).toBeNull();
  });
});
