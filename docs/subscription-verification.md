# Subscription verification — 6 October 2026

Verified with isolated SQLite and preview accounts. No actual cards, provider keys or external charges were used.

- Browser: public plans displayed the persisted Preview Basic price of MAD 199/month; an existing pending subscription blocked another purchase; unconfigured checkout was disabled.
- Browser: My subscription displayed the stored pending state and no completed payments. Attempting cancellation without a provider mapping returned the reconciliation error and kept the record pending.
- API: public plan filtering and public-only configuration, owner-only pending secrets, no paid entitlement for pending/cancelled records, duplicate protection, uncertain provider failures, immediate provider cancellation, cancellation failures and six-month billing dates passed.
- Stripe SDK HTTP stub: validated provider pricing, confirmed latest_invoice.confirmation_secret expansion and the selected payment method, and rejected mismatched pricing before subscription creation. Signed unpaid status events did not activate access. Existing signed paid-invoice reconciliation tests still passed.
- Frontend: setup rejection, payment authentication failure/retry, sign-in routing, pending purchase blocking, provider-unavailable state, cancellation confirmation/failure, and resuming an existing payment passed. These tests mock Stripe/network calls.

Results: 167 backend tests / 833 assertions; 34 frontend tests; production build and changed subscription files lint passed. Full frontend lint still has 102 errors / 4 warnings.

The card setup and confirmation follow [Stripe's setup confirmation documentation](https://docs.stripe.com/js/setup_intents/confirm_card_setup) and [payment confirmation documentation](https://docs.stripe.com/js/payment_intents/confirm_card_payment). The installed Stripe PHP SDK uses API version 2025-08-27.basil; its Invoice model declares confirmation_secret.

Before enabling checkout, configure STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, real Stripe price IDs, TLS, cookie/CORS domains and the signed webhook endpoint. Verify displayed plan features and enforce promised entitlements before selling subscriptions. Seeded price placeholders are not real Stripe prices.

Required staging cases: test cards, authentication challenges, failed/expired payments, renewal, immediate cancellation, pending recovery after reload, provider timeouts, concurrent MySQL requests, webhook delivery before local mapping, and out-of-order events. A reconciliation/support process is still needed for ambiguous provider failures and historical paid-state errors. The local status UI test does not prove live Stripe integration or production readiness.

Screenshot subscription-status.png is in the parent workspace. Preview servers were stopped after verification.
