# Social identity database integrity

Migration `2026_10_07_000006_unique_social_identities.php` adds the named unique index `users_provider_identity_unique` on `(provider, provider_id)`. Complete identity mappings can belong to only one account per provider. Identical ID strings from Google and Facebook remain separate identities, and multiple password accounts with both fields null remain valid.

Before index creation, the migration rejects historical incomplete mappings (only one field populated), blank/space-only values and duplicate complete pairs. Preflight queries use the target database's grouping/collation rules. Failures do not merge accounts, clear mappings, delete users or perform index DDL. No identity values appear in the exception messages.

The callback's missing-ID and ambiguous-mapping checks remain as safeguards for legacy data. The new constraint closes the database-level duplicate-write gap. If concurrent callbacks collide during account creation, the existing transaction rolls back and returns the generic sign-in failure; no existing email account is automatically linked. This batch does not add transparent retry or authenticated social-account linking.

The unique index is not a completeness CHECK constraint: null mappings are allowed, while the migration audits existing incomplete data and the OAuth callback validates new provider IDs. Direct database writes and any future linking implementation must preserve complete, validated mappings.

## Rollout

1. Back up and rehearse the migration on a staging copy using the production engine and collation. Review incomplete/blank mappings and duplicate provider pairs in restricted operator tooling. Reconcile ownership using trusted account/provider records; matching email alone is not enough to merge or link accounts.
2. Stop concurrent identity writes during the migration window. Apply the migration after preflight data issues are resolved, retaining all account/profile history. An index failure caused by a concurrent duplicate must be investigated; do not bypass the migration or delete an owner to force it through.
3. Restart application/queue processes as appropriate and test existing linked sign-in, password sign-in and simultaneous new-account callbacks. Verify that a losing callback cannot leave an orphan profile or authenticate a different owner.
4. Rollback removes only the unique index and preserves data, but also removes the database's duplicate-write protection. Retain runtime guards and reassess concurrent writes before rollback.

This migration was exercised only in disposable SQLite tests, not the existing preview or any deployed database. The full reminder and other earlier migrations remain part of the deployment sequence.

## Verification

Six new tests cover duplicate rejection, provider scoping, multiple null/password accounts, duplicate and incomplete/blank preflight failures, a standalone blank case, successful historical index creation and data-preserving rollback. The legacy ambiguous-callback regression explicitly removes the constraint within its disposable test transaction to represent unmigrated data.

Full backend suite: **291 tests, 1609 assertions passed** on PHP 8.5.1 / SQLite. PHP formatting and Git whitespace checks passed. No frontend files changed; the previous 105 frontend tests, production build and lint results remain applicable. MySQL collation, index deployment and genuinely simultaneous callbacks require staging verification. No real accounts, emails or provider requests were changed.
