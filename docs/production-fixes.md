# SIHATECH implementation status — 6 October 2026

Branch: `codex/production-fixes`, based on main commit `03f3e326be2fcdf57bb9591ac0776beab115f75c`.
The security foundation and real doctor-search/booking screens are implemented. The application is **not yet cleared for production**.

## Implemented

- Repaired the frontend service modules, mounted Redux, and consolidated authentication around one user state. Browser authentication uses Sanctum session cookies and CSRF protection; persisted browser bearer tokens were removed. Failed logout preserves authenticated UI state until the server confirms logout.
- Corrected doctor role names, registration fields and speciality selection, email verification, password-reset links, and doctor profile completion. Email verification and social callbacks use web session middleware, including direct navigation from an email link.
- Restricted public registration to patient and doctor roles. Admin login and admin routes require an active user and an approved admin record. Removed sensitive authentication logging and raw exception details from changed API responses.
- Replaced social callback credentials in URLs with a session. Provider state validation is enabled. New Google accounts require a verified provider email; matching an existing account's email cannot automatically link or authenticate that account. New Facebook registration/account linking still needs an explicit verified account-linking flow.
- Stored doctor credential documents on a private disk. Downloads require the owning doctor or an authorized admin. Added a dry-run migration command for existing public files.
- Preserved the schema's `rendezvous.patient_id -> patients.id` contract, fixed ownership and statistics queries, and updated notification recipients to the patient's user.
- Centralized appointment availability, checked doctor eligibility, future schedule slots, leave and overlap, and serialized booking using patient/doctor row locks. Patients can cancel their own future bookings; doctors follow explicit appointment status transitions.
- Kept unpaid subscriptions pending, passed the selected payment method to Stripe, added idempotent subscription creation and webhook event tracking, and reconciled successful invoices without duplicate payments. Receipt jobs no longer grant paid access. Failed provider cancellation no longer marks the local subscription cancelled.
- Added notification and Stripe-event tables plus appointment indexes. Repaired the test suite, added security/auth/payment/document regressions, and isolated tests from a real database.
- Updated to Laravel 12 with PHP 8.3-compatible dependency resolution, declared missing Socialite and Stripe dependencies, removed unused obsolete PHPExcel/Excel packages, updated frontend dependencies, and enabled Tailwind compilation.
- Added CI for backend tests on PHP 8.3/8.5 and frontend lint/tests/build on Node 24, plus dependency audits. Action versions are pinned to commits. The workflow has not run on GitHub yet. Frontend lint now blocks workflow success.

## Second implementation batch: real booking screens

- Replaced sample doctor search and profiles with real API records, live specialities/languages, submitted search filters and pagination. A chosen search date shows available slot counts and carries through to the profile. Date selection also survives the sign-in return path.
- Replaced the broken separate booking link with live slot selection and a patient appointment request on the doctor profile. Availability, empty results, leave, loading, retry and validation/conflict errors have explicit UI states. Conflicting bookings refresh slots; uncertain network results direct the patient to check their existing appointments.
- Replaced patient/doctor appointment mocks with an API-backed shared list. Pending bookings are visible and cancellable; doctors can confirm future requests and complete past confirmed visits. Changes require confirmation and only display success after a server response. Lists refresh after failures as well as successful changes.
- Added validated period filters and bounded appointment pagination, preserving ownership regardless of requested doctor/patient filters. Closed the general appointment-list bypass for unapproved admin accounts.
- Unified search slot generation with booking availability, including partial overlaps and leave. Disabled doctor accounts are excluded from public discovery, detail, statistics, availability and slots.
- Added explicit timezone metadata and ISO appointment timestamps with offsets. Displays preserve the server's clinic wall time rather than relying on matching PHP/JavaScript timezone databases. The server's configured timezone remains authoritative for booking; keep its timezone data current.
- Made no-show updates conditional on the current appointment status so stale jobs/manual updates cannot overwrite completed or cancelled records. The legacy scheduler remains inactive pending an operational review.
- Repaired test factories that created unrelated doctors/patients even when IDs were overridden. Added API journey tests and browser-like React interaction tests covering booking conflicts, stale slot responses, cancellation failures and doctor status actions.
- Fixed navigation contrast, added an appointments link and a usable mobile menu, and removed unsupported admin profile navigation. Removed fake profile fees, qualifications and review submissions; review submission still needs a real backend flow.

The real browser journey is documented in [booking-verification.md](booking-verification.md).

## Third implementation batch: doctor availability and private documents

- Replaced mock availability with the doctor's stored weekly schedule, inclusive leave dates, real saves/removals, verification gating, server timezone/date metadata and explicit errors. Added navigation and corrected profile shortcuts.
- Validate French weekday keys, zero-padded time ranges, minimum appointment duration, and overlapping ranges. Both schedule and profile changes now protect the full appointment duration and slot grid under the doctor's booking lock. Profile updates accept array or JSON schedules and preserve the schedule when omitted.
- Replaced mock document uploads/statuses with multipart uploads, real pending/approved/rejected states and private authenticated blob downloads. Approved credentials cannot be deleted; other deletions require explicit UI confirmation. Failed uploads retain the selected file.
- Restrict credentials to PDF/JPEG/PNG up to 10 MB. Failed database inserts clean up newly uploaded files; failed storage deletion does not delete its record. Admin review and doctor deletion share a document row lock; approving a previously rejected document clears stale rejection feedback.
- Added schedule integrity API tests and eight doctor workspace UI regression tests. Browser verification saved schedule and leave to isolated SQLite, uploaded a generated test PDF and downloaded matching bytes through the authenticated API. See [doctor-workspace-verification.md](doctor-workspace-verification.md).

