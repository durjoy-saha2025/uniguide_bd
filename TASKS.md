# Development Tasks

## Completed in the current backend milestone

- [x] Connect the project to the local `uniguide_bd` MariaDB database.
- [x] Create the initial relational schema without invented seed records.
- [x] Add environment-based PDO and safe JSON error responses.
- [x] Add public verified-university search and pagination.
- [x] Add student registration, login, logout, password change, and deletion.
- [x] Add session cookies, CSRF checks, and owner-only student-profile CRUD.
- [x] Add role-protected university CRUD with activity logging.
- [x] Add admin source CRUD and require verified sources for public university records.
- [x] Add a CLI-only first super-admin setup command.
- [x] Add a local least-privilege database-user provisioning helper.

## Remaining backend milestones

- [ ] Implement CRUD endpoints for programs, fees, requirements, scholarships,
  rankings, news, and guides.
- [ ] Add student saved-university and comparison APIs.
- [ ] Implement eligibility results using verified program requirements only.
- [ ] Implement transparent recommendations and verified-fee budget estimates.
- [ ] Add password reset delivery and login throttling.
- [ ] Connect the directory, profiles, and comparison UI to the APIs.
- [ ] Build and connect student and admin dashboard interfaces.
- [ ] Add endpoint tests for authorization, validation, privacy, and remaining CRUD.
- [ ] Review production configuration, migrations, backup and deployment steps.
