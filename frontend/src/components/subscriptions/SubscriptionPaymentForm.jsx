import { useState } from 'react';
import PropTypes from 'prop-types';
import { CardElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { useNavigate } from 'react-router-dom';
import { getSubscriptionSetupIntent, subscribeToPlan } from '../../services/subscriptionService';
import { apiError } from '../../utils/apiErrors';
import { money, cycles } from '../../utils/subscriptions';

export default function SubscriptionPaymentForm({ plan, user }) {
    const stripe = useStripe(); const elements = useElements(); const navigate = useNavigate();
    const [busy, setBusy] = useState(false); const [error, setError] = useState('');
    const [ready, setReady] = useState(false); const [consent, setConsent] = useState(false);
    // Once a subscription exists, retries confirm its payment instead of creating another.
    const [created, setCreated] = useState(null);
    async function submit(event) {
        event.preventDefault(); if (!stripe || !elements || busy) return;
        setBusy(true); setError('');
        try {
            let payment = created;
            if (!payment) {
                const setup = await getSubscriptionSetupIntent();
                const result = await stripe.confirmCardSetup(setup.data.client_secret, { payment_method: {
                    card: elements.getElement(CardElement), billing_details: { name: `${user.prenom || ''} ${user.nom || ''}`.trim(), email: user.email },
                } });
                if (result.error) { setError(result.error.message); return; }
                if (result.setupIntent?.status !== 'succeeded') { setError('Card setup is still processing. Please check your subscription before trying again.'); return; }
                const response = await subscribeToPlan({ plan_id: plan.id, expected_plan_version: plan.version, payment_method_id: result.setupIntent.payment_method });
                payment = response.data.data; setCreated(payment);
            }
            if (payment.client_secret) {
                const result = await stripe.confirmCardPayment(payment.client_secret);
                if (result.error) { setError(`${result.error.message} Your subscription is pending. Retry this payment or manage it in My subscription.`); return; }
            }
            navigate('/my-subscription', { state: { message: 'Payment submitted. Your subscription activates after payment confirmation.' } });
        } catch (err) { setError(apiError(err, 'Payment could not be completed. Check My subscription before trying again.')); }
        finally { setBusy(false); }
    }
    return <form onSubmit={submit} className="bg-white border rounded-xl p-6 space-y-4">
        <h2 className="text-xl font-semibold">Subscribe to {plan.name}</h2>
        <p>{money(plan.price, plan.currency)} every {cycles[plan.billing_cycle] || plan.billing_cycle}. Renews automatically until cancelled.</p>
        <p>Card details are collected securely by Stripe.</p>
        <div className="border rounded p-4"><CardElement options={{ disabled: busy, hidePostalCode: false }} onReady={() => setReady(true)} onChange={event => { if (event.error) setError(event.error.message); }} /></div>
        <label className="flex gap-2"><input type="checkbox" checked={consent} disabled={busy || Boolean(created)} onChange={event => setConsent(event.target.checked)} required />I authorize the recurring charge shown above. Cancellation ends the subscription immediately.</label>
        {error && <p role="alert" className="text-red-800">{error}</p>}
        <button type="submit" className="bg-blue-700 text-white rounded px-4 py-2" disabled={!stripe || !ready || !consent || busy}>{busy ? 'Processing payment…' : created ? 'Retry payment' : `Pay ${money(plan.price, plan.currency)} and subscribe`}</button>
    </form>;
}
SubscriptionPaymentForm.propTypes = { plan: PropTypes.object.isRequired, user: PropTypes.object.isRequired };
