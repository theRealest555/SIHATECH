// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import UserListPage from '../pages/admin/UserListPage';
import { getAllUsers, updateUserStatus } from '../services/adminService';
vi.mock('../services/adminService', () => ({ getAllUsers: vi.fn(), updateUserStatus: vi.fn() }));
const account = { id: 12, prenom: 'Sara', nom: 'Ahmed', email: 'sara@preview.test', role: 'medecin', status: 'actif', created_at: '2026-10-07', status_revision: 'a'.repeat(64) };
beforeEach(() => { vi.resetAllMocks(); getAllUsers.mockResolvedValue({ data: { data: [account], current_page: 1, last_page: 2, total: 11 } }); });
afterEach(cleanup);
it('renders actual names, roles and paginator fields without unsupported routes or deletion', async () => {
    render(<UserListPage />);
    await screen.findByText('Sara Ahmed');
    expect(screen.getByRole('cell', { name: 'Doctor' })).toBeTruthy();
    expect(screen.getByText('Page 1 of 2 · 11 users')).toBeTruthy();
    expect(screen.queryByRole('button', { name: /Delete/ })).toBeNull();
    expect(screen.queryByRole('link')).toBeNull();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Next' }));
    await waitFor(() => expect(getAllUsers).toHaveBeenLastCalledWith({ page: 2, search: '', role: '', status: '' }));
});
it('submits search only on request and resets pagination', async () => {
    const user = userEvent.setup(); render(<UserListPage />); await screen.findByText('Sara Ahmed');
    await user.type(screen.getByLabelText('Search users'), 'Sara');
    expect(getAllUsers).toHaveBeenCalledTimes(1);
    await user.click(screen.getByRole('button', { name: 'Search', exact: true }));
    await waitFor(() => expect(getAllUsers).toHaveBeenLastCalledWith({ page: 1, search: 'Sara', role: '', status: '' }));
});
it('requires confirmation for deactivation and reports retained history', async () => {
    const user = userEvent.setup(); render(<UserListPage />); await screen.findByText('Sara Ahmed');
    await user.click(screen.getByRole('button', { name: 'Deactivate Sara Ahmed' }));
    expect(updateUserStatus).not.toHaveBeenCalled();
    expect(screen.getByRole('dialog').textContent).toContain('bookings and billing need separate follow-up');
    updateUserStatus.mockResolvedValue({ data: { user: { ...account, status: 'inactif' } } });
    await user.type(screen.getByLabelText('Reason for deactivation'), '  Administrative access review  ');
    await user.click(screen.getByRole('button', { name: 'Confirm status change' }));
    await screen.findByText('Account deactivated. Existing access was revoked; account history is preserved.');
    expect(updateUserStatus).toHaveBeenCalledWith(12, 'inactif', account.status_revision, 'Administrative access review');
});
it('keeps the confirmation and reports failed status changes', async () => {
    updateUserStatus.mockRejectedValue({ response: { data: { message: 'Change rejected.' } } });
    const user = userEvent.setup(); render(<UserListPage />); await screen.findByText('Sara Ahmed');
    await user.click(screen.getByRole('button', { name: 'Deactivate Sara Ahmed' }));
    await user.type(screen.getByLabelText('Reason for deactivation'), 'Administrative access review');
    await user.click(screen.getByRole('button', { name: 'Confirm status change' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Change rejected.');
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByLabelText('Reason for deactivation').value).toBe('Administrative access review');
});
it('clears a stale decision and requires reloading before another confirmation', async () => {
    const user = userEvent.setup();
    updateUserStatus.mockRejectedValue({ response: { status: 409, data: { message: 'Account access changed. Reload users.' } } });
    render(<UserListPage />); await screen.findByText('Sara Ahmed');
    await user.click(screen.getByRole('button', { name: 'Deactivate Sara Ahmed' }));
    await user.type(screen.getByLabelText('Reason for deactivation'), 'Administrative access review');
    await user.click(screen.getByRole('button', { name: 'Confirm status change' }));
    await screen.findByRole('alert');
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Deactivate Sara Ahmed' })).toBeNull();
    const newer = { ...account, status: 'inactif', status_revision: 'b'.repeat(64) };
    getAllUsers.mockResolvedValue({ data: { data: [newer], last_page: 1, total: 1 } });
    await user.click(screen.getByRole('button', { name: 'Reload users' }));
    await user.click(await screen.findByRole('button', { name: 'Activate Sara Ahmed' }));
    updateUserStatus.mockResolvedValue({ data: { user: { ...newer, status: 'actif' } } });
    await user.click(screen.getByRole('button', { name: 'Confirm status change' }));
    await waitFor(() => expect(updateUserStatus).toHaveBeenLastCalledWith(12, 'actif', newer.status_revision, undefined));
});
it('requires a nonblank reason and clears it when the decision is cancelled', async () => {
    const user = userEvent.setup(); render(<UserListPage />); await screen.findByText('Sara Ahmed');
    await user.click(screen.getByRole('button', { name: 'Deactivate Sara Ahmed' }));
    const confirm = screen.getByRole('button', { name: 'Confirm status change' });
    expect(confirm.disabled).toBe(true);
    await user.type(screen.getByLabelText('Reason for deactivation'), '   ');
    expect(confirm.disabled).toBe(true);
    await user.click(confirm);
    expect(updateUserStatus).not.toHaveBeenCalled();
    await user.type(screen.getByLabelText('Reason for deactivation'), 'Access review');
    expect(confirm.disabled).toBe(false);
    expect(screen.getByLabelText('Reason for deactivation').maxLength).toBe(500);
    await user.click(screen.getByRole('button', { name: 'Keep current status' }));
    await user.click(screen.getByRole('button', { name: 'Deactivate Sara Ahmed' }));
    expect(screen.getByLabelText('Reason for deactivation').value).toBe('');
});
it('disables changes when the server provides no usable revision', async () => {
    getAllUsers.mockResolvedValue({ data: { data: [{ ...account, status_revision: undefined }], last_page: 1, total: 1 } });
    render(<UserListPage />);
    expect((await screen.findByRole('button', { name: 'Deactivate Sara Ahmed' })).disabled).toBe(true);
    expect(updateUserStatus).not.toHaveBeenCalled();
});
it('reports an unchanged response without claiming a new revocation', async () => {
    const user = userEvent.setup();
    updateUserStatus.mockResolvedValue({ data: { changed: false, user: { ...account, status: 'inactif' } } });
    render(<UserListPage />); await screen.findByText('Sara Ahmed');
    await user.click(screen.getByRole('button', { name: 'Deactivate Sara Ahmed' }));
    await user.type(screen.getByLabelText('Reason for deactivation'), 'Access review');
    await user.click(screen.getByRole('button', { name: 'Confirm status change' }));
    await screen.findByText('Account status is already current. No access changes were made.');
    expect(screen.queryByText('Account deactivated. Existing access was revoked; account history is preserved.')).toBeNull();
    expect(screen.queryByRole('dialog')).toBeNull();
});
