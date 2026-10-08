// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import Navbar from '../components/layouts/Navbar';
import { useAuth } from '../hooks/useAuth';

vi.mock('../hooks/useAuth', () => ({ useAuth: vi.fn() }));
afterEach(cleanup);
function show(role) {
    useAuth.mockReturnValue({ user: role ? { role, prenom: 'Test', nom: 'Account' } : null, logout: vi.fn().mockResolvedValue(true), loading: false });
    render(<MemoryRouter><Navbar /></MemoryRouter>);
}
it.each([['patient', '/patient/appointments', '/patient/profile'], ['medecin', '/doctor/appointments', '/doctor/profile']])('keeps the %s workspace navigation connected to supported routes', (role, appointments, profile) => {
    show(role);
    expect(screen.getByRole('link', { name: 'Appointments' }).getAttribute('href')).toBe(appointments);
    expect(screen.getByRole('link', { name: 'Profile' }).getAttribute('href')).toBe(profile);
    expect(screen.queryByRole('link', { name: 'Users' })).toBeNull();
});
it('keeps administrator controls grouped separately from patient account tools', () => {
    show('admin');
    expect(screen.getByRole('link', { name: 'Users' }).getAttribute('href')).toBe('/admin/users');
    expect(screen.getByRole('link', { name: 'Doctor verification' }).getAttribute('href')).toBe('/admin/doctors-verification');
    expect(screen.queryByRole('link', { name: 'Profile' })).toBeNull();
});
it('closes the mobile menu with Escape and returns focus to its toggle', async () => {
    show('admin');
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'Menu' }));
    expect(screen.getByRole('button', { name: 'Close menu' }).getAttribute('aria-expanded')).toBe('true');
    await user.click(screen.getByRole('link', { name: 'Users' }));
    expect(screen.getByRole('button', { name: 'Menu' }).getAttribute('aria-expanded')).toBe('false');
    await user.click(screen.getByRole('button', { name: 'Menu' }));
    await user.keyboard('{Escape}');
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Menu' }));
    expect(document.activeElement.getAttribute('aria-expanded')).toBe('false');
});
