# Project Context

UniGuide BD is a Bangladesh-focused university discovery and planning project.
The key product rule is evidence-first publishing: do not make up university
facts, fees, rankings, scholarships, or eligibility criteria.

## Current implementation

- Nine separate public/student/admin HTML pages, built with accessible HTML,
  shared CSS tokens, and vanilla JavaScript.
- PHP/PDO APIs using the local XAMPP MariaDB database.
- Public university search, profiles, comparisons, guides, and verified updates.
- Student registration, login, owner-only profile and shortlist features,
  suggestions, and eligibility screening.
- Protected admin CRUD for universities, sources, and seven catalog modules.
- CSRF protection, role checks, audit logs, and login-attempt throttling.
- The database is deliberately empty of university content; empty states are
  expected until administrators add and verify real records.

## Important constraints

- Root `.env` is local-only, ignored by Git, and must never be published.
- Admin users are provisioned with the CLI helper; public registration creates
  student accounts only.
- Do not describe fee entries as full costs or eligibility results as admission
  decisions.
- Password-reset token storage exists, but reset flows and email delivery are
  not implemented.
- Current gaps include advanced directory filters, complete profile facilities
  and map sections, admin student/settings/log screens, and full authenticated
  end-to-end tests.
- Production still requires reviewed security configuration, HTTPS, monitoring,
  backups, migration discipline, and real source verification.