## Fourth implementation batch: subscription screens and payment confirmation

- Replaced mock plans and subscription status with public, active plans and authenticated subscription/payment records. Prices use MAD and the stored billing cycle. No hardcoded card details, paid status or popularity claims remain in the screens.
- Added official Stripe React/browser components for secure card setup, payment confirmation and authentication challenges. Customers explicitly authorize the recurring price before submission. Payment retries use the existing subscription rather than creating another charge; pending attempts remain resumable on My subscription.
- Pending and cancelled subscriptions stay visible. Client-side payment completion never grants access or claims an active subscription; paid access comes from server confirmation. Cancellation wording now matches immediate provider cancellation, and provider errors preserve the current state.
- Serialize subscription creation/cancellation by user, reject active/pending duplicates, protect customer creation with a user lock and idempotency key, and retain uncertain provider failures for reconciliation. Pending records without a provider ID require support reconciliation before cancellation to avoid hiding a possible remote charge.
- Validate Stripe's active price, MAD currency, amount and interval against the stored plan before creating a provider subscription. Fixed price-ID persistence and six-month/month-end date handling. Updated invoice expansion to the installed SDK's Basil confirmation_secret contract.
- Prevent subscription status events alone from activating unpaid subscriptions, hide internal payment metadata from serialized subscription records, and expose resumable client secrets only through the authenticated owner's status endpoint.
- Added seven subscription API tests, two Stripe request/webhook contract tests and ten frontend payment interaction tests. See [subscription-verification.md](subscription-verification.md). Real Stripe payment and renewal verification remains required in staging.

## Fifth implementation batch: administrator credential review

- Connected the administrator verification route and navigation. Replaced incorrect doctor-ID document rejection and direct file paths with real document decisions and authenticated private downloads.
- Added validated doctor search, verification filters, bounded pagination and a detail endpoint exposing only the needed user fields. Credential detail reports missing files, required types and missing approved credentials.
- Medical licence is the default required credential in verification.required_document_types. Verification requires an approved file that actually exists, a verified email, active account and speciality. Additional required credential types can be configured after defining the operating policy.
- Review, verification and revocation run in transactions under the doctor's stable row lock. The review UI sends the document's observed status; stale review requests return 409. Repeated verification does not create duplicate audit records. Rejection of the last approved required credential revokes verification and records both actions.
- Replaced mock admin dashboard counts and unsupported shortcuts with actual database statistics and working links. Removed the unverified Operational claim and external name-based avatar requests from credential review.
- Added eight administrator API journey tests and nine UI tests. Real browser verification covered approved admin sign-in, actual counts, private download, credential approval, doctor verification and required-credential rejection/revocation. See [admin-verification.md](admin-verification.md).

## Sixth implementation batch: profile email security and workspace dashboards

- Email changes require the current password and clear email_verified_at for both patients and doctors. The profile transaction locks the user row; verification mail is sent after the transaction. Delivery failure preserves the committed change and allows resend from the verification page.
- Profile responses include verification-required/sent flags. Redux immediately updates the authenticated identity, protected routes send the user to verification, and the verification screen displays the current address. Provider users must first sign out and set a password through password reset.
- Signed verification links recheck the current email under a user row lock. A stale request or an old address link cannot verify a replacement address. Doctors with an unverified email are excluded from public profiles, search, statistics, availability and booking slots; existing appointments remain stored.
- Patient profile edits preserve omitted optional fields and can explicitly clear the preferred doctor. Password/session revocation and safe photo replacement still require a separate batch.
- Replaced doctor and patient dashboards with real upcoming appointment totals, next three visits, pending/confirmed status and clinic time. Doctor credential counts come from owned documents. Loading, empty, retry, partial failure and stale-request handling are explicit. Removed unsupported patient-record links and sample counts.
- Added API regressions for both profile roles, verification restoration, stale identity links and public eligibility. Added dashboard interaction tests and Redux tests proving authentication loses verified access without retaining password values.

Local verification details are in [profile-dashboard-verification.md](profile-dashboard-verification.md).

## Seventh implementation batch: account revocation and safe profile photos

- Added a user authentication version, stamped on browser sign-in and checked on authenticated API/email-link requests. Security changes increment it, rotate remember tokens and delete all personal access tokens. Existing sessions can bootstrap at version zero; once revoked, unstamped or stale sessions must sign in again.
- Shared password-change logic verifies the current password under a user row lock, preserves the current browser session, rotates its session identifier and revokes other sessions and every API token. Bearer clients receive reauthentication_required because the token used for the request is revoked too.
- Password-reset links and administrator resets use the same revocation logic. Reset callbacks recheck the email under the lock; API token issuance rechecks password/account approval under the same lock so a concurrent reset or suspension cannot grant a fresh token from stale credentials.
- Account suspension and administrator demotion revoke credentials atomically with their audit entry. Reactivation/reapproval never restores old sessions. The UI handles 401 responses by clearing authentication and cached private records; late Redux responses from a previous sign-in are ignored.
- Shared photo replacement validates JPEG/PNG/WebP images up to 5 MB, stores the new file first, locks/saves the user and deletes the old managed photo only after commit. Failed saves clean up new files and preserve the original. Cleanup failures are reported without undoing a committed replacement; arbitrary legacy paths are never deleted.
- Profile uploads use browser-managed multipart boundaries, keep raw photo paths consistent in Redux/authentication and use the configured API origin with initials as a fallback. Removed hardcoded localhost/photo placeholder URLs, added accessible upload controls and visible errors inside the upload modal.
- Added 24 backend security/failure tests and 11 frontend regression tests. Actual local verification covered photo upload/reload and revocation across an independent HTTP cookie session and the browser. Details are in [account-photo-verification.md](account-photo-verification.md).

