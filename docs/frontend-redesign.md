# Frontend visual refactor

The interface uses a shared forest-green, sage and warm-ivory palette, restrained borders, system-font controls and editorial serif headings on public/authentication pages. No external fonts, new UI dependencies, invented analytics or placeholder medical records are introduced.

## Changed surfaces

- Desktop role-based sidebar and account header; collapsible mobile navigation with Escape dismissal and focus restoration.
- Administrator dashboard with live totals, registrations, moderation queues and management links.
- Patient/doctor dashboards with live appointment counts, upcoming visits, credential review status and account tools.
- Public home page and shared account/recovery layout.
- Shared tables, filters, forms, buttons, profile cards and responsive spacing.
- Theme stylesheet loads after Bootstrap so its colours and controls apply consistently.

Existing API contracts, permissions, appointment decisions and billing flows are preserved. Shared styling extends to the remaining operational pages; those pages retain their existing feature-specific layouts.

## Verification

- 140 frontend tests in 22 files passed, including role-specific navigation and mobile menu focus handling.
- ESLint passed with zero warnings.
- Production build passed (519 modules).
- Five Chromium workflows passed against the real local API: administrator access, appointments, booking conflicts, recovery and private credentials.
- Desktop 1440px and mobile 390px viewport checks showed no page-level horizontal overflow. Wide operational tables scroll within their own wrappers.
- Reviewed home and administrator dashboard visually; checked account-recovery layout at desktop size.

Live staging provider and deployment verification remains part of docs/release-verification.md. This visual refactor does not establish production deployment readiness by itself.