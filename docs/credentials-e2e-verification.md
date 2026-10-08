# Credential workflow and privacy fixes

The Chromium suite now includes real doctor upload, byte-for-byte owner/admin downloads, approval, verification, rejection with a required reason, automatic verification revocation and rejected-document deletion. Another doctor receives 404 for the private resource; anonymous access receives 401. Approved credentials cannot be deleted through the UI.

Each run now has a separate private storage directory beside its fresh SQLite database. The seed guard checks both paths before migrations. `DOCUMENTS_ROOT` optionally configures the private disk; its default remains `storage/app/private`. No preview credential files are used.

The review identified two application gaps. `Document` now hides `file_path` in all serialized responses, including legacy administrator endpoints. Administrator downloads now send `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`, matching owner downloads. A backend regression covers those endpoints and headers.

All four Chromium workflows passed locally on 2026-10-08; frontend lint passed. The administrator test filters access-change audits, making its record count independent of new credential review history. Cookie-authenticated direct test requests include the frontend Origin so Sanctum recognizes the session.

This uses synthetic PNG credentials, local cookie domains and isolated storage. It does not constitute real professional credential verification or deployed-domain proof. Run `npm run test:e2e` from `frontend`; setup is described in [administrator test notes](admin-access-e2e-verification.md).
