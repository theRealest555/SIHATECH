# Administrator creation integrity

`POST /api/admin/users/admin` now creates the user, administrative approval profile and audit entry in one transaction. Profile or audit persistence failures roll back the entire operation; no partially created privileged account remains. The requesting administrator is reloaded under a row lock and must still be active and approved, with the same authentication version as the request identity.

Requests now require `current_password` for the requesting administrator and `password_confirmation` for the new account password. Password inputs are bounded at 1024 characters. A current-password mismatch or stale authentication version returns a field validation error; lost administrative eligibility returns 403. The endpoint allows three requests per minute through the existing authenticated-user throttle mechanism and preserves `Retry-After` when blocked.

Successful responses use no-store headers and exclude hidden credential/security fields. Unexpected failures log only `Admin creation failed` and the exception class, without exception messages containing database values. The new account remains email-unverified, receives no token and does not bypass protected email-verification routes. This batch does not send an invitation or build an account-creation UI.

## Verification

- Six new backend tests cover required password confirmation, wrong current password, profile/audit rollback, stale or suspended actors, successful audited creation without credential/token exposure or verification bypass, and creation throttling.
- Existing administrator creation tests now supply the required current password and new-password confirmation.
- Full backend suite: **326 tests, 1854 assertions passed** on PHP 8.5.1 / SQLite.
- Changed PHP formatting and Git whitespace checks passed. Frontend code is unchanged; its most recent suite remains **130 tests passed**, with build and lint passing in the previous batch.
- Account creation was exercised only in disposable automated test databases. No administrator was created in the user-facing preview or an external environment.

## Rollout and next step

No migration is required. Update any API clients to send `current_password` and `password_confirmation` together with the existing fields, and rebuild route caches as required. Administrators signing in through a provider need a known password before using this endpoint; they can establish one through the existing reset workflow. No real provider or email flow was exercised here.

Next fix: protect administrator-driven password resets with fresh password confirmation and recheck the actor's administrative authority within the credential-change transaction. Account-creation UI/invitations and production-database concurrency remain separate work.
