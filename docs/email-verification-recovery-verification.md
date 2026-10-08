# Email verification resend and recovery

The authenticated resend API now returns JSON for both `verification-link-sent` and `already-verified`, with no-store headers. It reloads the account before checking verification, eligibility and the destination email. A stale authenticated object cannot resend to an obsolete address or send after an already committed suspension. Inactive accounts and unapproved administrators are rejected. Already verified accounts send no notification.

Mail exceptions return HTTP 503 with retry guidance and a sanitized `Verification email delivery failed` warning containing only the exception class. Email-verification timestamps remain unchanged by delivery failures. The existing six-per-minute resend throttle remains enforced and preserves `Retry-After` headers. Verification notifications now use the configured `verification.expire` setting instead of an unrelated configuration path.

The frontend interprets explicit resend statuses, refreshes an already verified account, avoids claiming delivery for malformed responses, and explains rate limits and delivery failures without echoing arbitrary server error messages. The `invalid-link` query code displays fixed recovery guidance rather than raw query text.

## Verification

- Six new backend tests cover stale verification/email/status snapshots, delivery failure with sanitized logging, resend throttling and configured link expiration. Existing signed-link, email-change and verification tests pass.
- Seven new frontend tests cover invalid-link guidance, sent/already-verified/malformed statuses, and HTTP 429/503/500 recovery.
- Full backend suite: **311 tests, 1744 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **125 tests passed**; production build, full lint, changed PHP formatting and Git whitespace checks passed.
- Actual localhost browser signed in an isolated unverified fixture, reached the verification screen and displayed invalid-link recovery guidance. No email was sent and no verification decision or credential change was performed. Screenshot: workspace parent `email-verification-recovery.png`.

## Rollout and limitations

No migration is required. Deploy the API and frontend together, rebuild configuration caches and restart processes as required by the host. Configure `verification.expire` deliberately and monitor the sanitized delivery-failure warning. SMTP acceptance does not prove inbox delivery; rehearse real mail, expiration and verified-account refresh in staging.

Reloading the account before sending is not an atomic lock across external mail delivery. An email change after the reload can still cause an older-address notification to be sent; its signed hash cannot verify a different current address because verification rechecks that hash under the account lock. Shared rate-limit cache, trusted proxy configuration, concurrent production-engine tests and complete deployed-cookie journeys remain release requirements.