## Eighth batch: real doctor statistics

- Replaced sample statistics with authenticated, doctor-scoped appointment and patient aggregates, approved review averages and zero-filled daily counts.
- Applied week/month/year and inclusive custom date filters consistently; reports are bounded to 366 days. Completed consultations determine patients seen and repeat visits.
- Removed subscription-payment earnings and invented performance figures. Consultation revenue is explicitly unavailable until actual billing is implemented.
- Added aggregate CSV exports matching the displayed range, retry/error/empty states, stale-response protection and doctor navigation. Replaced service-mock backend tests with real database coverage and added frontend interaction tests.
- Browser and independent authenticated HTTP checks used isolated preview data. Details and limitations are in [doctor-statistics-verification.md](doctor-statistics-verification.md).
## Ninth batch: administrator audit history

- Replaced invented audit events and IP addresses with authenticated recorded administrator actions, inclusive date/action/actor filters and stable pagination.
- Added safe response fields, retry/empty/stale-response handling and administrator dashboard/navigation links. Arbitrary metadata is excluded; missing actors are clearly identified.
- A new migration preserves audit rows when actor accounts are deleted and indexes the listing queries. User deletion and audit creation now commit together; failed audit writes preserve the account.
- Added six database-backed tests and five UI tests; real browser checks display the earlier isolated credential decisions and filter them correctly. Details: [admin-audit-verification.md](admin-audit-verification.md).
## Tenth batch: speciality and language management

- Replaced mock screens with real administrator catalogue creation/editing, paginated literal search and usage counts, including archived doctor profiles.
- Updates compare original values under a lock, preserve assignments, and share a transaction with their audit record. Duplicate names are validated and database unique indexes protect concurrent writes.
- The new unique-name migration stops on historical duplicates for deliberate reconciliation; it never merges or reassigns existing records. Added seven backend and five frontend tests, real browser persistence/search checks and navigation.
- Details and migration limits: [admin-catalogue-verification.md](admin-catalogue-verification.md).
## Eleventh batch: review submission and moderation

- Added completed-past-appointment review submission with profile ownership, server-derived identities, validated feedback, pending moderation and one review per appointment.
- Replaced insecure moderation with a real queue and locked expected-status decisions. Approval validates historical appointment context; rejection reasons, doctor rating aggregates and audit entries commit atomically.
- Added safe responses, patient status/reason display, explicit admin confirmation, conflict/stale response handling and real appointment/navigation links. Eleven backend and eight UI tests cover the flow.
- Real patient → administrator browser checks verified submission, approval, rating update, rejection, rating removal and audit entries. Details: [review-verification.md](review-verification.md).
## Twelfth batch: administrator subscription plans

- Replaced mock plan management with actual listing, drafts, edits and new-version creation. Deactivation stops new purchases while preserving existing subscriptions.
- Corrected the plan-to-subscription foreign key, fixed billing fields once any subscription history exists, and added locked version checks with atomic audit records.
- Active plans require a verified matching MAD recurring Stripe price. Checkout rechecks availability and the customer-reviewed plan version under the same plan lock; provider creation bills the verified plan price.
- Added nine backend tests and four UI tests, updated checkout coverage, and verified draft creation/editing, reload, audit history and exclusion from public purchases through the real local browser. Details: [admin-plan-verification.md](admin-plan-verification.md).

## Thirteenth batch: administrator reports

- Replaced sample reports with real payment and appointment summaries, validated inclusive date ranges, retry/empty/stale-response handling and administrator navigation.
- Separated completed payment amounts by currency, labelled record creation dates and current statuses accurately, and distinguished current active subscriptions from selected-range activity. Appointment reports use scheduled dates and include daily/hourly counts and archived doctor profiles.
- Replaced personal financial exports with safe aggregate CSVs matching displayed filters, including appointment exports and spreadsheet formula neutralization. Seven backend tests replace four old tests; five UI tests cover report interactions.
- Real browser and independent authenticated HTTP checks verified isolated payment totals, appointment statuses and both exports. Browser file-download delivery remains unconfirmed. Details: [admin-report-verification.md](admin-report-verification.md).

## Fourteenth batch: frontend lint and CI

- Fixed all 43 outstanding lint errors by removing unused React imports and dead calendar variables and adding explicit component prop contracts. No lint rules were disabled or suppressed.
- ESLint detects the installed React version. `npm run lint` rejects warnings as well as errors, and the CI lint step no longer allows failure.
- The entire frontend now passes lint with zero errors and warnings. All 94 frontend tests and the production build pass after the cleanup. This batch does not change backend behavior or require a migration.
- The workflow change remains local; GitHub CI execution and repository branch-protection settings have not been verified or changed. Details: [frontend-lint-verification.md](frontend-lint-verification.md).

