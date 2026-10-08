import { useCallback, useEffect, useState } from 'react';
import { Elements } from '@stripe/react-stripe-js';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { getSubscriptionPlans, getUserSubscriptionStatus } from '../../services/subscriptionService';
import SubscriptionPaymentForm from '../../components/subscriptions/SubscriptionPaymentForm';
import { apiError } from '../../utils/apiErrors';
import { money, cycles, stripeClient } from '../../utils/subscriptions';

export default function PlansPage() {
    const { user } = useAuth(); const navigate = useNavigate();
    const [plans, setPlans] = useState([]); const [meta, setMeta] = useState({});
    const [current, setCurrent] = useState(null); const [selected, setSelected] = useState(null);
    const [loading, setLoading] = useState(true); const [error, setError] = useState('');
    const load = useCallback(async (signal) => {
        const [plansResponse, currentResponse] = await Promise.all([
            getSubscriptionPlans({ signal }), user?.email_verified_at ? getUserSubscriptionStatus({ signal }) : Promise.resolve(null),
        ]);
        setPlans(plansResponse.data.data); setMeta(plansResponse.data.meta || {});
        setCurrent(currentResponse?.data.data?.subscription || null);
    }, [user?.email_verified_at]);
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setError(''); setSelected(null);
        load(controller.signal).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load plans.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [load]);
    async function refresh() { setLoading(true); setError(''); try { await load(); } catch (err) { setError(apiError(err, 'Could not load plans.')); } finally { setLoading(false); } }
    function choose(plan) {
        if (!user) { navigate('/login', { state: { from: { pathname: '/subscription-plans' } } }); return; }
        if (!user.email_verified_at) { navigate('/verify-email'); return; }
        setSelected(plan);
    }
    const blocked = ['active', 'pending'].includes(current?.status);
    return <main className="max-w-5xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">Subscription plans</h1>
        <p>Compare available plans and review recurring charges before subscribing.</p>
        {error && <div role="alert" className="bg-red-50 text-red-800 p-4">{error} <button disabled={loading} onClick={refresh}>Retry</button></div>}
        {loading ? <p role="status">Loading plans…</p> : !error && <>
            {blocked && <p>You already have an active or pending subscription. <Link to="/my-subscription">Manage your subscription</Link></p>}
            {!meta.checkout_enabled && <p role="status">Online payment is currently unavailable. Please check back later.</p>}
            {!plans.length && <p>No plans are currently available.</p>}
            <div className="grid md:grid-cols-3 gap-4">{plans.map(plan => <article key={plan.id} className="bg-white rounded-xl border p-6 space-y-4">
                <h2 className="text-xl font-semibold">{plan.name}</h2>
                <p className="text-2xl font-bold">{money(plan.price, plan.currency)} <span className="text-base font-normal">/ {cycles[plan.billing_cycle] || plan.billing_cycle}</span></p>
                <p>{plan.description}</p>
                <ul className="list-disc pl-5">{(Array.isArray(plan.features) ? plan.features : []).map((feature, index) => <li key={index}>{feature}</li>)}</ul>
                <button className="bg-blue-700 text-white rounded px-4 py-2" disabled={blocked || !meta.checkout_enabled || !plan.checkout_available || Boolean(selected)} onClick={() => choose(plan)}>Choose {plan.name}</button>
            </article>)}</div>
            {selected && <><Elements key={selected.id} stripe={stripeClient(meta.stripe_publishable_key)}><SubscriptionPaymentForm plan={selected} user={user} /></Elements><p><Link to="/my-subscription">Check or cancel a pending subscription</Link></p></>}
            {user && <Link to="/my-subscription">My subscription</Link>}
        </>}
    </main>;
}
