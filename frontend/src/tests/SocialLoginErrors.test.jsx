// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import LoginPage from '../pages/auth/Login';
import { socialAuthError } from '../utils/socialAuthErrors';
import { getSocialProviders } from '../services/authService';

vi.mock('../hooks/useAuth', () => ({ useAuth: () => ({ login: vi.fn(), authError: '', loading: false }) }));
vi.mock('../services/authService', () => ({ getSocialProviders: vi.fn() }));
beforeEach(() => { vi.resetAllMocks(); getSocialProviders.mockReturnValue(new Promise(() => {})); });
afterEach(cleanup);
function CurrentLocation() {
    const location = useLocation();
    return <output aria-label="Current location">{location.pathname}{location.search}</output>;
}
function show(query) {
    render(<MemoryRouter initialEntries={['/login'+query]}><LoginPage /><CurrentLocation /></MemoryRouter>);
}
it.each(['account_link_required', 'session_expired', 'cancelled'])('shows actionable recovery for %s', code => {
    show('?error='+code);
    expect(screen.getByRole('alert').textContent).toContain(socialAuthError(code));
    expect(screen.getByRole('link', { name: 'Forgot your password?' }).getAttribute('href')).toBe('/forgot-password');
    expect(screen.getByRole('button', { name: 'Sign in' }).disabled).toBe(false);
});
it('does not echo arbitrary error text or inherited object keys', () => {
    const injected = '<img src=x onerror=alert(1)>';
    show('?error='+encodeURIComponent(injected));
    expect(screen.getByRole('alert').textContent).toContain(socialAuthError('authentication_failed'));
    expect(screen.queryByText(injected)).toBeNull();
    expect(socialAuthError('__proto__')).toBe(socialAuthError('authentication_failed'));
});
it('dismisses only the OAuth error and keeps unrelated query parameters', async () => {
    const user = userEvent.setup();
    show('?error=session_expired&next=appointments');
    await user.click(screen.getByRole('button', { name: 'Dismiss sign-in message' }));
    expect(screen.queryByRole('alert')).toBeNull();
    expect(screen.getByLabelText('Current location').textContent).toBe('/login?next=appointments');
});
it('does not show a failure on ordinary login', () => {
    show('');
    expect(screen.queryByRole('alert')).toBeNull();
});
it('disables unavailable providers while keeping email sign-in usable', async () => {
    getSocialProviders.mockResolvedValue({ data: { data: { google: { available: false }, facebook: { available: false } } } });
    show('');
    await screen.findByText('Social sign-in is currently unavailable. Use email and password.');
    expect(screen.getByRole('button', { name: 'Google' }).disabled).toBe(true);
    expect(screen.getByRole('button', { name: 'Facebook' }).disabled).toBe(true);
    expect(screen.getByRole('button', { name: 'Sign in' }).disabled).toBe(false);
});
it('enables configured options and explains Facebook account restrictions', async () => {
    getSocialProviders.mockResolvedValue({ data: { data: { google: { available: true }, facebook: { available: true, existing_accounts_only: true } } } });
    show('');
    await screen.findByText('Facebook sign-in is available for accounts already linked to Facebook.');
    expect(screen.getByRole('button', { name: 'Google' }).disabled).toBe(false);
    expect(screen.getByRole('button', { name: 'Facebook' }).disabled).toBe(false);
});
it('retries failed availability checks without blocking email sign-in', async () => {
    getSocialProviders.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce({ data: { data: { google: { available: true }, facebook: { available: false } } } });
    const user = userEvent.setup(); show('');
    await user.click(await screen.findByRole('button', { name: 'Retry social options' }));
    await screen.findByRole('button', { name: 'Google' });
    await screen.findByText('Or continue with');
    expect(getSocialProviders).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('button', { name: 'Sign in' }).disabled).toBe(false);
});
it('rejects malformed availability instead of enabling providers', async () => {
    getSocialProviders.mockResolvedValue({ data: { data: { google: { available: 'true' }, facebook: { available: false } } } });
    show('');
    await screen.findByRole('button', { name: 'Retry social options' });
    expect(screen.getByRole('button', { name: 'Google' }).disabled).toBe(true);
});
