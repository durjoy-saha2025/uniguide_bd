# UniGuide BD

UniGuide BD helps students in Bangladesh explore source-verified university
information, compare options, and plan next steps. It uses semantic HTML,
CSS, vanilla JavaScript, and PHP/MySQL APIs; no frontend framework is required.

## What is implemented

- Public pages: homepage, university directory, university profile, comparison,
  student guides, and verified university updates.
- Student registration and sign-in, a private academic profile, saved items,
  comparison list, program suggestions, eligibility screening, password change,
  and account deletion.
- A protected admin workspace for universities, sources, programs, fees,
  admission requirements, scholarships, rankings, news, and guides. It includes
  search, filters, sorting, pagination, view/edit/delete, validation feedback,
  and source-aware publishing rules.
- PHP APIs with session authentication, CSRF checks, role checks, audit logging,
  prepared SQL, and login-attempt throttling.

The database has no invented university records. Empty directory, guide, and
update states are expected until admins add real content and verify its sources.
Fee entries are not represented as a complete cost estimate. Eligibility
screening is informational, not an admission decision.

## Run locally with XAMPP

Requirements: PHP 8.0+ with `pdo_mysql`, MySQL 8+ or compatible MariaDB, and
Apache configured to serve the repository's `public` folder.

1. Create a database named `uniguide_bd`.
2. Copy `.env.example` to `.env` at the repository root and set your local DB
   connection values. Do not commit `.env` or use development credentials in
   production.
3. Import `database/schema.sql` in phpMyAdmin for a new database. For an older
   installation, apply only the migrations it does not yet have:
   `001_university_sources.sql`, `002_program_sources.sql`, and
   `003_auth_login_attempts.sql`.
4. Configure Apache's document root as this repository's `public` directory.
5. In PowerShell, create the first admin from the repository root:
   `$env:UNIGUIDE_ADMIN_PASSWORD = 'a-one-time-password-of-16-or-more'`
   then run `C:\xampp\php\php.exe bin\create-super-admin.php "Admin Name" admin@example.com`
   and clear it with `Remove-Item Env:\UNIGUIDE_ADMIN_PASSWORD`. The script
   refuses to create a second active super admin.
6. Visit `http://localhost/` and use the admin workspace at `admin.html`.

The local developer database may instead use XAMPP's `htdocs` layout; in that
case, place/serve this project so its `public` directory is the web root. The
`bin/provision-local-db-user.php` helper can create a least-privilege app user
when configured with an administrative local DB account.

## Main API routes

- Public: `GET /api/universities.php` and
  `GET /api/catalog.php?resource=programs|program-fees|admission-requirements|scholarships|rankings|news|guides`.
- Accounts: `/api/auth.php?action=register|login|me|logout|password|delete-account`,
  plus `/api/csrf.php` and `/api/student-profile.php`.
- Student-only: `/api/student/discovery.php` and `/api/student/planning.php`.
- Admin-only: `/api/admin/universities.php`, `/api/admin/sources.php`, and
  `/api/admin/catalog.php?resource=...`.

Mutating requests require a session CSRF token. Admin writes are audited.
Publishing catalog facts requires verified evidence; public results include
source references where applicable.

## Tests

From the project root, run the focused repository tests with:

```powershell
C:\xampp\php\php.exe tests\UniversityRepositoryTest.php
C:\xampp\php\php.exe tests\UniversityAdminRepositoryTest.php
C:\xampp\php\php.exe tests\CatalogRepositoryTest.php
C:\xampp\php\php.exe tests\SourceAdminRepositoryTest.php
```

PHP syntax can be checked with `C:\xampp\php\php.exe -l <file>`. Browser testing
is also needed for UI changes; Node.js is not required by the frontend.

## Known gaps

- No verified university catalog data has been entered yet.
- Password reset tokens exist in the schema, but reset endpoints and email
  delivery are not configured.
- The directory does not yet expose every planned filter (budget, scholarship,
  ranking edition, and campus type).
- Admin student-management, admin settings/profile, and review-log screens are
  not part of the current UI.
- Facilities and map details are not shown unless the schema has verified data
  for them; broader profile sections still need completion.
- Complete responsive/device and authenticated student/admin end-to-end testing,
  production hardening, backups, and deployment configuration remain.

See [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md),
[PROJECT_CONTEXT.md](PROJECT_CONTEXT.md), [TASKS.md](TASKS.md), and
[CHANGELOG.md](CHANGELOG.md) for implementation details and next steps.
