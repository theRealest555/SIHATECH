# Password reset consumption and recovery

The reset endpoint locks the account before Laravel's broker validates its token. Broker validation, password update, authentication-version increment, API-token revocation and reset-token deletion now share one database transaction. Competing reset submissions for the same account serialize on its row; a second submission checks the consumed token after the first transaction completes. The password-reset event is dispatched after the transaction succeeds. Token-deletion failures roll back credential changes and access revocation.

Unknown accounts and invalid/expired tokens return the same reset-link validation message. Token and email input must be strings with bounded lengths; password input is bounded before hashing. Dedicated limits permit five reset requests per minute per normalized email across source IPs and ten per minute per IP across addresses, independently of link-request limits. Limits return HTTP 429 with `Retry-After`.

The reset form reads the email query parameter in the emailed link, preserves an editable email field, explains invalid links and limits, and offers a new-link shortcut. Success clears password fields, prevents resubmission and provides explicit sign-in navigation instead of a delayed redirect. Status and errors are accessible; arbitrary server exception messages are not displayed.

## Verification

- Six new backend tests cover consumed-token replay without revoking newly issued credentials, rollback on token-deletion failure, expired/unknown links without password changes, normalized email limits and expiry, IP limits, and invalid token input types/lengths.
- Existing reset-token, reset-event and account-revocation tests pass.
- Five new frontend tests cover email/token submission, success cleanup, password mismatch, expired-link recovery, rate limits and hidden exception details.
- Full backend suite: **305 tests, 1716 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **118 tests passed**; production build, lint, changed PHP formatting and Git whitespace checks passed.
- Real localhost browser opened an invalid fixture link and verified the email prefill and new-link navigation control. No password was entered or changed in the browser. Screenshot: workspace parent `password-reset-prefill.png`. Actual reset mutations were tested only in disposable automated test databases.

## Rollout and remaining verification

No migration is required. This transaction uses the application's current database-backed password broker on the same default connection as users and API tokens. Preserve that configuration; moving reset tokens to a cache or separate connection requires a new atomic-consumption design. Use shared production cache for request limits and verify trusted proxies supply the intended client IP.

Run competing reset requests against the production database engine in staging; SQLite tests prove rollback and sequential replay behavior, not real row-lock concurrency. Rehearse an actual emailed reset link, secure cookie domains, subsequent fresh sign-in and rejection of old sessions/tokens. Mail delivery and full external staging journeys remain unverified.
