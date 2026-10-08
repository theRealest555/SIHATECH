# Access-change audit details

Account-status and administrator-approval decisions now store their previous state, resulting state and whether existing access was revoked in the same transaction as the target change. The previous state comes from the locked target row before mutation. Metadata contains only these decision fields; passwords, tokens and authentication versions are not recorded.

The audit endpoint projects recognized account-status and approval values into readable transitions, scoped to the corresponding action and target type. The history screen shows these transitions, recorded revocation and any existing recorded reason separately. React renders decision text as text. Approval restoration and account reactivation do not claim that previously revoked sessions or API tokens were restored.

Older records without before/after values retain the existing No decision details recorded fallback. Malformed values, unknown approval states and mismatched action/target combinations do not produce invented transitions. Integer approval values 0/1 are accepted; historical arbitrary strings are not interpreted as approvals. Raw metadata remains hidden by the audit API.

## Verification

- Six new backend cases cover account suspension/restoration, administrator demotion/restoration, unchanged active/approved decisions, malformed and mismatched metadata, and legacy rows without details.
- Two new frontend cases verify transitions, recorded revocation, simultaneous transition/reason display and approval restoration without a revocation claim.
- Focused backend suite: **35 tests, 231 assertions passed**. Existing rollback and stale-decision tests continue passing, so rejected/failed access changes do not leave audit entries.
- Full backend suite: **377 tests, 2187 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **134 tests passed**; lint and production build passed. PHP formatting and Git whitespace checks passed.
- All access changes occurred in disposable automated test databases; UI interaction tests mock API responses. No preview or external account was changed.

## Rollout and next step

No migration is required because the existing metadata column stores JSON text. Deploying the API first is compatible with the previous screen's transition display. Historical transitions cannot be reconstructed safely from current account state; no backfill is performed. These records describe application decisions, not provider-subscription cancellation, appointment cancellation or guaranteed browser receipt of a response.

Next fix: require and record a short reason when an administrator removes account or administrator access, and collect it in the user-management confirmation.
