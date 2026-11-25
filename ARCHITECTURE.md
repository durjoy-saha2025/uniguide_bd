# Architecture

## Current layout

```text
config/                 Environment-backed configuration
app/
  Core/                 PDO connection and shared API response behavior
  Controllers/          Request validation and endpoint orchestration
  Repositories/         Prepared SQL queries
  Middleware/           Session-backed authentication and role checks
  bootstrap.php         Autoloading, local environment loading, safe errors
bin/                    CLI-only database-user and super-admin setup
database/
  schema.sql            Initial MySQL 8 schema; no seed data
public/
  api/                  PHP API entry points
  assets/               Existing static frontend assets
  *.html                Existing public pages
.env.example            Local configuration template
```

The public directory is the web root. Secrets and application internals remain
outside it. PHP endpoints use the application bootstrap and are separated from
database query code.

## Request flow

`public/api/universities.php` -> `UniversityController` -> `UniversityRepository`
-> shared `Database` PDO connection -> MySQL.

The public university endpoint allows read-only GET requests. It returns only
published and verified records, validates search and pagination parameters,
uses bound PDO parameters, and returns a consistent JSON envelope. Registration,
session authentication, CSRF, a private student-profile endpoint, and
role-protected university and source CRUD are also implemented. A university
must cite a verified source before publication, and public responses include
the citation. Internal exceptions are written to the PHP error log; API clients
receive a generic server error.

## Planned boundaries

- Password-reset delivery, login rate limiting, and endpoint-level authorization reviews.
- Services for eligibility, recommendations, fee calculations, and verification workflows.
- Repositories for each data module; controllers should not contain SQL.
- Keep personal student data restricted to owner-authorized routes.
- Admin university and source writes create audit records; other modules still need CRUD and review workflows.
- The static frontend remains demo-backed until API integration is implemented and tested.

## Current limitations

Only university CRUD is available to administrators. Other admin modules,
student saved/comparison APIs, eligibility, recommendation, and budget services
are not implemented. There is no real university seed data, and the frontend
still uses illustrative browser-side data.
