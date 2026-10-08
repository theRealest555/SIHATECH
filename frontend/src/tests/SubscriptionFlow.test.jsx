// @vitest-environment jsdom
import { beforeEach, afterEach, it, expect, vi } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import PlansPage from '../pages/subscriptions/PlansPage';
import StatusPage from '../pages/subscriptions/SubscriptionStatusPage';
import * as service from '../services/subscriptionService';
import { useAuth } from '../hooks/useAuth';
import { useStripe, useElements } from '@stripe/react-stripe-js';
import { stripeClient } from '../utils/subscriptions';
vi.mock('../hooks/useAuth', () => ({ useAuth: vi.fn() }));
vi.mock('../services/subscriptionService', () => ({ getSubscriptionPlans: vi.fn(), getUserSubscriptionStatus: vi.fn(), getSubscriptionSetupIntent: vi.fn(), subscribeToPlan: vi.fn(), cancelCurrentSubscription: vi.fn() }));
vi.mock('@stripe/react-stripe-js', async () => {
    const { default: PropTypes } = await import('prop-types');
    function CardElement({ onReady }) { return <button type="button" onClick={onReady}>Load secure card</button>; }
    CardElement.propTypes = { onReady: PropTypes.func };
    return { Elements: ({ children }) => children, CardElement, useStripe: vi.fn(), useElements: vi.fn() };
});
vi.mock('../utils/subscriptions', async original => ({ ...await original(), stripeClient: vi.fn() }));
const plan = { id: 1, version: 3, name: 'Basic', price: '199.00', currency: 'MAD', billing_cycle: 'monthly', features: ['Appointment scheduling'], checkout_available: true };
const plans = { data: { data: [plan], meta: { checkout_enabled: true, stripe_publishable_key: 'pk_test' } } };
const subscription = { id: 7, status: 'pending', ends_at: '2100-02-01', subscription_plan: plan };
const response = data => ({ data: { data } });
const stripe = { confirmCardSetup: vi.fn(), confirmCardPayment: vi.fn() };
function show(path = '/subscription-plans') { render(<MemoryRouter initialEntries={[path]}><Routes><Route path="/subscription-plans" element={<PlansPage />} /><Route path="/my-subscription" element={<StatusPage />} /><Route path="/login" element={<p>Sign in first</p>} /></Routes></MemoryRouter>); }
beforeEach(() => {
    vi.resetAllMocks(); useAuth.mockReturnValue({ user: { email_verified_at: '2100-01-01', email: 'test@example.test', role: 'patient' } });
    service.getSubscriptionPlans.mockResolvedValue(plans); service.getUserSubscriptionStatus.mockResolvedValue(response(null));
    service.getSubscriptionSetupIntent.mockResolvedValue({ data: { client_secret: 'seti_secret' } });
    service.subscribeToPlan.mockResolvedValue(response({ subscription, client_secret: 'pi_secret' }));
    useStripe.mockReturnValue(stripe); useElements.mockReturnValue({ getElement: () => ({ secure: true }) });
    stripeClient.mockResolvedValue(stripe);
    stripe.confirmCardSetup.mockResolvedValue({ setupIntent: { status: 'succeeded', payment_method: 'pm_card' } });
    stripe.confirmCardPayment.mockResolvedValue({ paymentIntent: { status: 'succeeded' } });
});
afterEach(cleanup);
it('renders real MAD pricing and handles empty plans', async () => {
    show(); await screen.findByText('Basic'); expect(screen.getByText('Appointment scheduling')).toBeTruthy();
    expect(screen.getByText(/MAD.*199/)).toBeTruthy();
});
it('shows a retryable plan-loading failure', async () => {
    service.getSubscriptionPlans.mockRejectedValueOnce({ response: { data: { message: 'Plans unavailable.' } } });
    const user = userEvent.setup(); show(); expect((await screen.findByRole('alert')).textContent).toContain('Plans unavailable.');
    await user.click(screen.getByRole('button', { name: 'Retry' })); await screen.findByText('Basic');
});
it('returns anonymous users to sign-in before collecting payment', async () => {
    useAuth.mockReturnValue({ user: null }); const user = userEvent.setup(); show();
    await user.click(await screen.findByRole('button', { name: 'Choose Basic' })); await screen.findByText('Sign in first');
    expect(service.getSubscriptionSetupIntent).not.toHaveBeenCalled();
});
it('blocks duplicate purchase when a pending subscription exists', async () => {
    service.getUserSubscriptionStatus.mockResolvedValue(response({ subscription })); show();
    expect((await screen.findByRole('button', { name: 'Choose Basic' })).disabled).toBe(true);
});
it('disables payment when the provider is not configured', async () => {
    service.getSubscriptionPlans.mockResolvedValue({ data: { ...plans.data, meta: { checkout_enabled: false } } }); show();
    await screen.findByText('Online payment is currently unavailable. Please check back later.');
    expect(screen.getByRole('button', { name: 'Choose Basic' }).disabled).toBe(true);
});
async function prepare(user) {
    await user.click(await screen.findByRole('button', { name: 'Choose Basic' }));
    await user.click(screen.getByRole('button', { name: 'Load secure card' }));
    await user.click(screen.getByRole('checkbox'));
}
it('confirms setup then payment without claiming instant paid access', async () => {
    const user = userEvent.setup(); show(); await prepare(user);
    await user.click(screen.getByRole('button', { name: /Pay .*and subscribe/ }));
    await screen.findByText('Payment submitted. Your subscription activates after payment confirmation.');
    expect(service.subscribeToPlan).toHaveBeenCalledWith({ plan_id: 1, expected_plan_version: 3, payment_method_id: 'pm_card' });
    expect(stripe.confirmCardPayment).toHaveBeenCalledWith('pi_secret');
});
it('retries the same payment after authentication fails without creating a second subscription', async () => {
    stripe.confirmCardPayment.mockResolvedValueOnce({ error: { message: 'Authentication failed.' } });
    const user = userEvent.setup(); show(); await prepare(user);
    await user.click(screen.getByRole('button', { name: /Pay .*and subscribe/ }));
    expect((await screen.findByRole('alert')).textContent).toContain('Authentication failed.');
    await user.click(screen.getByRole('button', { name: 'Retry payment' }));
    await waitFor(() => expect(stripe.confirmCardPayment).toHaveBeenCalledTimes(2));
    expect(service.subscribeToPlan).toHaveBeenCalledTimes(1); expect(stripe.confirmCardSetup).toHaveBeenCalledTimes(1);
});
it('shows card setup failure before creating a subscription', async () => {
    stripe.confirmCardSetup.mockResolvedValue({ error: { message: 'Card declined.' } }); const user = userEvent.setup(); show(); await prepare(user);
    await user.click(screen.getByRole('button', { name: /Pay .*and subscribe/ }));
    expect((await screen.findByRole('alert')).textContent).toContain('Card declined.'); expect(service.subscribeToPlan).not.toHaveBeenCalled();
});
it('requires cancellation confirmation and keeps state on provider failure', async () => {
    service.getUserSubscriptionStatus.mockResolvedValue(response({ subscription, has_access: false, recent_payments: [] }));
    service.cancelCurrentSubscription.mockRejectedValue({ response: { data: { message: 'Provider cancellation failed.' } } });
    const user = userEvent.setup(); show('/my-subscription'); await user.click(await screen.findByRole('button', { name: 'Cancel subscription' }));
    expect(service.cancelCurrentSubscription).not.toHaveBeenCalled(); await user.click(screen.getByRole('button', { name: 'Confirm cancellation' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Provider cancellation failed.');
    expect(screen.getByText('Status: Awaiting payment confirmation')).toBeTruthy();
});
it('resumes an existing pending payment and refreshes the server state', async () => {
    service.getUserSubscriptionStatus.mockResolvedValue(response({ subscription, payment_client_secret: 'pi_resume', has_access: false, recent_payments: [] }));
    const user = userEvent.setup(); show('/my-subscription'); await user.click(await screen.findByRole('button', { name: 'Complete pending payment' }));
    await screen.findByText('Payment submitted. Refresh to check payment confirmation.');
    expect(stripe.confirmCardPayment).toHaveBeenCalledWith('pi_resume'); expect(service.subscribeToPlan).not.toHaveBeenCalled();
});
