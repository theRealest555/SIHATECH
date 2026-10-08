// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import { configureStore } from '@reduxjs/toolkit';
import { Provider } from 'react-redux';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../contexts/AuthContext';
import { useAuth } from '../hooks/useAuth';
import { createAppReducer } from '../redux/store';
import axios from '../api/axios';

vi.mock('../api/axios', () => ({ default: { get: vi.fn(), post: vi.fn(), interceptors: { response: { use: vi.fn(), eject: vi.fn() } } } }));
const account = { id: 1, role: 'patient', email_verified_at: '2100-01-01' };
beforeEach(() => {
    vi.resetAllMocks();
    axios.get.mockResolvedValue({ data: { user: account } });
    axios.interceptors.response.use.mockReturnValue(41);
});
afterEach(cleanup);
function Identity() {
    const { user } = useAuth();
    return <p>{user ? user.role : 'Signed out'}</p>;
}
function show() {
    const store = configureStore({ reducer: createAppReducer() });
    const view = render(<Provider store={store}><MemoryRouter><AuthProvider><Identity /></AuthProvider></MemoryRouter></Provider>);
    return { store, ...view };
}

it('clears the authenticated identity on a revoked-session response and removes its interceptor on unmount', async () => {
    const { store, unmount } = show();
    await screen.findByText('patient');
    const reject = axios.interceptors.response.use.mock.calls[0][1];
    const error = { response: { status: 401 } };
    await expect(reject(error)).rejects.toBe(error);
    await screen.findByText('Signed out');
    expect(store.getState().auth.user).toBeNull();
    unmount();
    expect(axios.interceptors.response.eject).toHaveBeenCalledWith(41);
});

it('keeps the session on a validation error', async () => {
    const { store } = show();
    await screen.findByText('patient');
    const reject = axios.interceptors.response.use.mock.calls[0][1];
    const error = { response: { status: 422 } };
    await expect(reject(error)).rejects.toBe(error);
    await waitFor(() => expect(store.getState().auth.user).toEqual(account));
});
