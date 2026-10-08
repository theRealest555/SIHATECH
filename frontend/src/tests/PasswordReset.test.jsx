// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import ResetPasswordPage from '../components/auth/ResetPasswordPage';
import api from '../api/axios';

vi.mock('../api/axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
beforeEach(() => { vi.resetAllMocks(); api.get.mockResolvedValue({}); });
afterEach(cleanup);
function show() {
    render(<MemoryRouter initialEntries={['/reset-password/fixture-token?email=reset%2Btest%40preview.test']}><Routes><Route path="/reset-password/:token" element={<ResetPasswordPage />} /></Routes></MemoryRouter>);
}
async function submit(confirmation = 'Reset-test-2026!') {
    show();
    const user = userEvent.setup();
    await user.type(screen.getByLabelText('New Password'), 'Reset-test-2026!');
    await user.type(screen.getByLabelText('Confirm New Password'), confirmation);
    await user.click(screen.getByRole('button', { name: 'Reset Password' }));
}
it('prefills the link email, sends its token and clears passwords after success', async () => {
    api.post.mockResolvedValue({ data: {} });
    await submit();
    expect((await screen.findByRole('status')).textContent).toContain('Sign in with your new password');
    expect(api.post).toHaveBeenCalledWith('/api/reset-password', { email: 'reset+test@preview.test', token: 'fixture-token', password: 'Reset-test-2026!', password_confirmation: 'Reset-test-2026!' });
    expect(screen.getByLabelText('New Password').value).toBe('');
    expect(screen.getByLabelText('Confirm New Password').value).toBe('');
    expect(screen.getByRole('button', { name: 'Password reset' }).disabled).toBe(true);
    expect(screen.getByRole('link', { name: 'Back to Sign in' }).getAttribute('href')).toBe('/login');
});
it('rejects password mismatch without making a request', async () => {
    await submit('Mismatch-2026!');
    expect(screen.getByRole('alert').textContent).toBe('Passwords do not match.');
    expect(api.post).not.toHaveBeenCalled();
});
it('explains expired links and provides recovery navigation', async () => {
    api.post.mockRejectedValue({ response: { status: 422, data: { errors: { email: ['This reset link is invalid or expired. Request a new link.'] } } } });
    await submit();
    expect((await screen.findByRole('alert')).textContent).toContain('invalid or expired');
    expect(screen.getByRole('link', { name: 'Request a new reset link' }).getAttribute('href')).toBe('/forgot-password');
});
it('explains rate limits while allowing a later retry', async () => {
    api.post.mockRejectedValue({ response: { status: 429 } });
    await submit();
    expect((await screen.findByRole('alert')).textContent).toContain('Wait a minute');
    expect(screen.getByRole('button', { name: 'Reset Password' }).disabled).toBe(false);
});
it('hides arbitrary server failures', async () => {
    api.post.mockRejectedValue({ response: { status: 500, data: { message: 'private details' } } });
    await submit();
    expect((await screen.findByRole('alert')).textContent).toBe('We could not reset your password. Try again or request a new link.');
});
