# Administrator status-change authorization

Account-status and administrator-approval updates now lock both the acting administrator and the target account in ascending user-ID order. Before changing access, they recheck the actor's current role, active status, administrator approval and authentication version against the request identity. Approval changes lock the owner's user row before the admin profile, so actor authorization and target changes use the same user-lock ordering.

The existing protection against deactivating or demoting oneself remains. Successful changes revoke only the target's access and write the audit record in the same transaction. Authorization errors retain HTTP 403 and missing targets retain HTTP 404 instead of becoming generic 500 errors. Successful status responses use no-store headers. Approval updates also reject a profile whose owner no longer has the administrator role.

## Verification

Fourteen new cases cover both endpoints: suspended actors, stale authentication versions, revoked approval, demotion injected after preliminary authorization, target-only access revocation with the target ID below the actor ID, audit-failure rollback and missing targets. The deterministic demotion injection tests the authorization boundary; it does not simulate two independent database connections.

- New authorization suite: **14 tests, 84 assertions passed**.
- Full backend suite: **363 tests, 2081 assertions passed** on PHP 8.5.1 / SQLite.
- Changed PHP formatting and Git whitespace checks passed.

No preview or external account was suspended, demoted or reactivated. Tests use disposable SQLite databases. No frontend, payload or migration change is required; frontend verification remains the previously recorded 130 passing tests.

## Rollout and next step

Rehearse administrators changing each other's status and competing approval/status changes on the production database engine. SQLite does not establish real row-lock behavior. Account-status changes retain clinical and billing history and do not cancel appointments or provider subscriptions.

Next fix: reject stale administrator status decisions, so an old user-management screen cannot overwrite a newer suspension, reactivation or approval decision.
