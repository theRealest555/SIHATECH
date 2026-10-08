// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import StatisticsPage from '../pages/doctor/StatisticsPage';
import * as doctors from '../services/doctorService';
vi.mock('../services/doctorService', () => ({ getDoctorStatistics: vi.fn(), exportDoctorStatistics: vi.fn() }));
const report = total => ({ data: { data: {
    range: { start_date: '2026-10-01', end_date: '2026-10-31', timezone: 'Africa/Casablanca' },
    appointments: { total, by_status: { en_attente: total, 'confirmé': 0, 'terminé': 0, 'annulé': 0, no_show: 0, other: 0 } },
    patients: { total_unique: total, seen: 0, repeat_completed: 0 },
    rating: { average: null, total_reviews: 0 }, trends: { appointments: [{ date: '2026-10-01', count: total }] },
    revenue: { available: false, reason: 'Consultation payments are not tracked.' },
} } });
beforeEach(() => { vi.resetAllMocks(); doctors.getDoctorStatistics.mockResolvedValue(report(0)); });
afterEach(cleanup);
it('renders actual empty counts and explains missing revenue and ratings', async () => {
    render(<StatisticsPage />);
    await screen.findByText('No appointments in this period.');
    expect(screen.getByText('Consultation payments are not tracked.')).toBeTruthy();
    expect(screen.getByText(/No approved reviews/)).toBeTruthy();
    expect(screen.queryByText(/3250|4.7|152/)).toBeNull();
});
it('retries a failed request and submits the selected period', async () => {
    const user = userEvent.setup();
    doctors.getDoctorStatistics.mockRejectedValueOnce(new Error('offline'));
    render(<StatisticsPage />);
    await user.click(await screen.findByRole('button', { name: 'Retry' }));
    await screen.findByText('No appointments in this period.');
    await user.selectOptions(screen.getByLabelText('Period'), 'year');
    await user.click(screen.getByRole('button', { name: 'Apply period' }));
    await waitFor(() => expect(doctors.getDoctorStatistics.mock.calls.at(-1)[0]).toEqual({ period: 'year' }));
});
it('ignores a late response after filters change', async () => {
    const user = userEvent.setup(); let resolve;
    doctors.getDoctorStatistics.mockReturnValueOnce(new Promise(done => { resolve = done; }));
    render(<StatisticsPage />);
    await user.selectOptions(screen.getByLabelText('Period'), 'week');
    await user.click(screen.getByRole('button', { name: 'Apply period' }));
    await screen.findByText('No appointments in this period.');
    resolve(report(999));
    await waitFor(() => expect(screen.queryByText('999')).toBeNull());
    expect(doctors.getDoctorStatistics.mock.calls[0][1].signal.aborted).toBe(true);
});
it('exports the displayed server range and exposes a download failure', async () => {
    const user = userEvent.setup(); doctors.exportDoctorStatistics.mockRejectedValue(new Error('offline'));
    render(<StatisticsPage />);
    await user.click(await screen.findByRole('button', { name: 'Download summary CSV' }));
    await screen.findByRole('alert');
    expect(doctors.exportDoctorStatistics).toHaveBeenCalledWith({ start_date: '2026-10-01', end_date: '2026-10-31', type: 'overview', format: 'csv' });
});
