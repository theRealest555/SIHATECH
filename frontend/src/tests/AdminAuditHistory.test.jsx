// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AuditLogPage from '../pages/admin/AuditLogPage';
import * as service from '../services/adminService';
vi.mock('../services/adminService', () => ({ getAuditLogs: vi.fn() }));
const entry = { id: 1, created_at: '2026-10-07T10:00:00+00:00', actor: { id: 3, name: 'Preview Reviewer' }, action: 'rejected_document', target: { type: 'Document', id: 12 }, details: { reason: 'Expired credential' } };
const response = (rows = [entry], page = 1, last = 1) => ({ data: { data: rows, meta: { current_page: page, last_page: last, total: rows.length, timezone: 'UTC' } } });
beforeEach(() => { vi.resetAllMocks(); service.getAuditLogs.mockResolvedValue(response()); });
afterEach(cleanup);
it('shows recorded access transitions and revocation without hiding a recorded reason', async () => {
    service.getAuditLogs.mockResolvedValue(response([{ ...entry, action: 'updated_user_status', target: { type: 'User', id: 12 }, details: { transition: 'Account status: Active → Inactive', access_revoked: true, reason: 'Recorded reason' } }]));
    render(<AuditLogPage />);
    await screen.findByText('Account status: Active → Inactive');
    expect(screen.getByText('Existing sessions and API tokens revoked.')).toBeTruthy();
    expect(screen.getByText('Recorded reason')).toBeTruthy();
    expect(screen.queryByText('No decision details recorded')).toBeNull();
});
it('shows approval restoration without claiming revoked access was restored', async () => {
    service.getAuditLogs.mockResolvedValue(response([{ ...entry, action: 'updated_admin_status', details: { transition: 'Administrator approval: Not approved → Approved', access_revoked: false } }]));
    render(<AuditLogPage />);
    await screen.findByText('Administrator approval: Not approved → Approved');
    expect(screen.queryByText('Existing sessions and API tokens revoked.')).toBeNull();
});
it('filters attendance decisions and renders the appointment status transition', async () => {
    service.getAuditLogs.mockResolvedValue(response([{ ...entry, action: 'marked_appointment_no_show', target: { type: 'Appointment', id: 42 }, details: { transition: 'Confirmed → No-show' } }]));
    const user = userEvent.setup(); render(<AuditLogPage />);
    await screen.findByText('Confirmed → No-show');
    expect(screen.getByText('Appointment #42')).toBeTruthy();
    await user.selectOptions(screen.getByLabelText('Action'), 'marked_appointment_no_show');
    await user.click(screen.getByRole('button', { name: 'Filter history' }));
    await waitFor(() => expect(service.getAuditLogs.mock.calls.at(-1)[0]).toMatchObject({ action: 'marked_appointment_no_show', page: 1 }));
});
it('renders recorded actor, target and reason without invented IP addresses or system labels', async () => {
    service.getAuditLogs.mockResolvedValue(response([entry, { ...entry, id: 2, actor: null, details: {} }]));
    render(<AuditLogPage />);
    await screen.findByText('Preview Reviewer');
    expect(screen.getByText('Expired credential')).toBeTruthy();
    expect(screen.getByText('Actor account deleted or unavailable')).toBeTruthy();
    expect(screen.queryByText('IP Address')).toBeNull();
    expect(screen.queryByText('System')).toBeNull();
});
it('applies filters on submission and resets pagination', async () => {
    const user = userEvent.setup();
    service.getAuditLogs.mockResolvedValueOnce(response([entry], 1, 2)).mockResolvedValueOnce(response([entry], 2, 2));
    render(<AuditLogPage />);
    await user.click(await screen.findByRole('button', { name: 'Next page' }));
    await screen.findByText('Page 2 of 2');
    await user.type(screen.getByLabelText('Actor user ID'), '3');
    expect(service.getAuditLogs).toHaveBeenCalledTimes(2);
    await user.selectOptions(screen.getByLabelText('Action'), 'approved_document');
    await user.click(screen.getByRole('button', { name: 'Filter history' }));
    await waitFor(() => expect(service.getAuditLogs.mock.calls.at(-1)[0]).toEqual({ user_id: '3', action: 'approved_document', page: 1, per_page: 25 }));
    await user.click(screen.getByRole('button', { name: 'Clear filters' }));
    await waitFor(() => expect(service.getAuditLogs.mock.calls.at(-1)[0]).toEqual({ page: 1, per_page: 25 }));
});
it('supports retry and empty results', async () => {
    const user = userEvent.setup(); service.getAuditLogs.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(response([]));
    render(<AuditLogPage />);
    await user.click(await screen.findByRole('button', { name: 'Retry' }));
    await screen.findByText('No recorded actions match these filters.');
});
it('ignores stale responses after a new filter is applied', async () => {
    const user = userEvent.setup(); let complete;
    service.getAuditLogs.mockReturnValueOnce(new Promise(resolve => { complete = resolve; })).mockResolvedValueOnce(response([]));
    render(<AuditLogPage />);
    await user.selectOptions(screen.getByLabelText('Action'), 'approved_document');
    await user.click(screen.getByRole('button', { name: 'Filter history' }));
    await screen.findByText('No recorded actions match these filters.');
    complete(response());
    await waitFor(() => expect(screen.queryByText('Preview Reviewer')).toBeNull());
    expect(service.getAuditLogs.mock.calls[0][1].signal.aborted).toBe(true);
});
it('renders recorded decision text as text instead of HTML', async () => {
    service.getAuditLogs.mockResolvedValue(response([{ ...entry, details: { reason: '<img src=x onerror=alert(1)>' } }]));
    render(<AuditLogPage />);
    await screen.findByText('<img src=x onerror=alert(1)>');
    expect(document.querySelector('main img')).toBeNull();
});
