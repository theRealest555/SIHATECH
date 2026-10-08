# Reasons for administrator access removal

Account-status requests that set Inactive or Pending and administrator-approval requests that set Not approved now require a nonblank `reason` of at most 500 characters. Leading/trailing whitespace, including Unicode whitespace, is removed before validation. Activation and approval requests may omit the reason; any supplied value is still validated.

The validated reason is saved alongside the locked before/after states and revocation outcome in the same audit transaction. It is available through the existing administrator-only audit history projection, alongside the transition. Status-update success responses do not echo the reason. Credentials and authentication versions are not added to audit metadata.

The user-management confirmation collects Reason for deactivation and explains that administrators can see it in audit history. Confirmation stays disabled until a nonblank reason is provided. The screen trims the submitted value, limits the input to 500 characters and preserves it after a failed request. Cancelling clears it; a stale-state conflict still requires reloading and a new decision. The frontend has no administrator-approval management form; API callers must supply the reason for demotion.

## Verification

- Nine new backend cases cover Inactive, Pending and administrator demotion; missing/null/blank/Unicode-whitespace/non-string/overlong reasons; trimmed audit projection; optional reasons for active/approved decisions; and a 500-character Unicode reason preserved without truncation.
- One new frontend case covers blank-reason blocking, the input limit and clearing on cancellation. Existing confirmation, failure-preservation and stale-conflict cases now verify reason handling.
- Existing status authorization, audit rollback and stale-decision tests use explicit fixture reasons and continue exercising the same safety guarantees.
- Full frontend suite: **135 tests passed**. Frontend lint and production build passed.
- Full backend suite: **386 tests, 2297 assertions passed** on PHP 8.5.1 / SQLite. Changed PHP formatting and Git whitespace checks passed.
- Changes were exercised in disposable backend test databases and mocked UI tests. No preview or external account was changed.

## Rollout and next step

Deploy frontend and API together. Other clients must include `reason` when requesting Inactive, Pending or administrator demotion, alongside `expected_status_revision`. No migration or historical-reason backfill is required. Old audit entries without reasons retain their existing fallback; the application cannot infer why an earlier action happened.

Next fix: make unchanged status decisions return without repeating access revocation or creating duplicate change-history entries.
