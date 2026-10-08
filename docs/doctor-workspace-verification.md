# Doctor workspace verification — 6 October 2026

Verified on localhost with an isolated SQLite preview database, generated test accounts and a generated PDF containing only test text. No production data or external services were used.

| Boundary | Result | Evidence |
| --- | --- | --- |
| Cookie sign-in → own availability API → form | Passed | Preview doctor saw their stored schedule and enabled editing after verification |
| Schedule form → validation → persisted schedule | Passed | Monday changed to 09:00-12:00 and 14:00-17:00; read-only database query matched |
| Leave form → inclusive date storage → refreshed list | Passed | Preview conference saved for 10–11 November 2026, dates confirmed in database |
| Multipart upload → private storage → pending review UI | Passed | Generated preview-credential.pdf persisted with pending status and private disk path |
| Private API → authenticated blob → browser download | Passed | Downloads/preview-credential.pdf was 619 bytes, SHA-256 F7D302EA5C7B3D72FD67BF29C3A31487788A1245C4B1D29FED8507A037DD9230 matched the original |
| Full slot and grid conflicts, profile bypass, invalid/overlapping ranges | Passed | ScheduleIntegrityTest protects duration, grid and omitted profile schedule |
| Upload types/size and access ownership | Passed | Executable/oversized uploads rejected; unauthenticated/other-doctor downloads rejected in API tests |
| UI failed requests and deletion confirmation | Passed | Regression tests preserve draft/file selections and never show false success |

The in-app browser download event observer timed out even though the file was downloaded. The saved file and matching checksum independently confirmed delivery. A controller regression found during live verification was fixed and covered by an exact returned-schedule test.

Screenshots doctor-availability.png and doctor-documents.png are saved in the parent task workspace. Local preview servers were stopped after verification.

Limits: SQLite does not prove concurrent MySQL locking. Production document migration, object-storage backup/retention, malware scanning where required, admin workflow screens and staging cookie settings still need validation. Existing legacy malformed schedules need review before editing. Live email, OAuth and Stripe flows remain outside this verification.
