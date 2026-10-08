import { useEffect, useState } from 'react';
import { getAdminPlans, createAdminPlan, updateAdminPlan } from '../../services/adminService';
import { apiError } from '../../utils/apiErrors';
const blank = { name: '', description: '', price: '', billing_cycle: 'monthly', features: '', is_active: false, stripe_price_id: '' };
const buttonClass = 'border border-blue-700 text-blue-800 rounded px-3 py-2 disabled:opacity-50';
const cycles = { monthly: 'Monthly', 'semi-annual': 'Every six months', yearly: 'Yearly' };
export default function SubscriptionPlanManagementPage() {
    const [page, setPage] = useState(1); const [revision, setRevision] = useState(0); const [data, setData] = useState(null); const [loading, setLoading] = useState(true);
    const [editor, setEditor] = useState(null); const [busy, setBusy] = useState(false); const [error, setError] = useState(''); const [saveError, setSaveError] = useState(''); const [message, setMessage] = useState('');
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setData(null); setError('');
        getAdminPlans({ page, per_page: 25 }, { signal: controller.signal }).then(response => { if (!controller.signal.aborted) setData(response.data); })
            .catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load plans.')); }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [page, revision]);
    function edit(plan = null, clone = false) {
        setSaveError(''); setMessage('');
        setEditor(plan ? { ...plan, name: clone ? `${plan.name} (new version)` : plan.name, features: plan.features.join('\n'), stripe_price_id: clone ? '' : plan.stripe_price_id || '', ...(clone ? { id: null, is_active: false, subscriptions_count: 0 } : {}) } : { ...blank });
    }
    async function save(event) {
        event.preventDefault(); setBusy(true); setSaveError('');
        const body = { name: editor.name.trim(), description: editor.description.trim() || null, price: editor.price, billing_cycle: editor.billing_cycle, features: editor.features.split('\n').map(value => value.trim()).filter(Boolean), is_active: editor.is_active, stripe_price_id: editor.stripe_price_id.trim() || null, ...(editor.id ? { expected_version: editor.version } : {}) };
        try {
            if (editor.id) await updateAdminPlan(editor.id, body); else await createAdminPlan(body);
            setEditor(null); setMessage('Plan saved.'); setRevision(value => value + 1);
        } catch (err) { setSaveError(apiError(err, 'Could not save this plan.')); }
        finally { setBusy(false); }
    }
    const billingLocked = Boolean(editor?.id && editor.subscriptions_count > 0);
    const change = event => setEditor(current => ({ ...current, [event.target.name]: event.target.type === 'checkbox' ? event.target.checked : event.target.value }));
    return <main className="max-w-5xl mx-auto p-6 space-y-5"><h1 className="text-3xl font-bold">Subscription plans</h1>
        <p>Prices are in MAD. Plans with subscription history keep their price, billing cycle and Stripe price. Create a new version for new pricing.</p>
        <div className="flex gap-4"><button className={buttonClass} disabled={busy} onClick={() => edit()}>Create draft plan</button><button className={buttonClass} disabled={loading || busy} onClick={() => setRevision(value => value + 1)}>Refresh plans</button></div>
        {message && <p role="status">{message}</p>}
        {editor && <section className="border rounded p-4 space-y-3" aria-label="Plan editor"><h2 className="text-xl">{editor.id ? 'Edit plan' : 'New plan'}</h2>
            {billingLocked && <p>Billing fields are locked because this plan has subscription history.</p>}
            {editor.features_need_review && <p>Stored features need review. Enter a clear feature list before saving.</p>}
            <form onSubmit={save} className="space-y-3">
                <label className="block">Plan name <input name="name" value={editor.name} required maxLength={100} disabled={busy} onChange={change} className="border rounded p-2 w-full" /></label>
                <label className="block">Description <textarea name="description" value={editor.description} maxLength={2000} disabled={busy} onChange={change} className="border rounded p-2 w-full" /></label>
                <label className="block">Price (MAD) <input name="price" type="number" min="0.01" max="999999.99" step="0.01" required value={editor.price} disabled={busy || billingLocked} onChange={change} className="border rounded p-2" /></label>
                <label className="block">Billing cycle <select name="billing_cycle" value={editor.billing_cycle} disabled={busy || billingLocked} onChange={change} className="border rounded p-2">{Object.entries(cycles).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
                <label className="block">Features (one per line) <textarea name="features" value={editor.features} disabled={busy} onChange={change} className="border rounded p-2 w-full" /></label>
                <label className="block">Stripe price ID <input name="stripe_price_id" value={editor.stripe_price_id} maxLength={255} disabled={busy || billingLocked} onChange={change} className="border rounded p-2 w-full" /></label>
                <p>Allowing new subscriptions requires an active recurring Stripe price with the same MAD amount and cycle. Saving a draft can leave this field blank.</p>
                <label className="block"><input name="is_active" type="checkbox" checked={editor.is_active} disabled={busy} onChange={change} /> Allow new subscriptions</label>
                <p>Turning this off removes the plan from new purchases. Existing subscriptions continue.</p>
                {saveError && <p role="alert">{saveError}</p>}
                <div className="flex gap-4"><button disabled={busy} className="bg-blue-700 text-white rounded px-4 py-2">{busy ? 'Saving…' : 'Save plan'}</button><button type="button" className={buttonClass} disabled={busy} onClick={() => setEditor(null)}>Cancel edit</button></div>
            </form>
        </section>}
        {loading && <p role="status">Loading plans…</p>}{error && <p role="alert">{error} <button className={buttonClass} onClick={() => setRevision(value => value + 1)}>Retry</button></p>}
        {data && <><p>{data.meta.total} plans</p>{!data.data.length && <p>No subscription plans recorded.</p>}{data.data.map(plan => <article key={plan.id} className="border rounded p-4 space-y-2"><h2 className="text-lg font-semibold">{plan.name} · Plan #{plan.id}</h2><p>{plan.price} MAD · {cycles[plan.billing_cycle]} · {plan.is_active ? 'Available to new subscribers' : 'Not available to new subscribers'}</p><p>{plan.subscriptions_count} subscription records</p><p>{plan.description}</p><ul>{plan.features.map((feature, index) => <li key={index}>{feature}</li>)}</ul>
            <div className="flex gap-4"><button className={buttonClass} disabled={busy} onClick={() => edit(plan)}>Edit plan #{plan.id}</button><button className={buttonClass} disabled={busy} onClick={() => edit(plan, true)}>New version of plan #{plan.id}</button></div>
        </article>)}<nav aria-label="Plan pages" className="flex gap-4"><button className={buttonClass} disabled={busy || data.meta.current_page <= 1} onClick={() => setPage(value => value - 1)}>Previous page</button><span>Page {data.meta.current_page} of {data.meta.last_page}</span><button className={buttonClass} disabled={busy || data.meta.current_page >= data.meta.last_page} onClick={() => setPage(value => value + 1)}>Next page</button></nav></>}
    </main>;
}
