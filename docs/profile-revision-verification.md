# Profile and schedule stale-edit verification

Patient profiles, doctor profiles, doctor onboarding and weekly schedules now reject a save made from outdated editable data with HTTP 409. A newer save remains intact. Missing revision tokens return validation errors.

## Contract and behavior

- Private profile reads return `profile_revision`; writes require `expected_profile_revision`. Private availability reads return `schedule_revision`; schedule writes require `expected_schedule_revision`.
- Revisions are keyed content fingerprints tied to the account/profile IDs, not authentication credentials or monotonic counters. Profile fingerprints include editable contact fields and role-specific fields, including doctor hours. Schedule fingerprints include doctor hours. Returning to identical content returns the same fingerprint; rotating `APP_KEY` invalidates open drafts.
- Checks execute inside the existing row-lock transactions before changes or email notifications. Successful responses use the saved fields and revision from the same transaction snapshot. Both profile and availability endpoints protect schedule changes against stale data and retain existing future-booking checks.
- Photos, verification timestamps and security flags are excluded from contact fingerprints. Existing authorization, session-revocation and email-change checks still apply independently.
- Profile forms keep the original draft and revision during background refreshes. Conflicts offer an explicit reload that discards the draft; there is no automatic merge. Ordinary doctor profile saves no longer include hours that the form cannot edit. Onboarding loads the current profile before allowing submission.
- Fixed an unintended form submit when the Edit Profile button became Save Changes during a click. Added label associations and normalized patient birth dates for native date inputs.

## Local verification

- Backend: 254 tests / 1415 assertions passed using PHP 8.5.1 and SQLite. Six new regression tests cover stale patient saves, cross-endpoint schedule conflicts, account-bound tokens, required tokens, unrelated updates and onboarding conflicts.
- Frontend: all 96 tests across 16 files passed. New patient/doctor interaction tests verify preserved drafts, original revisions, explicit reload and opening the editor without submission. Availability coverage verifies the schedule revision sent to the service.
- Full frontend lint passed with zero errors/warnings; production build, PHP formatting and Git whitespace checks passed.
- Real localhost browser with an isolated preview patient: a separate authenticated HTTP session saved “Preview newer name”; the browser's “Preview local draft” save received the conflict message and retained its draft. Explicit reload displayed “Preview newer name”. Only fixture data changed. Screenshots are saved in the workspace parent as `profile-stale-edit-conflict.png` and `profile-stale-edit-reloaded.png`; the latter shows the resulting saved profile.

## Rollout and limits

No database migration is required for this batch. Deploy the frontend and API together because writes now require revision tokens; other clients must implement the same read/edit/save contract. Existing open drafts need a fresh read after rollout.

SQLite and sequential two-session browser checks do not prove concurrent row-lock behavior on MySQL. Rehearse simultaneous profile/schedule writes and deployed cookie/session flows on the production database engine. Doctor and schedule conflicts were checked through backend tests and frontend interactions; the real two-session browser proof covered the patient profile.
