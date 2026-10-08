# Account revocation and profile photos — 6 October 2026

Verification used the isolated booking-preview.sqlite database, generated accounts and image/PDF fixtures. No production credentials or personal files were changed. The preview password was restored through the same password-change API after the session test.

| Boundary | Result | Evidence |
| --- | --- | --- |
| Patient / doctor password change | API tests passed | Current password checked under a row lock; current browser retained; auth version incremented, remember token rotated and every API token removed |
| Revoked token / stale or unstamped session | API tests passed | Old bearer token rejected; old browser marker rejected; unstamped sessions may bootstrap only before the first security change |
| Password reset and stale email | API/service tests passed | Reset revokes prior access; a reset callback cannot change credentials after its original email no longer matches |
| Login racing a reset / admin demotion | API tests passed | Fresh token issuance rechecks locked password and account approval, preventing tokens from stale credential checks |
| Suspension / reactivation and admin demotion / reapproval | API tests passed | Old sessions and tokens stay revoked after access is restored; audited changes are atomic |
| Audit failure during admin reset | API test passed | Password, auth version and token deletion roll back with the failed audit entry |
| Photo validation and failed storage/database writes | API tests passed for both roles | Invalid/oversized files return 422; failed writes return safe 503; existing file and profile path preserved; unused new file removed on database failure |
| Successful replacement / failed old-file cleanup | API tests passed | Old managed file removed after commit; cleanup failure does not undo replacement; unrelated legacy storage paths never deleted |
| Profile upload → real API → stored photo → browser | Browser passed | Generated 128px PNG uploaded and rendered from configured API origin; persisted across reload |
| Non-image upload → validation → retry | Browser passed | PDF rejected with a visible modal error; valid PNG retry replaced the photo, removed the previous file and matched fixture SHA-256 |
| Independent cookie client password change → browser revocation | Real HTTP and browser passed | Changing client's next GET /api/user remained authenticated; original browser's next API request redirected to sign-in; a fresh login succeeded |
| Client expiration and private caches | Frontend tests passed | 401 clears identity; sign-out clears profile/appointment caches; late responses from an earlier sign-in are ignored; 422 preserves identity |

Checks: 208 backend tests / 1064 assertions, 63 frontend tests and production build passed. Changed frontend files lint and Git whitespace checks passed. Overall frontend lint remains 71 errors and 4 warnings.

Screenshots profile-photo.png, profile-photo-validation.png and revoked-session.png are saved in the parent workspace. Generated image SHA-256: 79a678af3787f67a05fece82b49e15d979154cd0eabbbbc68d9eee0cd7fe884a. Preview servers are stopped after verification.

Rollout requires the auth_version migration before this code is deployed. New sign-ins stamp the version; legacy sessions can bootstrap at version zero until the first security change. Revocation is enforced on the next authenticated request, including top-level email-link navigation. Already displayed data and requests that were in flight cannot be withdrawn by a subsequent password change. The checks support file, database and other session drivers without enumerating session storage, but deployed domain/cookie behavior and real MySQL concurrency still need staging validation.

Public photos use the public storage disk and configured API host. Complete private credential migration before exposing public storage, run php artisan storage:link, and configure the web server to serve image files without executing uploads. If cleanup fails, an unused image may remain on disk for operational cleanup. A process crash between storing a new image and database save can also leave an orphan; storage reconciliation remains operational work.

Remaining release work includes doctor statistics, administrator moderation/reference management, real reviews, lint cleanup, provider and email integration tests, MySQL concurrency, queue/scheduler operations, backup/restore drills and reconciliation of historical records. See production-fixes.md.