## Fifteenth batch: profile and schedule stale edits

- Added account-bound content revisions to patient/doctor profiles, doctor onboarding and weekly schedules. Locked writes reject outdated data before modifying records or sending email notifications; profile and availability schedule writes share the protection.
- Preserved active drafts during background refreshes and provided explicit conflict reloads. Ordinary doctor profile saves omit hours that are not editable there. Fixed unintended submission when opening the profile editor and associated form labels with controls.
- Six new backend tests and two new profile interaction tests cover the contract. A real isolated patient browser draft conflicted with a separate authenticated save, retained its local edits and successfully reloaded the newer profile.
- No migration is required for this batch, but frontend and API deployment must be coordinated because writes require revision tokens. Details: [profile-revision-verification.md](profile-revision-verification.md).

## Sixteenth batch: readiness and background operations

- Replaced hard-coded health success with real database/cache checks and non-cached, session-free HTTP 503 failure responses. Optional production readiness also requires recent scheduler and asynchronous-worker heartbeats.
- Registered the supported minute heartbeat through `routes/console.php`; removed the obsolete kernel's dormant commands. Queue probes use their dispatch time so delayed backlog cannot falsely refresh health. Asynchronous queue dispatch now waits for transaction commit.
- Seven new tests include real database-worker execution and rollback-discarded dispatch. Documented supervised workers, scheduler configuration, failure monitoring and restore drills. Clinical no-show and provider-renewal automation remain unscheduled pending policy/reconciliation fixes.
- No migration or permanent hosting configuration was added. Details: [operations-verification.md](operations-verification.md).

## Seventeenth batch: appointment reminders

- Added a five-minute, feature-flagged reminder scan for confirmed visits within 24 hours, with a durable appointment/time ledger and indexes. Duplicate scans and jobs share a locked delivery claim; queue failures leave pending work recoverable.
- Recheck cancellation, rescheduling, elapsed time and account eligibility before delivery. Persist database notifications atomically with the mail claim; uncertain/interrupted mail attempts are recorded for investigation instead of automatic duplicate retries.
- Corrected the reminder's frontend link and included the application timezone. Seven new tests cover eligibility, repeated work, delivery conflicts, flags and mail failures. No real email was sent; reminders remain disabled by default.
- Apply the new migration before enablement and rehearse real mail delivery and failure monitoring in staging. Details: [appointment-reminder-verification.md](appointment-reminder-verification.md).

## Eighteenth batch: subscription period safety

- Replaced the legacy renewal job's date-based expiry writes and automatic renewal notices with a read-only aggregate audit. Added `subscriptions:audit-periods` for operator checks without contacting Stripe or changing access.
- Aligned model and query access checks at the exact period-end boundary; pending, future and ended periods no longer show as expiring access. Corrected the retained notice's frontend link and removed unsupported renewal-price/new-purchase claims.
- Five regression tests cover boundaries, counts, unchanged statuses, repeated job execution and template behavior. No migration or new schedule is required; restart workers on rollout. Details: [subscription-period-verification.md](subscription-period-verification.md).

## Nineteenth batch: attendance decision safety

- Replaced automatic no-show marking with a read-only attendance audit, retaining the old job class for queued payloads. Added `appointments:audit-attendance` to distinguish overdue confirmed visits from unconfirmed requests without changing records.
- Manual doctor/admin decisions now require confirmed past visits and share the doctor/appointment locking order with other status writes. Actor/target/transition audit history commits with the decision; failed audit writes roll it back.
- Seven tests cover unchanged statuses, duplicate decisions, authorization, rollback and notice links. No migration, automatic attendance schedule or new frontend UI was added. Details: [attendance-verification.md](attendance-verification.md).

## Twentieth batch: attendance controls and audit display

- Aligned doctor no-show controls with confirmed past visits, explained unconfirmed requests and added explicit attendance-check wording to confirmation.
- Added the attendance action filter, appointment target label and validated status transition to administrator audit history. Broadened page wording to include doctor attendance actions.
- Three new frontend interactions and one backend projection test cover eligibility, confirmation, conflict reload and history filtering. A real isolated doctor-to-admin browser journey verified the saved decision and matching audit row. Details: [attendance-ui-verification.md](attendance-ui-verification.md).

## Twenty-first batch: social sign-in recovery

- Displayed actionable OAuth failure messages on login, with safe unknown-code fallback and dismiss behavior. Distinguish cancelled sign-in and expired provider state without exposing exception details. Replaced the shared authentication layout's broken external placeholder logo with a text wordmark.
- Reject missing/invalid provider IDs and ambiguous historical mappings before authentication; preserve the refusal to link accounts by matching email alone.
- Four new backend tests and six frontend tests cover identity failures and recovery screens. Actual local cancellation callback → login message → dismissal passed in the browser. Live OAuth and authenticated linking remain staging/implementation work. Details: [social-signin-verification.md](social-signin-verification.md).

## Twenty-second batch: social identity uniqueness

- Added a provider/ID unique index to prevent duplicate account mappings at the database boundary, while allowing null password-account mappings and IDs scoped to different providers.
- Migration preflight refuses duplicate, incomplete or blank historical mappings without changing account data. Runtime identity guards remain, and the legacy duplicate regression represents an unmigrated database explicitly.
- Six new tests verify constraints, preflight safety, historical data preservation and rollback. Apply and rehearse the new migration before release; authenticated social linking remains unimplemented. Details: [social-identity-integrity-verification.md](social-identity-integrity-verification.md).

