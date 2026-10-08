// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import ForgotPasswordPage from '../components/auth/ForgotPasswordPage';
import api from '../api/axios';

vi.mock('../api/axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
beforeEach(() => { vi.resetAllMocks(); api.get.mockResolvedValue({}); });
afterEach(cleanup);
async function submit() {
    render(<MemoryRouter><ForgotPasswordPage /></MemoryRouter>);
    const user = userEvent.setup();
    await user.type(screen.getByLabelText('Email address'), 'recovery@preview.test');
    await user.click(screen.getByRole('button', { name: 'Send Reset Link' }));
}
it('shows conditional recovery guidance and sends the entered address', async () => {
    const message = 'If an account matches this email, a password reset link will be sent. Check your inbox and spam folder.';
    api.post.mockResolvedValue({ data: { message } });
    await submit();
    expect((await screen.findByRole('status')).textContent).toBe(message);
    expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie');
    expect(api.post).toHaveBeenCalledWith('/api/forgot-password', { email: 'recovery@preview.test' });
});
it('uses conditional guidance when an older API omits the message', async () => {
    api.post.mockResolvedValue({ data: { status: 'sent' } });
    await submit();
    expect((await screen.findByRole('status')).textContent).toContain('If an account matches this email');
});
it('explains rate limiting and keeps the form available', async () => {
    api.post.mockRejectedValue({ response: { status: 429 } });
    await submit();
    expect((await screen.findByRole('alert')).textContent).toBe('Too many reset requests. Wait a minute before trying again.');
    expect(screen.getByRole('button', { name: 'Send Reset Link' }).disabled).toBe(false);
});
it('does not expose server exception details', async () => {
    api.post.mockRejectedValue({ response: { status: 500, data: { message: 'private server detail' } } });
    await submit();
    expect((await screen.findByRole('alert')).textContent).toBe('We could not process your request. Please try again.');
});
