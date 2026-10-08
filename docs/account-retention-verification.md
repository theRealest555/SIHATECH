# Account history preservation and user management

The administrative permanent-deletion endpoint now returns HTTP 409 with `account_deletion_unavailable` for existing accounts. It makes no account, token, document, appointment, payment or audit changes. Unknown IDs still return 404; existing authentication and administrator authorization remain enforced. Database cascade definitions remain unchanged, so direct database deletion is still dangerous and is not an approved retention workflow.

Administrators can deactivate accounts through the existing status endpoint. This preserves history while incrementing the authentication version, revoking API tokens and invalidating existing browser access. Reactivation does not restore revoked credentials. Administrators cannot deactivate their own account or remove their own administrative approval; another administrator must perform those actions. Deactivation does not cancel bookings or external subscriptions, and the UI explicitly explains the separate follow-up.

The user list now consumes Laravel's actual paginator fields and `prenom`/`nom` names, displays correct role labels, requests search only on submission, supports role/status filters, resets the page on filtering and ignores stale fetch responses. It uses explicit status confirmation and reports failed updates without claiming success. Removed creation/edit links pointed to routes that were not implemented; the deletion button was removed. Administrative creation and broader account editing still require a separate UI workflow.

The listing validates filter types/lengths, uses stable ID ordering and projects only the fields needed by the table. Its response uses no-store headers.

## Verification

- Four new backend tests cover retained account/appointment/document/payment history, deactivation with retained appointments and revoked tokens, administrator self-lockout prevention, stable pagination and filter validation.
- Existing deletion expectations now assert refusal. The audit failure test verifies deactivation rolls back status, authentication version and token revocation when audit persistence fails. Legacy audit-actor deletion remains a separate schema-level test.
- Four new frontend tests cover actual fields/pagination, submitted search, explicit status confirmation and failed-update recovery.
- Full backend suite: **320 tests, 1813 assertions passed** on PHP 8.5.1 / SQLite.
- Full frontend suite: **130 tests passed**; production build, lint, changed PHP formatting and Git whitespace checks passed.
- Real localhost browser inspected the administrator user list and status confirmation, then cancelled. No account was deleted, suspended or reactivated. Screenshot: workspace parent `admin-user-retention.png`.

## Rollout and remaining work

No migration is required. Deploy API and frontend together; older deletion clients will receive the explicit 409 response. This closes the unsafe public administrative deletion path, but does not implement a complete retention, anonymization or erasure workflow. Design that workflow with explicit handling of clinical/billing records, credential files, audit history, provider resources and backups before reintroducing deletion.

Self-lockout protection does not guarantee that simultaneous administrators cannot disable all other administrators. Concurrent status decisions still use row locks with the latest write winning. Rehearse these flows on the production database engine and implement any additional administrator quorum or stale-decision policy required by the product. Confirm operational booking and provider-billing follow-up when suspending an account.
