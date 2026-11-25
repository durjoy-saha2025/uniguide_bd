# Project Context

UniGuide BD helps students in Bangladesh explore university options and make
informed comparisons. It is intended to show sources and verification status;
the interface must not invent fees, rankings, eligibility rules, or scholarships.

## Current implementation

- Four responsive public HTML pages in `public/`.
- Vanilla JavaScript and CSS; no frontend framework.
- PHP 8.2 and PDO API code under `app/` and `public/api/`.
- Admin source CRUD and source-backed university verification.
- MySQL-compatible schema imported into the local XAMPP `uniguide_bd` database.
- The database currently has no university content; the public list API correctly
  returns an empty result until verified information is added.
- Demo homepage listings remain illustrative and are not fed into the database.

## Important constraints

- `.env` contains local-only database settings and is excluded from Git.
- Do not commit credentials or add unverified university data.
- Student profile data is private and must be scoped to the authenticated user.
- Production deployment needs HTTPS, a reviewed DB account, backups, migrations,
  email delivery, rate limiting, and a security review.
