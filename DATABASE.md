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

For an existing database, apply each migration that has not already been
applied, in order:

1. `database/migrations/001_university_sources.sql` adds the restrictive
   university-to-source reference.
2. `database/migrations/002_program_sources.sql` adds program-level source
   evidence.
3. `database/migrations/003_auth_login_attempts.sql` creates the login-throttle
   table.

The initial `schema.sql` already contains these structures for a new database.
This is not yet a full migration runner; track applied migrations before
re-running non-idempotent ALTER statements. Before production, add a versioned
migration process, retention rules, backups, and a reviewed least-privilege DB
account.