## Twenty-third batch: social provider availability

- Added a secret-free public availability endpoint and configuration checks on both provider redirects and callbacks. Disabled or incomplete providers never call Socialite; staging/production callback URLs require HTTPS and the correct path.
- Login buttons reflect availability, remain disabled during loading/failure, and offer a retry on metadata errors. Email sign-in remains usable; Facebook explains its existing-account restriction.
- Three backend and four frontend tests cover configuration and loading behavior. Actual localhost browser verification confirmed disabled providers and the unavailable-provider redirect. Details: [social-provider-availability-verification.md](social-provider-availability-verification.md).

## Twenty-fourth batch: password recovery privacy and limits

- Existing, unknown and broker-throttled email addresses receive the same conditional recovery response. Delivery failures produce a sanitized operator warning and the same public response; the broker's reset tokens and cooldown remain in use.
- Dedicated limits cover requests per email across IPs and per IP across addresses. JSON HTTP errors preserve rate-limit headers; frontend recovery messages explain throttling and avoid arbitrary server details.
- Five new backend and four frontend tests cover privacy, delivery failures, limits, cooldown and recovery UX. The real localhost browser verified conditional guidance for an unknown fixture address. Details and operational limits: [password-recovery-verification.md](password-recovery-verification.md).

## Twenty-fifth batch: password reset safety and recovery

- Account locking now precedes broker token validation; password changes, access revocation and token consumption share a transaction. Reset events follow successful transactions, and invalid/unknown links use the same public validation message.
- Added reset-specific email/IP limits and bounded input validation. The reset form uses the emailed address, clears passwords after success, prevents resubmission and provides explicit recovery/sign-in navigation.
- Six backend and five frontend tests cover replay, rollback, expiry, limits and reset UX. Browser verification confirmed fixture-link email prefill without changing credentials. Production-engine concurrency and real mail journeys remain staging work. Details: [password-reset-safety-verification.md](password-reset-safety-verification.md).

## Twenty-sixth batch: email verification resend and recovery

- Resend now returns explicit JSON statuses, refreshes account state/email before sending, rejects unavailable accounts and handles delivery failures with HTTP 503 and sanitized warnings. Already verified accounts send no mail.
- Notifications use configured verification expiry. The UI handles already-verified/malformed responses, invalid links, throttling and delivery failures without arbitrary server details.
- Six backend and seven frontend tests cover stale state, resend limits, expiry and recovery. Browser verification used an isolated unverified account without sending mail. Details and race limitations: [email-verification-recovery-verification.md](email-verification-recovery-verification.md).

## Twenty-seventh batch: durable credential file cleanup

- Doctor deletion now removes the record and queues private-file cleanup in one transaction, preserving files on database rollback. A scheduled command retries storage failures and refuses unsafe or still-referenced paths.
- Doctor metadata hides storage paths and reports file availability; missing-file downloads are disabled, private download headers are explicit, and deletion text explains queued cleanup.
- Five backend tests and one frontend test cover rollback, retry, cleanup safety and missing-file recovery. Apply the new cleanup migration and supervise its scheduler before deployment. Details: [document-cleanup-verification.md](document-cleanup-verification.md).

## Twenty-eighth batch: account history preservation and user management

- Disabled unsafe permanent account deletion, whose cascades discarded clinical/billing history and orphaned private files. Existing deactivation revokes access while retaining records; administrator self-deactivation/demotion is blocked.
- Repaired the user list's paginator, names and role labels; added submitted search, filters and explicit status confirmation; removed links to unimplemented account routes and the deletion control.
- Four backend and four frontend tests cover retained records, deactivation, self-lockout and list behavior. Audit persistence failure now verifies deactivation rollback. Details and retention limitations: [account-retention-verification.md](account-retention-verification.md).

## Twenty-ninth batch: administrator creation integrity

- Account, approval profile and audit entry now commit together, with rollback on profile/audit failures and a fresh locked check of the requesting administrator's eligibility/authentication version.
- Creation requires the actor's current password and confirmation of the new password, has a dedicated request limit, uses no-store responses and sanitizes failure logging.
- Six backend regression tests cover rollback, stale actors, reauthentication and throttling. No administrator was created outside disposable automated test databases. Next fix: fresh confirmation and actor rechecks for administrator-driven password resets. Details: [admin-creation-verification.md](admin-creation-verification.md).

## Thirtieth batch: administrator password reset authorization

- Administrative resets now require fresh actor-password confirmation and matching new-password confirmation. Actor/target locks use ID order; actor eligibility and authentication version are rechecked inside the audited credential transaction.
- Validation, authorization and missing-target errors preserve their correct HTTP status. Success reports target access revocation; reset and creation use separate named rate limiters.
- Six backend tests cover stale/suspended actors, target-only revocation, confirmation, missing targets and throttling. No preview or external credential was changed. Next fix: invalidate outstanding recovery links on every password change. Details: [admin-password-reset-verification.md](admin-password-reset-verification.md).

## Thirty-first batch: recovery-link invalidation on password changes

