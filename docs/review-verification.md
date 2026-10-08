# Review submission and moderation verification

Verified locally on 7 October 2026. Changes remain local on codex/production-fixes. No production deployment or customer review changes occurred.

Patients can open a review form from a completed past appointment. The server verifies the patient-profile ownership and completed/past status under appointment/doctor locks, derives doctor and patient user IDs from that appointment, and accepts a rating of 1–5 plus optional feedback of at most 2000 characters. Client-supplied identities or moderation status are ignored. One review per appointment is enforced by a database unique index. Submitted reviews remain pending and do not affect public doctor ratings.

The administrator queue has pending/approved/rejected filters, pagination, explicit decision confirmation, required rejection reasons and conflict handling. Decisions compare the loaded status under a lock. Approval requires a coherent completed appointment, matching doctor/patient ownership and a valid rating; legacy reviews without reliable context cannot be approved. Rejection remains available for reconciliation. Same-status decisions are idempotent.

Moderation status, saved reason, moderator identity/time, doctor average/count and audit entry commit together. Rejecting an approved review removes it from the rating aggregate. Audit failure rolls back both moderation and rating updates. The incorrect review appointment fillable field was corrected to rendezvous_id, matching the schema. The old insecure moderation implementation was removed from the routed administrator controller.

Patient and administrator responses expose only the needed names and review/appointment identifiers, not full user/account/contact objects. Comment and reason text is rendered as text. Patients see their own review status and rejection reason from the appointment page. UI responses from aborted filters or a different visit cannot overwrite the current screen. Feedback is available to the patient and moderation queue; a public written-review feed was not added.

## Evidence

- Eleven backend tests cover owner/profile ID handling, completed/past eligibility, duplicate submissions, validation, status conflicts, reasons/idempotency, invalid historical context, atomic audit rollback, owner-scoped lists, safe response fields, aggregate averaging, database uniqueness and duplicate-migration preflight.
- Eight frontend tests cover submission/pending state, saved rejection reason, failures, explicit decisions, rejection text, safe HTML-like text, conflicts without automatic resubmission, filter staleness and late submission responses after visit navigation.
- Existing admin tests now use the expected-status moderation contract. Review factories create coherent completed past appointments.
- Full backend suite: 236 tests / 1245 assertions. Frontend: 85 tests. Production build and changed frontend lint passed. Whole frontend lint remains at 47 errors / 1 warning elsewhere.
- Real browser → cookie API → isolated SQLite: patient reviewed appointment 5 with 4/5 feedback; administrator approved it and the doctor cache became average 4, count 1. Rejecting it saved the supplied reason and returned the doctor cache to average 0, count 0. Both audit entries were verified directly in the isolated database.
- Screenshots: ../../patient-review-pending.png, ../../admin-review-approved.png and ../../admin-review-rejected.png. The rejected preview record remains in the isolated database.
- Applied the integrity migration only to booking-preview.sqlite. Original backend .env unchanged; the preview doctor's existing unverified credential status was preserved.

## Rollout and remaining work

Back up and rehearse the unique-review migration on the production engine. It refuses historical duplicate non-null appointment links; reconcile those records deliberately before deployment. Null appointment links are retained for historical reconciliation and cannot pass approval validation. Existing incorrect appointment ownership and previously approved reviews/ratings require a separate trusted-history audit; no automated historical reassignment was performed.

Concurrent submission/moderation and row locking still need production-engine staging verification. Moderation policy and operator workflow need review using actual product requirements. Outcome notifications, public written-review display, review appeals and retention policy are separate product work. The new migration must run before deploying code that uses moderation_reason; migration rollback drops that field and therefore discards stored rejection reasons.
