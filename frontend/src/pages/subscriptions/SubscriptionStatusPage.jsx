import { useCallback, useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { cancelCurrentSubscription, getSubscriptionPlans, getUserSubscriptionStatus } from '../../services/subscriptionService';
import { apiError } from '../../utils/apiErrors';
import { money, cycles, stripeClient } from '../../utils/subscriptions';
const statuses = { active: 'Active', pending: 'Awaiting payment confirmation', cancelled: 'Cancelled', expired: 'Expired' };
export default function SubscriptionStatusPage() {
    const location = useLocation();
    const [data, setData] = useState(null); const [key, setKey] = useState(null);
    const [loading, setLoading] = useState(true); const [busy, setBusy] = useState(false);
    const [error, setError] = useState(''); const [message, setMessage] = useState(location.state?.message || '');
    const [confirm, setConfirm] = useState(false);
    const load = useCallback(async (signal) => {
        const [response, plans] = await Promise.all([getUserSubscriptionStatus({ signal }), getSubscriptionPlans({ signal })]);
        setData(response.data.data); setKey(plans.data.meta?.checkout_enabled ? plans.data.meta?.stripe_publishable_key : null);
    }, []);
    useEffect(() => {
        const controller = new AbortController();
        load(controller.signal).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load subscription.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [load]);
    async function refresh() { setError(''); setLoading(true); try { await load(); } catch (err) { setError(apiError(err, 'Could not refresh subscription.')); } finally { setLoading(false); } }
    async function cancel() {
        setBusy(true); setError(''); setMessage('');
        try {
            await cancelCurrentSubscription(); setMessage('Subscription cancelled. Recurring billing has stopped.'); setConfirm(false);
            try { await load(); } catch { setError('Cancellation succeeded, but refreshing failed. Reload to see your subscription.'); }
        } catch (err) { setError(apiError(err, 'Cancellation failed. Your subscription has not been marked cancelled.')); }
        finally { setBusy(false); }
    }
    async function resume() {
        setBusy(true); setError(''); setMessage('');
        try {
            const stripe = await stripeClient(key);
            if (!stripe) throw new Error('Payment is currently unavailable.');
            const result = await stripe.confirmCardPayment(data.payment_client_secret);
            if (result.error) { setError(result.error.message); return; }
            setMessage('Payment submitted. Refresh to check payment confirmation.'); await load();
        } catch (err) { setError(apiError(err, 'Could not complete payment. Please check your subscription before retrying.')); }
        finally { setBusy(false); }
    }
    const subscription = data?.subscription; const plan = subscription?.subscription_plan;
    return <main className="max-w-3xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">My subscription</h1>
        {error && <p role="alert" className="bg-red-50 text-red-800 p-4">{error}</p>}
        {message && <p role="status" className="bg-green-50 text-green-800 p-4">{message}</p>}
        <button disabled={loading || busy} onClick={refresh}>Refresh status</button>
        {loading ? <p role="status">Loading subscription…</p> : !subscription ? !error && <p>No subscription found. <Link to="/subscription-plans">View plans</Link></p> : <>
            <section className="bg-white rounded-xl border p-6 space-y-4">
                <h2 className="text-xl font-semibold">{plan?.name || 'Subscription'}</h2>
                {plan && <p>{money(plan.price)} / {cycles[plan.billing_cycle] || plan.billing_cycle}</p>}
                <p>Status: {statuses[subscription.status] || subscription.status}</p>
                {subscription.status === 'active' && <p>{data.has_access ? `Current paid period ends ${subscription.ends_at.slice(0, 10)}.` : 'The recorded subscription is outside its paid access period. Please contact support before subscribing again.'}</p>}
                {subscription.status === 'pending' && <><p>Paid access is awaiting confirmation. Check your payment before starting another subscription.</p>{key && data.payment_client_secret && <button disabled={busy} onClick={resume}>Complete pending payment</button>}</>}
                {['active', 'pending'].includes(subscription.status) && (confirm ? <div className="space-y-3"><p>Cancel now? Cancellation takes effect immediately and stops future billing.</p><button disabled={busy} onClick={cancel}>{busy ? 'Cancelling…' : 'Confirm cancellation'}</button> <button disabled={busy} onClick={() => setConfirm(false)}>Keep subscription</button></div> : <button disabled={busy} onClick={() => setConfirm(true)}>Cancel subscription</button>)}
                {['cancelled', 'expired'].includes(subscription.status) && <Link to="/subscription-plans">View available plans</Link>}
            </section>
            <section className="bg-white rounded-xl border p-6 space-y-3"><h2 className="text-xl font-semibold">Recent completed payments</h2>
                {!data.recent_payments?.length ? <p>No completed payments recorded.</p> : <ul>{data.recent_payments.map(payment => <li key={payment.id}>{payment.date} · {money(payment.amount, payment.currency)}</li>)}</ul>}
            </section>
        </>}
    </main>;
}
