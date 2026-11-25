# Changelog

## 2026-11-25

- Added the initial PHP/PDO backend and MySQL/MariaDB schema with no demo data.
- Added source-verified university and catalog APIs, including admin CRUD for
  programs, fees, requirements, scholarships, rankings, news, and guides.
- Added student-owned saved/comparison data, eligibility screening, transparent
  suggestions, and verified fee-component reporting.
- Added session/CSRF protections, login throttling, and administrative activity
  logging.
- Connected the public directory, profile, and comparison pages to real APIs.
- Added student account/dashboard pages and a source-aware admin CRUD workspace.
- Added public guide and university-update pages, and refreshed homepage
  discovery links and latest verified updates.
- Added sorting for admin university/source/catalog lists and corrected hidden
  UI states so forms, lists, and dialogs respect the `hidden` attribute.
- Updated setup, architecture, task, and project-context documentation.
- Verified PHP syntax and repository tests; checked empty-state rendering and a
  narrow mobile viewport in the browser.

## Known incomplete work

Password reset delivery, advanced directory filters, some profile sections,
admin student/settings/audit-log screens, and broader authenticated and
production validation remain future work.
