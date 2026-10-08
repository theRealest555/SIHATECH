# Profile security and workspace dashboards — 6 October 2026

All verification used generated accounts, an isolated SQLite database or mocked frontend network responses. No production record, email address, password or provider service was changed.

| Boundary | Result | Evidence |
| --- | --- | --- |
| Patient / doctor profile → changed email | API tests passed | Correct password required, invalid password leaves profile untouched, changed email loses verification, notification targets new address |
| Profile without email change | API and Redux tests passed | Verification retained, no notification, preferred doctor can be cleared and omitted patient fields retained |
| Old signed link / stale request identity | API tests passed | Old address cannot verify replacement, even if request authentication loaded the old identity |
| New signed link → restored access | API tests passed | Replacement address can be verified; resend available while unverified |
| Doctor email becomes unverified | API tests passed | Public profile, availability and slots denied; public active scope excludes account |
| Profile response → authenticated client state | Redux tests passed | Verified access removed immediately, new address stored, submitted password absent from Redux state |
| Real doctor cookie session → dashboard → database | Browser passed | Two generated future appointments shown: pending and confirmed; credential counts 1 pending, 0 approved, 1 rejected match stored documents |
| Real patient cookie session → dashboard → database | Browser passed | Same two stored visits shown with doctor name and speciality; cancelled appointment omitted |
| Dashboard load, retry and stale response | React tests passed | Truthful empty state, retry after error, credential failure preserves appointments, old responses ignored |

Checks: 184 backend tests / 941 assertions, 52 frontend tests and frontend production build passed. Changed frontend files lint passes; overall frontend lint still has 72 errors and 4 warnings. Git whitespace checks passed.

Screenshots doctor-dashboard.png and patient-dashboard.png are saved in the parent workspace. The isolated dashboard fixtures use year 2100 to remain future appointments. Preview servers are stopped after verification.

Limitations: real email delivery and the deployed cookie/domain flow still need staging verification. API tests use SQLite and do not demonstrate MySQL concurrency; the stale-identity test verifies the transaction’s second hash check. Password changes still lack a complete session/token revocation policy, photo replacement needs failure handling, and the separate doctor statistics page still uses sample data. Provider accounts must sign out and use the existing password-reset flow before changing email. The entire production release remains subject to the checklist in production-fixes.md.
