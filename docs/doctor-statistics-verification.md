# Doctor statistics verification

Verified locally on 7 October 2026. Changes remain local on codex/production-fixes; no production data or deployment was changed.

The statistics page now loads authenticated Laravel aggregates rather than sample counts. Week, month, year and inclusive custom ranges apply to appointments, patient counts, approved ratings and CSV exports. Custom reports are bounded to 366 days. Counts use stored clinic wall time and display the configured timezone.

Unique booked patients include all appointment statuses; patients seen require at least one completed consultation. Repeat patients require multiple completed consultations within the selected range. Daily counts include zero days. Ratings include only approved reviews submitted in the range for the signed-in doctor; no reviews produces an unavailable average rather than a fabricated rating.

Subscription payments were incorrectly reported as earnings. Consultation revenue is now explicitly unavailable because the application has no consultation payment ledger. Invented response times and availability scores were removed. Unsupported revenue/PDF exports return a controlled validation response.

CSV summaries contain aggregate counts, dates and timezone, with no patient names, contact details or individual appointment records. CSV status keys now match the database. The explicit CSV escape argument avoids PHP 8.5 deprecations. The UI exports the displayed server range, supports retry/empty/error states, and ignores responses from aborted filter requests. Statistics is accessible through doctor navigation.

## Evidence

- Seven database-backed backend tests replace three service-mock tests: ownership isolation, date boundaries, week/custom filters, booked versus seen/repeat patients, approved review scope, invalid ranges, aggregate CSV and patient access denial.
- Four frontend interaction tests cover empty reports, retries, filters, stale responses and export errors.
- Browser against the isolated SQLite preview: October report showed 4 appointments, 2 completed, 1 cancelled, 1 no-show, 1 unique patient seen and 1 repeat patient. Selecting this week showed only the cancelled October 8 appointment. Screenshot: ../../doctor-statistics.png.
- Independent authenticated HTTP cookie session verified the real October API and streamed CSV against those known records. Saved aggregate artifact: ../../doctor-statistics-preview.csv. Browser download completion was not independently confirmed.
- Full backend suite: 212 tests / 1099 assertions. Frontend suite: 67 tests. Production build and changed frontend file lint passed. Full frontend lint still has 66 errors and 4 warnings elsewhere.

## Release limits

Verify aggregates against a staging copy of the production database and its actual timezone. Existing incorrect appointment ownership must be reconciled separately; these aggregates do not repair historical records. Review submission/moderation remains separate release work. CSV consumers must use the new explicit status/metric contract. No consultation billing or accounting system has been added.
