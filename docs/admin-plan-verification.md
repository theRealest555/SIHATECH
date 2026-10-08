# Administrator subscription-plan verification

Verified locally on 2026-10-07 with PHP 8.5.1, Node 26.7.0 and isolated SQLite fixtures. All changes remain local.

## Implemented behavior

- Approved administrators can list, create and edit actual plans at `/admin/subscription-plans`. New versions start as inactive drafts without copying the old Stripe price ID. Features are stored as a validated list of strings.
- Any subscription history, including pending, cancelled and expired subscriptions, fixes the plan's price, cycle and Stripe price ID. Administrators can still update its presentation and stop new purchases. Existing subscriptions are not cancelled by plan deactivation.
- Activating a plan or changing an active plan's billing fields verifies its Stripe price: active, MAD, matching amount, matching recurring interval/count, and licensed billing. Provider failures produce a safe validation error and do not save the plan.
- Plan updates lock the row and compare its version. Changes and audit records commit together. Unchanged saves do not increment the version or duplicate audit records. The plan-to-subscription relationship now uses the correct `subscription_plan_id` column.
- Checkout locks the plan and rechecks availability and the version the customer reviewed before creating a subscription or payment. A changed plan produces a conflict requiring the customer to refresh and review it. Stripe subscription creation uses the same plan price ID that was verified.

## Evidence

Backend coverage includes real draft persistence/listing, audit targets, version conflicts, identical retries, all four historical subscription states, retirement without cancellation, provider failure sanitization, validation, authorization, audit rollback, retirement during checkout, and cached checkout versions. Official Stripe SDK HTTP stubs cover invalid currency/amount/cycles/activation/metered billing and billing with the validated price.

Frontend tests cover retirement with locked billing fields, new-version drafts, preserved edits on conflict, retry/late responses, and the checkout version request. These interaction tests mock the network.

The browser used the real Laravel API and `storage/app/booking-preview.sqlite`:

1. Existing plan #1 retained its 199 MAD monthly price and pending subscription. Its billing fields were disabled in the administrator editor.
2. Created draft plan #2 from a new-version action at 249 MAD, with no Stripe price and no new subscriptions allowed.
3. Edited draft #2 to 259 MAD and updated its description. Reload confirmed persistence.
4. Audit history showed both creation and update targeting subscription plan #2, by isolated administrator #3.
5. The public purchasing screen excluded draft #2 and disabled online payment because the preview has no configured provider keys.

Screenshot: `../../admin-plan-management.png`. No live Stripe request, card charge or production customer record was used.

## Checks and rollout

- Full backend suite: 245 tests, 1342 assertions passed.
- Full frontend suite: 89 tests passed. Production build and changed-file ESLint passed.
- Entire frontend lint still has 46 existing errors and no warnings.
- Migration `2026_10_07_000004_version_subscription_plans.php` adds `lock_version`, default zero. Applied only to the isolated preview database. Apply it before code rollout and rehearse against a staging copy of the production database. Rollback drops the version column and requires rolling back dependent code too.
- Deploy frontend and API together: subscription creation now requires `expected_plan_version` from the public plan response. Older clients must refresh/update before checkout.
- Rehearse real MySQL locking and provider test-mode activation/checkout/webhooks. Existing active plans are not automatically verified or deactivated by migration; audit their provider price mappings before accepting purchases. Already-created pending payments retain their existing provider intent and require reconciliation rather than a new checkout.
- Administrator reporting, frontend lint cleanup, full staging account journeys, deployment configuration and operational readiness remain release work.
