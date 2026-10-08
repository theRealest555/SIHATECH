# Admin verification — 6 October 2026

Verified locally using the isolated booking-preview.sqlite database, approved admin@preview.test account and a generated PDF containing test text. No real practitioner credential was processed.

| Boundary | Result | Evidence |
| --- | --- | --- |
| Approved admin login → cookie session → dashboard | Passed | Counts were 3 users, 1 doctor, 1 patient, 1 appointment and 1 pending doctor |
| Queue → doctor details → private download | Passed | Selected doctor 1, credential document 2; downloaded bytes matched generated PDF SHA-256 F7D302EA5C7B3D72FD67BF29C3A31487788A1245C4B1D29FED8507A037DD9230 |
| Credential approval → persisted document | Passed | Document 2 became approved; approved_document audit entry attributed to admin user 3 |
| Approved required licence → doctor verification | Passed | Doctor 1 became verified; verified_doctor audit entry recorded |
| Required licence rejection → automatic revocation | Passed | Document 2 rejected with supplied reason; doctor 1 became unverified; rejected_document and revoked_doctor_verification entries recorded |
| Revocation → public eligibility | Passed | GET /api/public/doctors/1 returned 404; existing cancelled appointment 1 remained stored |
| Conflict, missing-file and access control | Passed in API tests | Stale status decision blocked; other document type insufficient; absent files blocked; patient/unapproved admin denied access |
| UI decisions and retries | Passed in React tests | Correct document IDs, explicit confirmations, required reasons, conflict refresh, private downloads and supported dashboard links |

Checks: 175 backend tests / 874 assertions, 43 frontend tests, production build, changed admin files lint and Git whitespace checks passed. Overall frontend lint remains 90 errors / 4 warnings.

Screenshots admin-doctor-verified.png and admin-verification.png are in the parent workspace. Preview servers were stopped after verification.

Production requirements: agree and configure required credential types, audit existing verified doctors and private file availability, complete document migration/backups, review credential retention and access policy, and exercise concurrent review/booking on MySQL. The new default requires a medical licence; it does not validate licence authenticity or expiry automatically. Expiry tracking, doctor notifications, admin audit-log UI and automated staging browser coverage remain release work. Old compatibility review calls may omit expected_status; the new review screen always supplies it.
