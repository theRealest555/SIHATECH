# Social provider availability

The login page loads public availability flags from `GET /api/public/auth/providers`. Google and Facebook buttons remain disabled until valid options arrive. Missing configuration or a disabled provider keeps its button disabled; a failed or malformed response offers a retry while email/password sign-in stays usable. Facebook's available option explains that it supports already linked accounts only.

The endpoint exposes booleans only and uses `Cache-Control: no-store`. Both redirects and authentication callbacks independently reject unavailable providers before calling Socialite. A provider cancellation still returns the harmless cancellation message without authentication.

Availability requires a supported provider, its enabled flag, nonempty client ID/secret, and a valid callback URL with the exact `/api/auth/social/{provider}/callback` path. Production and staging require HTTPS. URLs containing user credentials, queries or fragments are rejected. These checks do not prove the credentials, consent settings, callback host or external provider registration are correct.

## Verification

- Three new backend tests cover secret-free public flags, disabled/missing configuration, redirect/callback rejection without provider calls, callback URL validation and staging HTTPS enforcement.
- Four new frontend tests cover disabled options, configured options and Facebook guidance, failed loading with retry, and malformed options.
- Full backend suite: **294 tests, 1631 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **109 tests passed**; production build, full lint and changed PHP formatting passed.
- Actual local browser/API: missing provider configuration disables both buttons while email sign-in stays enabled. A direct Google redirect request returns `/login?error=provider_unavailable` with recovery guidance. No external OAuth request, account creation or email occurred. Screenshot: workspace parent `social-provider-availability.png`.

## Rollout

Deploy the API endpoint with the frontend. Configure `GOOGLE_LOGIN_ENABLED` / `FACEBOOK_LOGIN_ENABLED`, client IDs, client secrets and registered HTTPS callback URLs. Rebuild the host's configuration cache and restart long-running processes after configuration changes. Disabling a provider also prevents in-flight authentication callbacks from completing. No migration is required for this batch; the earlier identity uniqueness migration remains required.

Rehearse successful and cancelled OAuth on staging with the actual registered callback domains, consent configuration, secure cookies and provider credentials. Authenticated account linking remains unimplemented; matching emails never authorize linking automatically. Configuration availability is not an external integration health check.