- The shared credential transaction now invalidates outstanding password recovery tokens alongside password/access changes, covering patient/doctor profile updates, administrator resets and token-based recovery.
- Old links cannot overwrite newer credentials; fresh links still work. Token deletion and audit persistence failures preserve the previous credentials and recovery token through rollback.
- Six new test cases and an expanded audit-rollback assertion verify these flows. Next fix: invalidate recovery links on email-address changes. Details: [password-link-invalidation-verification.md](password-link-invalidation-verification.md).

## Thirty-second batch: recovery-link invalidation on email changes

- Patient and doctor email changes invalidate recovery tokens for the previous and destination addresses inside the locked profile transaction, preventing recovery links from following reused addresses.
- Unchanged addresses and rejected current-password checks preserve existing links. Token-deletion or profile-save failure restores the profile and both tokens through rollback; fresh recovery still works after a successful change.
- Six new cases cover both roles, address reuse and rollback. Next fix: serialize recovery-token issuance with credential/email changes. Details: [email-recovery-token-verification.md](email-recovery-token-verification.md).

## Thirty-third batch: recovery-token issuance serialization

- Recovery requests lock the matching account before broker token creation, sharing the lock used by email/password changes and resets. Broker lookup includes the locked account ID and email.
- Mail and the standard link-sent event run after the token transaction. Token-write failures restore prior tokens; transport failures retain the generic private response. Credential changes during delivery invalidate the committed link.
- Five new regression cases cover transaction ordering, rollback, delivery failure and credential changes during delivery. Next fix: locked actor authorization for administrator status/approval changes. Details: [recovery-issuance-verification.md](recovery-issuance-verification.md).

## Thirty-fourth batch: locked administrator status authorization

- Account-status and administrator-approval changes lock actor and target users in ascending ID order, then recheck current actor eligibility and authentication version before changing target access.
- Successful responses use no-store headers; authorization and missing-target failures preserve 403/404. Target access revocation and audit persistence remain atomic, with self-deactivation/demotion protection preserved.
- Fourteen new regression cases cover revoked actors, demotion after preliminary authorization, target-only revocation, rollback and missing targets. Next fix: reject stale administrator status decisions. Details: [admin-status-authorization-verification.md](admin-status-authorization-verification.md).

## Thirty-fifth batch: stale administrator status decisions

- Both status endpoints now require a signed expected revision covering account status, role, authentication version and approval. Locked revision checks reject stale decisions before changing access or writing audit history, including suspension/demotion followed by restoration.
- User listing/details and successful decisions return revisions. The user-management screen sends the captured revision and clears conflicted confirmations until the administrator reloads and reviews the current state.
- Eight backend and two frontend regression cases cover validation, stale/reloaded decisions and addressable UI recovery. Deploy API and frontend together; no migration is required. Next fix: safe status transitions in audit history. Details: [account-status-revision-verification.md](account-status-revision-verification.md).

## Thirty-sixth batch: access-change audit transitions

- Account-status and administrator-approval decisions record the locked previous state, resulting state and access-revocation outcome in their existing atomic audit transaction.
- Audit history projects only recognized transitions for matching actions/targets and displays recorded revocation and reasons separately. Unknown/legacy states are not reconstructed from current accounts; raw metadata remains hidden.
- Six backend and two frontend cases cover recorded transitions, restoration, unchanged decisions and malformed/legacy metadata. Next fix: reasons for access-removal decisions. Details: [account-status-audit-verification.md](account-status-audit-verification.md).

## Thirty-seventh batch: reasons for access removal

- Inactive/Pending account decisions and administrator demotion require a trimmed, nonblank reason bounded to 500 characters. Activation/approval can omit it.
- Reasons are stored atomically with access changes and projected into administrator audit history. The deactivation confirmation collects the reason, blocks blank submissions and preserves it on request failure.
- Nine backend cases and one new frontend case cover validation, Unicode bounds, audit projection and confirmation behavior. Deploy frontend and API together; no migration is required. Next fix: unchanged status decisions without repeated revocation or duplicate change history. Details: [account-access-reason-verification.md](account-access-reason-verification.md).

## Thirty-eighth batch: unchanged account-status decisions

- Status endpoints return `changed: false` for a currently matching locked state after authorization/revision checks. They preserve credentials, timestamps and history; actual changes return `changed: true` and retain atomic revocation/auditing.
- Stale retries still conflict even if the requested status now matches. Unknown legacy approvals are not treated as zero. User management reports unchanged results without claiming a fresh activation or revocation.
- Eight backend cases and one frontend case cover unchanged states, credential preservation, stale replay and legacy approvals. Next step: real-browser verification of user-management reasons, conflicts and audit details. Details: [unchanged-account-status-verification.md](unchanged-account-status-verification.md).

## Thirty-ninth batch: real-browser administrator access verification

- Restored the isolated local preview and verified real browser → API → SQLite behavior with one new empty synthetic patient fixture. Blank reasons block confirmation; a competing tab's completed change invalidates the older decision; reload and reactivation succeed.
- Audit history shows exactly the two valid transitions and the deactivation reason. The stale decision left no extra history; the fixture ended active with one authentication-version increment. Existing preview accounts were not changed.
- No application code changed. Screenshots and limits: [admin-access-browser-verification.md](admin-access-browser-verification.md). Next step: automated browser regression coverage for the administrator access workflow.

## Fortieth batch: automated administrator access regression

