# Social sign-in failure recovery

The login page now translates known backend OAuth error codes into clear recovery messages. Cancelled sign-in, expired state, provider failures, unverified provider email, unavailable accounts and existing-email conflicts have distinct explanations. Email/password sign-in and password recovery remain available. Dismiss removes only the error query parameter using replacement navigation; unrelated query parameters are preserved.

Unknown codes use a fixed generic message. Raw query text is not displayed as the error message, including inherited object-property names. This banner reports a sign-in failure; it does not establish authentication or authorize linking.

The shared authentication layout now uses a text SIHATECH wordmark instead of a broken external placeholder image. Its home link has an explicit accessible name.

The callback now returns `cancelled` for provider `access_denied` responses without attempting authentication, and `session_expired` for Socialite's invalid-state exception. Successful identity processing still uses Socialite's state verification. Other exceptions retain an opaque failure response and existing provider-only warning logs.

Before account lookup, the callback requires a nonempty string/integer provider ID within the storage length. Missing IDs cannot match legacy null mappings. Multiple accounts sharing the same provider ID are rejected instead of selecting the first account. IDs are compared within their provider. Existing matching-email accounts are still not linked automatically.

## Verification

- Four new backend tests cover cancelled flows, invalid state, null IDs and duplicate mappings. Existing creation, linked-account login and matching-email safeguards pass.
- Six new frontend tests cover recovery messages, unknown/inherited keys, dismissal and ordinary login.
- Full backend suite: **285 tests, 1589 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **105 tests passed**; production build, full lint, PHP formatting and Git whitespace checks passed.
- Real localhost browser: signed out the isolated preview administrator, visited the actual Google callback with `error=access_denied`, verified redirect to `/login?error=cancelled`, visible recovery text and successful dismissal. No provider login, new account or email was performed. Screenshot: workspace parent `social-signin-cancelled.png`.

## Rollout and remaining work

No migration is required for this batch. Restart long-running application processes after deployment as required by the host.

Authenticated social-account linking remains unimplemented. The subsequent batch adds a provider-identity uniqueness migration with preflight checks; see [social-identity-integrity-verification.md](social-identity-integrity-verification.md). Duplicate historical mappings block sign-in and need deliberate reconciliation; do not merge accounts merely because emails match. Concurrent callbacks still need production-engine testing.

Google/Facebook credentials, consent configuration, callback domains, actual provider state validation, cookie settings and full successful provider journeys still require staging tests. New account creation continues to require a verified Google email; Facebook remains available for already linked identities. A subsequent batch adds configuration availability checks and disabled provider controls; see [social-provider-availability-verification.md](social-provider-availability-verification.md). These checks and error screens do not prove those external integrations are working.
