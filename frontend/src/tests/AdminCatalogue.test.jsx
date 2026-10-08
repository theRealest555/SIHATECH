// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import CatalogueManagement from '../components/CatalogueManagement';
import * as api from '../services/adminService';
vi.mock('../services/adminService', () => ({ getCatalogue: vi.fn(), createCatalogueRecord: vi.fn(), updateCatalogueRecord: vi.fn() }));
const record = { id: 4, nom: 'Cardiology', description: 'Heart care', doctors_count: 2 };
const response = (rows = [record]) => ({ data: { data: rows, meta: { total: rows.length, current_page: 1, last_page: 1 } } });
beforeEach(() => { vi.resetAllMocks(); api.getCatalogue.mockResolvedValue(response()); api.createCatalogueRecord.mockResolvedValue({}); api.updateCatalogueRecord.mockResolvedValue({}); });
afterEach(cleanup);
it('edits stored fields with the original values for stale-edit protection', async () => {
    const user = userEvent.setup(); render(<CatalogueManagement kind="specialities" />);
    await user.click(await screen.findByRole('button', { name: 'Edit Cardiology' }));
    await user.clear(screen.getByLabelText('Name')); await user.type(screen.getByLabelText('Name'), 'Clinical cardiology');
    await user.click(screen.getByRole('button', { name: 'Save record' }));
    await screen.findByText('Record saved.');
    expect(api.updateCatalogueRecord).toHaveBeenCalledWith('specialities', 4, { nom: 'Clinical cardiology', description: 'Heart care', expected_nom: 'Cardiology', expected_description: 'Heart care' });
});
it('creates a real language name without an invented language-code field', async () => {
    const user = userEvent.setup(); render(<CatalogueManagement kind="languages" />);
    await user.click(screen.getByRole('button', { name: 'Add language' }));
    await user.type(screen.getByLabelText('Name'), 'French');
    expect(screen.queryByLabelText('Code')).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Save record' }));
    await screen.findByText('Record saved.');
    expect(api.createCatalogueRecord).toHaveBeenCalledWith('languages', { nom: 'French' });
});
it('keeps draft edits and displays conflict errors without claiming success', async () => {
    const user = userEvent.setup(); api.updateCatalogueRecord.mockRejectedValue({ response: { status: 409, data: { message: 'This record changed. Refresh before editing again.' } } });
    render(<CatalogueManagement kind="specialities" />);
    await user.click(await screen.findByRole('button', { name: 'Edit Cardiology' }));
    await user.click(screen.getByRole('button', { name: 'Save record' }));
    await screen.findByRole('alert');
    expect(screen.getByLabelText('Name').value).toBe('Cardiology');
    expect(screen.queryByText('Record saved.')).toBeNull();
});
it('supports retry and searches only after submission', async () => {
    const user = userEvent.setup(); api.getCatalogue.mockRejectedValueOnce(new Error('offline')).mockResolvedValue(response([]));
    render(<CatalogueManagement kind="languages" />);
    await user.click(await screen.findByRole('button', { name: 'Retry' }));
    await screen.findByText('No records match this search.');
    await user.type(screen.getByLabelText('Search names'), 'French');
    expect(api.getCatalogue).toHaveBeenCalledTimes(2);
    await user.click(screen.getByRole('button', { name: 'Search', exact: true }));
    await waitFor(() => expect(api.getCatalogue.mock.calls.at(-1)[1]).toEqual({ search: 'French', page: 1, per_page: 25 }));
});
it('ignores late responses after the search changes', async () => {
    const user = userEvent.setup(); let complete;
    api.getCatalogue.mockReturnValueOnce(new Promise(resolve => { complete = resolve; })).mockResolvedValueOnce(response([]));
    render(<CatalogueManagement kind="languages" />);
    await user.type(screen.getByLabelText('Search names'), 'Unmatched'); await user.click(screen.getByRole('button', { name: 'Search', exact: true }));
    await screen.findByText('No records match this search.'); complete(response());
    await waitFor(() => expect(screen.queryByText('Cardiology')).toBeNull());
});
