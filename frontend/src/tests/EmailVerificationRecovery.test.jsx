// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import VerifyEmail from '../pages/auth/VerifyEmail';
import { resendVerificationEmail } from '../services/authService';

const { fetchUser } = vi.hoisted(() => ({ fetchUser: vi.fn() }));
vi.mock('../hooks/useAuth', () => ({ useAuth: () => ({ user: { email: 'verification@preview.test', email_verified_at: null }, fetchUser }) }));
vi.mock('../services/authService', () => ({ resendVerificationEmail: vi.fn() }));
beforeEach(() => { vi.resetAllMocks(); });
afterEach(cleanup);
function show(query = '') {
    render(<MemoryRouter initialEntries={['/verify-email'+query]}><VerifyEmail /></MemoryRouter>);
}
it('explains invalid links without echoing arbitrary query text', () => {
    show('?error=invalid-link');
    expect(screen.getByRole('status').textContent).toContain('invalid or expired');
});
it('reports sent mail only after an explicit sent status', async () => {
    resendVerificationEmail.mockResolvedValue({ data: { status: 'verification-link-sent' } });
    show();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Resend email' }));
    expect(screen.getByRole('status').textContent).toContain('Check your inbox and spam folder');
});
it('refreshes an already verified account instead of claiming email was sent', async () => {
    resendVerificationEmail.mockResolvedValue({ data: { status: 'already-verified' } });
    fetchUser.mockResolvedValue({ email_verified_at: '2026-10-07' });
    show();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Resend email' }));
    expect(fetchUser).toHaveBeenCalledOnce();
    expect(screen.getByRole('status').textContent).toContain('already verified');
});
it('does not claim delivery for a malformed response', async () => {
    resendVerificationEmail.mockResolvedValue({ data: {} });
    show();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Resend email' }));
    expect(screen.getByRole('status').textContent).toContain('Unable to confirm delivery');
});
it.each([[429, 'Wait a minute'], [503, 'try again later'], [500, 'Please retry']])('shows recovery for HTTP %s without server details', async (status, expected) => {
    resendVerificationEmail.mockRejectedValue({ response: { status, data: { message: 'private transport details' } } });
    show();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Resend email' }));
    expect(screen.getByRole('status').textContent).toContain(expected);
    expect(screen.getByRole('status').textContent).not.toContain('private transport details');
    expect(screen.getByRole('button', { name: 'Resend email' }).disabled).toBe(false);
});
