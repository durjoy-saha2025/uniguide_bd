# Architecture

## Project layout

```text
app/
  Controllers/       Request validation and API orchestration
  Core/              PDO, CSRF, session, response, and login throttling
  Middleware/        Authentication and role checks
  Repositories/      Prepared SQL queries and persistence
  Support/           Catalog resource rules and metadata
  bootstrap.php      Autoloading, configuration, and safe error handling
bin/                 Local DB provisioning and first-admin CLI commands
database/
  schema.sql         Empty MySQL/MariaDB schema
  migrations/        Changes for databases created from earlier schemas
public/
  api/               PHP API entry points
  assets/css/        Design tokens and shared responsive styles
  assets/js/         Shared API client and page behavior
  *.html             Public, student, and admin pages
```

`public/` is the web root. Database credentials and application internals stay
outside it. HTML pages use shared CSS/JavaScript and call PHP endpoints through
`public/assets/js/api.js`; pages do not issue SQL or embed database credentials.

## Request and trust boundaries

- Public university and catalog endpoints return published records only. Facts
  that need evidence are hidden unless their record and linked source are
  verified; news entries require a verified source.
- Admin routes require an active admin/super-admin session and CSRF validation
  for mutations. Repositories use prepared statements; mutations are logged.
- Student profile, saved items, comparisons, and planning endpoints derive the
  user identity from the authenticated session. Clients cannot select another
  account's records.
- Eligibility returns cautious screening outcomes from available verified
  requirements. Recommendations use preferences and verified program facts.
  Published fee components are not guaranteed to represent the total cost.
- APIs return `{ "data": ..., "meta": ... }` on success and a structured error
  on failure. Internal exceptions go to the server log.

## Schema and migrations

`database/schema.sql` creates an empty database. It intentionally contains no
fabricated universities, fees, rankings, scholarships, or admission criteria.
Existing databases may need migrations `001_university_sources.sql`,
`002_program_sources.sql`, and `003_auth_login_attempts.sql` applied once.

## Current limitations

Password reset delivery has not been implemented. Directory filters do not yet
cover all planned budget/scholarship/ranking/campus dimensions. Admin student
management, settings, and activity-log browsing are not present. Facilities,
map data, and broad end-to-end/security testing also remain to be completed.
