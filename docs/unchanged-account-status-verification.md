# Unchanged administrator status decisions

Both administrator status endpoints now return `changed: false` when the locked target already has the requested status or approval. They still check current actor authorization and the expected status revision first. A stale revision remains HTTP 409, even if the requested result now matches the account. Actual transitions return `changed: true` and retain their existing audited access-revocation transaction.

An unchanged decision does not save the target, rotate its remember token, increment its authentication version, delete API tokens or write an access-change audit entry. It returns the current status revision and the existing no-store response headers. Reason validation still applies to requests for Inactive, Pending or Not approved; an unchanged decision does not create an audit entry solely to store that reason.

Only canonical approval values 0/1 and their database string equivalents qualify as unchanged. A historical value such as pending is not silently interpreted as zero. Changing such a record to Not approved remains an actual transition with revocation and an audit entry.

The user-management screen recognizes `changed: false` and reports that the status is already current. It refreshes the listing without claiming a new activation or revocation.

## Verification

- Eight new backend cases cover all three account statuses, both approval states, repeated unchanged decisions, credential/recovery-token/timestamp preservation, stale replay after a real change, and unknown legacy approval values.
- Existing unchanged-active/approved audit cases now expect no new history entry. Actual transition, reason, authorization, rollback and stale-decision suites remain covered.
- One new frontend case verifies the unchanged-result message without a new revocation claim.
- Focused backend suite: **45 tests, 405 assertions passed**. Full frontend suite: **136 tests passed**; production build passed.
- Full backend suite: **394 tests, 2402 assertions passed** on PHP 8.5.1 / SQLite. Frontend lint, changed PHP formatting and Git whitespace checks passed.
- Tests use disposable backend databases and mocked frontend API responses. No preview or external access was changed.

## Rollout and next step

No migration or new request field is required. The `changed` response field is additive; deploy the frontend update to display the accurate unchanged-result message. Existing access-change audit entries remain untouched. An unchanged status request is not a forced credential-revocation operation; use the dedicated credential-change flows when revocation is required.

Next step: verify user management against the real local API in the browser, including reason entry, stale conflicts, reactivation and the resulting audit details. Rehearse simultaneous decisions on the production database engine before release.
