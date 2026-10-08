# Administrator-driven password reset authorization

The administrative reset endpoint requires the requesting administrator's `current_password` and matching `password_confirmation` for the new target password. Inputs are bounded before hashing. The transaction locks actor and target user rows in ID order, rechecks the actor's active administrative approval and authentication version, then changes target credentials and persists the audit entry together.

Wrong/missing actor credentials, mismatched new passwords and stale authentication return validation errors. Lost administrative authority returns 403, and an unknown target returns 404 instead of a generic server error. Successful resets increment the target authentication version, rotate its remember token, revoke target API tokens and return `access_revoked: true` with no-store headers. Another administrator's credentials and tokens remain unchanged. Existing audit-write failure coverage proves all credential changes roll back when the audit cannot persist.

Reset and administrator-creation routes now use distinct named three-per-minute limiters keyed by the authenticated actor, avoiding an incidental shared counter with unrelated generic route throttles. Limit responses retain `Retry-After`.

## Verification

- Six new backend tests cover required/wrong current passwords, confirmation mismatch, stale and suspended actors, successful target-only changes with safe auditing, missing targets, and reset throttling.
- Existing reset and audit-rollback tests now provide both confirmation fields.
- Full backend suite: **332 tests, 1898 assertions passed** on PHP 8.5.1 / SQLite.
- Changed PHP formatting and Git whitespace checks passed. Frontend code is unchanged; the most recent frontend suite remains **130 tests passed**, with build and lint passing in the user-management batch.
- Credential changes were exercised only in disposable automated test databases. No preview or external account password was reset.

## Rollout and next step

No migration is required. Update reset API clients to send `current_password` for the actor and `password_confirmation` for the target. Rebuild route/config caches and restart processes as required. Provider-only administrators must establish a known password through recovery before using this operation. The dedicated administrative reset UI remains unimplemented.

Rehearse competing resets and demotions on the production database engine; SQLite tests do not prove row-lock ordering under concurrency. An administrative reset of the actor's own account revokes its own access too; subsequent requests must sign in again.

Next fix: invalidate outstanding email reset links whenever a password changes, including ordinary profile changes and administrator resets, so an older recovery link cannot undo a newer credential change.