- Added a Chromium browser test that uses the real React application, Laravel API and a fresh SQLite database. It checks reason validation, a competing tab's stale revision, reactivation and exactly the two expected audit records.
- The runner isolates ports, application key and database; fixtures refuse existing data and cached configuration. Vitest excludes browser specifications, and the new CI job installs Chromium and retains failure diagnostics for seven days.
- The browser test, 394 backend tests, 136 frontend tests, lint, production build and npm audit passed locally. GitHub execution remains pending. Details and commands: [admin-access-e2e-verification.md](admin-access-e2e-verification.md). Next step: automate the patient booking, doctor confirmation and cancellation journey.

## Forty-first batch: automated patient and doctor booking journey

- Added a real Chromium journey for patient search and booking, doctor confirmation in an independent browser session, patient cancellation and released slot availability. The synthetic booking patient's user and profile IDs differ to catch appointment ownership regressions.
- The existing guarded runner now creates isolated booking fixtures and pins test time to UTC. CI discovers both browser specifications; the original administrator workflow still passes.
- Both browser workflows, frontend lint, seed formatting and whitespace checks passed. No application code or migrations changed. Details: [booking-e2e-verification.md](booking-e2e-verification.md). Next step: automate doctor schedule and leave changes around existing appointments.

## Forty-second batch: automated schedule and leave protections

- Added browser coverage for schedule and leave conflicts with both pending and confirmed appointments, compatible schedule extensions, stale schedule edits across tabs and inclusive leave dates on the public booking page.
- A separate synthetic doctor/patient keeps fixtures independent. Reloads prove persisted schedule/leave state; leave removal restores slots, and both existing appointment statuses remain unchanged.
- All three browser workflows, frontend lint, fixture formatting and whitespace checks passed locally. CI discovers the new specification; GitHub execution is pending. Details: [availability-e2e-verification.md](availability-e2e-verification.md). Next step: automate credential upload and administrator review with isolated private storage.

## Forty-third through forty-sixth batches: credentials, MySQL, mail and release preparation

- Added private credential upload/review/approval/rejection browser coverage and isolated storage. Hid file paths in legacy responses and disabled download caching. Credential privacy regression tests pass.
- Verified seven independent-process booking/availability races on MySQL 8.4.11 and added a fresh MySQL concurrency CI job.
- Added real local SMTP browser recovery/email-change coverage. Fixed empty MAIL_URL handling and Markdown verification-email rendering; verified link consumption, two-session revocation and new-address verification.
- Added a read-only, credential-safe integration configuration command and tests. Restored a synthetic MySQL backup and private file successfully. Prepared [release verification and staging gates](release-verification.md). Live provider, deployed cookie, historical data and required-check gates remain open.

## Verified locally

| Check | Result |
| --- | --- |
| Backend PHPUnit, PHP 8.5.1 / SQLite in memory | 399 tests, 2426 assertions passed |
| Frontend Vitest, Node 26.7.0 | 136 tests passed |
| Chromium administrator access, booking, availability, credentials and recovery, real API / fresh SQLite / local SMTP | 5 workflows passed |
| Real MySQL 8.4.11 concurrency | 7 booking/availability races passed |
| Synthetic MySQL/private-storage restore | 30 tables and private file hashes matched |
| Frontend production build | Passed |
| Composer manifest / lock validation | Passed |
| Composer locked dependency audit | No advisories or abandoned packages |
| npm dependency audit | Zero known vulnerabilities |
| Changed PHP formatting and Git whitespace checks | Passed |
| Real booking/availability/document/subscription/admin verification screens, shared navigation and new UI tests lint | Passed |
| Entire frontend lint | Passed: zero errors and warnings; warnings fail the check |
| Real local browser journey, isolated SQLite database | Booking and doctor availability/credential upload/download flows passed; mobile navigation checked |

Audits are point-in-time dependency checks, not proof that application security is complete. MySQL concurrency now covers booking, schedule and leave conflicts; other race scenarios still need staging verification. Mail delivery was exercised through a local SMTP sink. Live Stripe, Google/Facebook, external mail delivery and deployed browser-cookie flows were not exercised. Cookie authentication was exercised in Chromium on localhost with isolated accounts.

## Setup and staging migration

Use PHP 8.3 or later with the required Laravel extensions, Composer 2, Node 24 or later, and the production database engine in staging. Commit lock files and install from them; do not update dependencies during deployment.

```sh
cd backend
composer install
# Configure .env from .env.example for your own staging services.
# Generate an APP_KEY only for a new environment; retain the existing key on upgrades.
php artisan key:generate
php artisan migrate
php vendor/bin/phpunit

cd ../frontend
npm ci
# Configure VITE_API_BASE_URL using .env.example.
npm test
npm run build
```

On this Windows host, SQLite is installed but not enabled by default, so tests were run as `php -d extension=pdo_sqlite vendor/bin/phpunit`. Do not use `.env.testing` for deployment.

Audit existing verified doctors against the configured required credential types and file availability before enabling the new verification workflow. Private document migration must complete first for legacy public files. This batch does not automatically revoke existing verified doctors during deployment.

The audit-history migration preserves recorded rows for deleted actors but cannot recover previously deleted history. Rehearse its foreign-key/index changes against your production database before rollout. See [admin-audit-verification.md](admin-audit-verification.md) for limits and rollback behavior.

The catalogue unique-name migration must be rehearsed against existing names and the production database collation. It stops when duplicates exist; reconcile those references deliberately before deployment, as described in [admin-catalogue-verification.md](admin-catalogue-verification.md).

