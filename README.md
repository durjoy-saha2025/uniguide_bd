# UniGuide BD

Bangladesh university discovery frontend and a PHP/MySQL backend foundation.

## Current pages

- `public/index.html` — homepage
- `public/universities.html` — sample directory with filters and compare selection
- `public/university.html?id=nsu` — reusable sample university profile
- `public/compare.html` — side-by-side comparison page

The current frontend data is illustrative and stored in `public/assets/js/data.js`.
It is not verified university information and does not yet load from the API.

## Backend setup (XAMPP)

Requirements: PHP 8.0+, MySQL 8.0+ (or a compatible XAMPP MariaDB release),
and the PHP `pdo_mysql` extension.

1. Configure Apache's document root to point to this repository's `public` folder.
2. Create the `uniguide_bd` database.
3. Copy `.env.example` to `.env` in the project root and set the local database
   credentials. The `.env` file stays outside `public` and is ignored by Git.
   For a local XAMPP setup, `bin/provision-local-db-user.php` can create a
   least-privilege app account when `.env` currently contains an administrative
   DB connection; it replaces `.env` with the generated app credentials.
4. Import `database/schema.sql` into the database using phpMyAdmin or the MySQL client.
5. To create the first administrator, set `UNIGUIDE_ADMIN_PASSWORD` to a
   one-time password of at least 16 characters, then run
   `php bin/create-super-admin.php "Admin Name" admin@example.com` from the
   project root. The command refuses to create another active super admin.
6. Open `http://localhost/api/universities.php` to test the public listing endpoint.

The schema creates empty tables; it deliberately does not seed fictional records.
The local XAMPP database has the schema imported and currently contains no
university records, so the public API returns an empty list. Do not insert
unverified education data just to make the directory non-empty.

## Public university API

- `GET /api/universities.php?page=1&per_page=20`
- `GET /api/universities.php?q=engineering&city=Dhaka`
- `GET /api/universities.php?slug=example-university`

Only records with `status = published` and `verification_status = verified`
are returned. The endpoint supports search, city filtering, pagination, and a
single verified profile lookup.
Responses use either `{ "data": ..., "meta": ... }` or
`{ "error": { "code": ..., "message": ... } }`.

No sample fees, rankings, scholarships, or admission requirements are supplied
as facts. Verification and source fields must be populated from reliable sources.

## Authentication and student endpoints

- `GET /api/csrf.php` — issue/read the session CSRF token
- `POST /api/auth.php?action=register` — student registration
- `POST /api/auth.php?action=login` — student or admin login
- `GET /api/auth.php?action=me` — current account
- `POST /api/auth.php?action=logout` — end current session
- `POST /api/auth.php?action=password` — change current password
- `DELETE /api/auth.php?action=delete-account` — permanently delete own student account
- `GET|PUT|DELETE /api/student-profile.php` — current student's profile

Mutating requests send JSON and an `X-CSRF-Token` header. The profile endpoint
gets the account id from the session, so clients cannot choose another user's id.
Student passwords require at least 12 characters; initial super-admin passwords
require at least 16 characters.

## Admin university endpoint

- `GET /api/admin/universities.php?page=1&per_page=20&status=draft`
- `GET /api/admin/universities.php?id=123`
- `POST /api/admin/universities.php`
- `PUT /api/admin/universities.php?id=123`
- `DELETE /api/admin/universities.php?id=123`

The endpoint requires an active `admin` or `super_admin` session. Mutations
require CSRF protection and are recorded in `admin_activity_logs`. Publishing
is blocked unless the record has a verified source. Public university responses
include the verified source's title, publisher, and URL.

## Admin source endpoint

- `GET /api/admin/sources.php?page=1&per_page=20&verification_status=pending`
- `GET /api/admin/sources.php?id=123`
- `POST|PUT|DELETE /api/admin/sources.php` (`id` query parameter for updates/deletes)

Source management requires an active admin session and CSRF protection for
mutations. Sources referenced by university facts cannot be deleted. Apply
`database/migrations/001_university_sources.sql` to an existing database; new
installations receive the source relationship directly from `schema.sql`.

## Current backend scope

Implemented: environment-based PDO, the MySQL schema, public verified-university
read/search, student registration and session auth, private profile CRUD, and
role-protected university and source CRUD with activity logging. A university
cannot be marked verified or shown publicly without a verified source record.

Not implemented yet: login throttling, password-reset delivery, CRUD for
programs/fees/admissions/scholarships/rankings/news/guides, saved and
comparison API storage, eligibility, recommendations, budget calculations,
admin/student dashboard integration, and frontend API integration.

See [ARCHITECTURE.md](ARCHITECTURE.md) and [DATABASE.md](DATABASE.md) for the
current design and data model. See [TASKS.md](TASKS.md) for unfinished backend
milestones and [CHANGELOG.md](CHANGELOG.md) for the latest changes.