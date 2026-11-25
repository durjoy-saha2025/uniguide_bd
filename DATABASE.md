# Database

`database/schema.sql` targets MySQL 8.0 and compatible MariaDB versions, and
creates an empty schema. It does not include passwords, demo universities, or
unverifiable education information.

## Tables

- `users`: student/admin role, account status, and password hash storage.
- `student_profiles`: optional academic details and discovery preferences.
- `universities`, `programs`: public catalog, with draft/published/archived states.
- `program_fees`, `admission_requirements`, `scholarships`, `rankings`: source-linked
  data with separate verification status and date fields.
- `sources`: source metadata and verification state.
- `news_updates`: draft/review/approved/published/archived workflow.
- `student_guides`: categorized guide content and SEO fields.
- `saved_universities`, `student_comparison_lists`, `student_comparison_items`:
  student-owned saved and comparison data.
- `password_reset_tokens`: hashed, expiring reset tokens.
- `admin_activity_logs`: admin action audit trail.

Foreign keys preserve ownership and related records. Unique constraints prevent
duplicate email addresses, university slugs, and repeated saved/comparison
entries. GPA and monetary values have database checks as a second validation
layer.

The local XAMPP/MariaDB `uniguide_bd` database has the schema imported and
currently contains no university records. The PHP app uses a generated
least-privilege database account held in the ignored `.env` file. The API
therefore returns an empty listing. Do not add unverified education data simply
to populate the interface.

## Setup notes

Create the database first, then configure the ignored root `.env` file using
`.env.example` as a template. Use an existing app account, or configure an
administrative local connection temporarily and run the provisioning helper to
create a least-privilege app account. Never commit real credentials.

The schema is a foundation, not a finished production migration system. Before
production, add versioned migrations, retention rules, backups, and a reviewed
least-privilege database account.

For databases created before source-backed university verification was added,
apply `database/migrations/001_university_sources.sql` once. It adds the
restrictive source reference used to require evidence before a university can
be verified and displayed publicly.