The review integrity migration refuses duplicate appointment-linked reviews and adds stored rejection reasons. Audit historical review context and ratings before rollout; see [review-verification.md](review-verification.md). Rollback drops stored moderation reasons.

The plan-version migration must run before deploying plan-management and checkout code. Deploy the frontend and API together because checkout now requires `expected_plan_version` from the public plan listing. Audit existing Stripe price mappings and rehearse concurrent plan edits/checkout in staging; see [admin-plan-verification.md](admin-plan-verification.md).

The authentication-version migration must run before deploying the code that reads it. Existing users start at version zero; a subsequent security change revokes their old sessions regardless of the session storage driver. New browser sign-ins stamp the current version. Rehearse migration and login against your deployed domains.

For an existing database, back up the database and document storage and rehearse the new migration against a staging copy. Review manually created notification tables/indexes before applying migrations. Do not use `migrate:fresh` against existing data.

**Existing appointment data needs a separate audit.** The previous booking controller wrote user IDs into the patient-profile foreign key. If those IDs coincidentally existed as patient IDs, an appointment may have been assigned to another patient. This change corrects new writes; it cannot safely infer and repair historical ownership. Reconcile affected records from reliable booking history before enabling patient access in production.

Move existing credential documents in a maintenance window from the backend directory:

```sh
php artisan documents:privatize
# Review the listed document IDs and ensure a storage backup exists.
php artisan documents:privatize --apply
php artisan documents:privatize
```

The command verifies private-copy SHA-256 hashes before deleting public copies and refuses to overwrite conflicting private files. Investigate failures and orphaned files manually. Ensure web-server/CDN caches no longer serve old public credential URLs. Do not move ordinary profile photos, which remain public. After private credential migration is complete, run php artisan storage:link to expose the public photo disk. Confirm the web server can serve those image URLs and cannot execute uploaded files.

Configure `APP_URL`, `APP_TIMEZONE`, `FRONTEND_URL`, `VITE_API_BASE_URL`, CORS origins, `SANCTUM_STATEFUL_DOMAINS` (including ports in development), session domain, secure cookies, and HTTPS consistently. Prefer frontend and API hosts under the same site for cookie authentication. Set `APP_DEBUG=false` in staging/production; store Stripe, OAuth and mail secrets outside Git. Point OAuth callbacks to `/api/auth/social/{provider}/callback`.

Review paid subscriptions before migration: earlier code could grant active access before confirmed payment. Reconcile with Stripe invoices and subscriptions. Configure the signed Stripe webhook and exercise successful, declined, retried, duplicate and renewal events in test mode before accepting money.

## Next work before release

1. Rehearse administrator reports, plan management and review moderation in staging, including concurrent decisions, provider reconciliation and browser export delivery. Test registration → email verification → doctor approval → doctor schedule configuration → patient booking in a browser with separate patient/doctor/admin accounts. Search → patient booking → doctor confirmation → patient cancellation has passed locally.
2. Review [draft PR #1](https://github.com/theRealest555/SIHATECH/pull/1) and confirm its latest GitHub checks before release. Main now requires backend PHP 8.3/8.5, frontend, browser and MySQL checks from GitHub Actions with strict up-to-date checks, including administrators. Frontend lint and browser regressions are blocking workflow steps. All five browser workflows are automated; rehearse them on deployed domains. React interaction tests mock network responses, while Chromium tests use the actual Laravel API and a fresh SQLite database/local SMTP sink.
3. Booking, schedule closure and leave races passed locally and on GitHub MySQL; expand concurrency verification to competing completion/no-show decisions, account edits and payment events in staging. Schedule and profile edits protect pending/confirmed appointments. No-show decisions require a human doctor/admin action on a confirmed visit; the legacy job is read-only. Audit historical automatically marked records separately.
4. Exercise live provider test-mode integrations, payment authentication challenges, webhook delivery before local mapping, and out-of-order subscription events. OAuth recovery messages, identity uniqueness and provider configuration checks are implemented; rehearse the uniqueness migration, finish authenticated account linking, then verify real successful/cancelled OAuth journeys on deployed cookie domains.
5. Rehearse the implemented profile/schedule stale-edit protection under concurrent writes on the production database. Rehearse session revocation, password recovery and concurrent token issuance on deployed cookie domains. Email changes require fresh verification; password changes, resets, suspension and admin demotion now revoke old access; profile photo replacement protects the previous file.
6. Provision supervised queue workers and the registered heartbeat scheduler, enable required production heartbeats, and exercise readiness failure/recovery in staging. Migrate and rehearse reminders with staging mail before enabling their flag. Use the read-only attendance audit for staff review and subscription-period audit for provider reconciliation. Implement required retention jobs deliberately. Configure external alerts, structured error reporting, encrypted backups and restore drills; see [operations-verification.md](operations-verification.md).
7. Reconcile legacy appointment ownership and unpaid subscription states, migrate private documents, verify admin provisioning, and remove/rotate any credentials previously committed to Git history. Complete access-control and data-retention reviews using the application's actual hosting and operating requirements.

## CI references

Action configuration follows the maintainers' documentation: [checkout](https://github.com/actions/checkout), [setup-node](https://github.com/actions/setup-node), and [setup-php](https://github.com/shivammathur/setup-php).
