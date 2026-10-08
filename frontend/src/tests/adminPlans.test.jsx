// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PlanManagement from '../pages/admin/SubscriptionPlanManagementPage';
import * as api from '../services/adminService';
vi.mock('../services/adminService', () => ({ getAdminPlans: vi.fn(), createAdminPlan: vi.fn(), updateAdminPlan: vi.fn() }));
const plan = { id: 4, name: 'Professional', description: 'Booking tools', price: '199.00', billing_cycle: 'monthly', features: ['Booking'], is_active: true, stripe_price_id: 'price_existing', version: 3, subscriptions_count: 2 };
const response = (rows = [plan]) => ({ data: { data: rows, meta: { total: rows.length, current_page: 1, last_page: 1 } } });
beforeEach(() => { vi.resetAllMocks(); api.getAdminPlans.mockResolvedValue(response()); api.createAdminPlan.mockResolvedValue({}); api.updateAdminPlan.mockResolvedValue({}); });
afterEach(cleanup);
it('retiring a used plan preserves billing fields and supplies the edit version', async () => {
    const user = userEvent.setup(); render(<PlanManagement />);
    await user.click(await screen.findByRole('button', { name: 'Edit plan #4' }));
    expect(screen.getByLabelText('Price (MAD)').disabled).toBe(true);
    expect(screen.getByLabelText('Billing cycle').disabled).toBe(true);
    expect(screen.getByLabelText('Stripe price ID').disabled).toBe(true);
    await user.click(screen.getByLabelText('Allow new subscriptions'));
    await user.click(screen.getByRole('button', { name: 'Save plan' }));
    await screen.findByText('Plan saved.');
    expect(api.updateAdminPlan).toHaveBeenCalledWith(4, { name: 'Professional', description: 'Booking tools', price: '199.00', billing_cycle: 'monthly', features: ['Booking'], is_active: false, stripe_price_id: 'price_existing', expected_version: 3 });
});
it('creates a new draft version without reusing the Stripe price or subscription history', async () => {
    const user = userEvent.setup(); render(<PlanManagement />);
    await user.click(await screen.findByRole('button', { name: 'New version of plan #4' }));
    expect(screen.getByLabelText('Price (MAD)').disabled).toBe(false);
    expect(screen.getByLabelText('Stripe price ID').value).toBe('');
    expect(screen.getByLabelText('Allow new subscriptions').checked).toBe(false);
    await user.clear(screen.getByLabelText('Price (MAD)')); await user.type(screen.getByLabelText('Price (MAD)'), '249.00');
    await user.type(screen.getByLabelText('Features (one per line)'), '\nReporting');
    await user.click(screen.getByRole('button', { name: 'Save plan' }));
    await screen.findByText('Plan saved.');
    expect(api.createAdminPlan).toHaveBeenCalledWith({ name: 'Professional (new version)', description: 'Booking tools', price: '249', billing_cycle: 'monthly', features: ['Booking', 'Reporting'], is_active: false, stripe_price_id: null });
});
it('retains draft edits on conflict without claiming success', async () => {
    const user = userEvent.setup(); api.updateAdminPlan.mockRejectedValue({ response: { status: 409, data: { message: 'Refresh before editing again.' } } });
    render(<PlanManagement />); await user.click(await screen.findByRole('button', { name: 'Edit plan #4' }));
    await user.clear(screen.getByLabelText('Plan name')); await user.type(screen.getByLabelText('Plan name'), 'Renamed');
    await user.click(screen.getByRole('button', { name: 'Save plan' }));
    await screen.findByRole('alert'); expect(screen.getByLabelText('Plan name').value).toBe('Renamed');
    expect(screen.queryByText('Plan saved.')).toBeNull();
});
it('retries a failed list request and ignores late responses after refresh', async () => {
    const user = userEvent.setup(); let complete;
    api.getAdminPlans.mockRejectedValueOnce(new Error('offline')).mockReturnValueOnce(new Promise(resolve => { complete = resolve; })).mockResolvedValueOnce(response([]));
    render(<PlanManagement />); await user.click(await screen.findByRole('button', { name: 'Retry' }));
    // Loading disables refresh; unmounting also must discard the outstanding request.
    cleanup(); render(<PlanManagement />); await screen.findByText('No subscription plans recorded.');
    complete(response()); await waitFor(() => expect(screen.queryByText('Professional · Plan #4')).toBeNull());
});
