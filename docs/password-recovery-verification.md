# Password recovery privacy and request limits

Valid password-recovery requests return identical HTTP 200 bodies for existing accounts, unknown addresses and the password broker's resend cooldown. Responses contain conditional guidance in both `message` and the retained `status` field and are not cached. Invalid email input still returns field validation errors. Existing accounts receive the normal Laravel reset notification; unknown accounts create no reset token. The broker retains its existing token expiration and resend cooldown.

Delivery exceptions also return the conditional public response. A sanitized `Password reset delivery failed` warning records the exception class only, without the email, token, exception message or stack trace. Operators must alert on this warning; HTTP 200 means the request was handled, not that email delivery succeeded.

A dedicated limiter permits three requests per minute per normalized email address across source IPs, and ten per minute per source IP across addresses. Email keys are SHA-256 hashes; request counts apply equally to existing and unknown addresses. The API exception renderer now preserves HTTP exception headers, including `Retry-After`, on JSON rate-limit responses. The frontend explains throttling and uses accessible status/error messages without echoing arbitrary server exception details.

## Verification

- Five new backend tests verify identical public responses, real broker cooldown with no duplicate notification or token replacement, sanitized delivery-failure handling, email/IP limits and expiry, and invalid input without token creation.
- Existing reset-token and password-reset tests still pass; the former unknown-account rejection test now expects the generic response.
- Four new frontend tests cover submitted address/CSRF request, conditional fallback messaging, rate-limit recovery and hidden exception details.
- Full backend suite: **299 tests, 1668 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **113 tests passed**; production build, lint, changed PHP formatting and Git whitespace checks passed.
- Actual localhost browser submitted `unknown-recovery@preview.test` against the isolated API and displayed conditional success guidance. This unknown fixture address sends no email and changes no account credentials. Screenshot: workspace parent `password-recovery-privacy.png`.

## Rollout and limits

No migration is required. Deploy frontend and API changes and rebuild route/config caches as required by the host. Use a shared production cache for limits across application instances and verify the host's trusted proxy configuration produces the intended client IP. Rehearse real reset-email delivery and operator alerts in staging.

These changes remove the explicit response/status account-existence signal from password recovery. They do not establish identical request timing: existing-account notification delivery is synchronous, and registration still has its own account-existence validation. Dedicated limits can temporarily delay legitimate recovery requests to an address; monitor abuse and tune them with operational evidence. Do not describe the application as fully resistant to account enumeration based on this batch.
