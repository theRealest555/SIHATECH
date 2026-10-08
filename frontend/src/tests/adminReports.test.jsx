// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ReportsPage from '../pages/admin/ReportsPage';
import * as api from '../services/adminService';
vi.mock('../services/adminService', () => ({ getAdminReport: vi.fn(), exportAdminReport: vi.fn() }));
const range = { start_date: '2026-10-01', end_date: '2026-10-03', timezone: 'Africa/Casablanca' };
const financial = { range, payments: { total: 3, by_status: { completed: 2, failed: 1 }, by_currency: [{ currency: 'MAD', transactions: 1, completed_amount: '199.00', subscription_amount: '199.00' }, { currency: 'USD', transactions: 1, completed_amount: '10.00', subscription_amount: '0.00' }] }, subscriptions: { created_in_range: 1, cancelled_in_range: 0, active_now: 1, as_of: '2026-10-07T12:00:00Z' } };
const appointments = { range, appointments: { total: 2, by_status: { 'terminé': 1, no_show: 1 } }, specialities: [{ name: 'Cardiology', count: 2 }], daily: [{ date: '2026-10-01', count: 2 }, { date: '2026-10-02', count: 0 }], hourly: [{ hour: '09:00', count: 2 }] };
const response = data => ({ data: { data } });
beforeEach(() => { vi.resetAllMocks(); api.getAdminReport.mockResolvedValue(response(financial)); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });
it('shows separate currency amounts and a clearly labelled current subscription snapshot', async () => {
    render(<ReportsPage />); await screen.findByText('3 payment records');
    expect(screen.getByText('MAD')).toBeTruthy(); expect(screen.getByText('USD')).toBeTruthy();
    expect(screen.getByText(/Current snapshot|This current snapshot/)).toBeTruthy();
    expect(screen.getByText(/Refunds, fees, settlement/)).toBeTruthy();
    expect(screen.queryByText('Total revenue')).toBeNull();
});
it('applies the selected report and dates only on submission', async () => {
    const user = userEvent.setup(); render(<ReportsPage />); await screen.findByText('3 payment records');
    await user.selectOptions(screen.getByLabelText('Report type'), 'appointments');
    await user.type(screen.getByLabelText('Start date'), '2026-10-01'); await user.type(screen.getByLabelText('End date'), '2026-10-03');
    expect(api.getAdminReport).toHaveBeenCalledTimes(1);
    api.getAdminReport.mockResolvedValueOnce(response(appointments)); await user.click(screen.getByRole('button', { name: 'Generate report' }));
    await screen.findByText('2 scheduled appointments'); expect(screen.getByText('Cardiology')).toBeTruthy();
    expect(api.getAdminReport.mock.calls.at(-1).slice(0, 2)).toEqual(['appointments', { start_date: '2026-10-01', end_date: '2026-10-03' }]);
});
it('exports the displayed report range even when draft filters have changed', async () => {
    const user = userEvent.setup(); render(<ReportsPage />); await screen.findByText('3 payment records');
    await user.selectOptions(screen.getByLabelText('Report type'), 'appointments');
    api.exportAdminReport.mockRejectedValue(new Error('offline'));
    await user.click(screen.getByRole('button', { name: 'Export aggregate CSV' })); await screen.findByRole('alert');
    expect(api.exportAdminReport.mock.calls[0].slice(0, 2)).toEqual(['financial', { start_date: '2026-10-01', end_date: '2026-10-03', format: 'csv' }]);
    expect(screen.getByText('3 payment records')).toBeTruthy();
});
it('discards a late financial response after switching to appointments', async () => {
    const user = userEvent.setup(); let complete;
    api.getAdminReport.mockReturnValueOnce(new Promise(resolve => { complete = resolve; })).mockResolvedValueOnce(response(appointments));
    render(<ReportsPage />); await user.selectOptions(screen.getByLabelText('Report type'), 'appointments'); await user.click(screen.getByRole('button', { name: 'Generate report' }));
    await screen.findByText('2 scheduled appointments'); complete(response(financial));
    await waitFor(() => expect(screen.queryByText('3 payment records')).toBeNull());
});
it('supports retry and honest empty payment results', async () => {
    const user = userEvent.setup(); api.getAdminReport.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(response({ ...financial, payments: { total: 0, by_status: {}, by_currency: [] } }));
    render(<ReportsPage />); await user.click(await screen.findByRole('button', { name: 'Retry' }));
    await screen.findByText('No payment records in this range.'); expect(screen.getByText('No completed payment amounts in this range.')).toBeTruthy();
});
