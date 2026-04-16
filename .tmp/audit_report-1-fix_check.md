# Follow-Up Review of Previously Reported Issues

Date: 2026-04-16

## Scope and Boundary

- Reviewed the previously reported issues from `.tmp/delivery_acceptance_architecture_audit.md`.
- Static analysis only.
- Did not start the project, run tests, run Docker, or perform browser/manual checks.
- Conclusions below are limited to what is provable from the current repository contents.

## Summary

- Fixed: 0
- Partially Fixed: 0
- Not Fixed: 10

## Issue-by-Issue Verification

### 1. Catalog low-confidence dedup flow is not truly moderator-gated
- Status: `Not Fixed`
- Rationale: The low-confidence branch still writes `action='APPROVE'` as a placeholder and uses submitter identity in `moderator_id` rather than a pending moderator decision workflow.
- Evidence:
  - `app/service/CatalogService.php:337`
  - `app/service/CatalogService.php:342`
  - `app/service/CatalogService.php:343`

### 2. Reviewer pool curation is largely non-persistent
- Status: `Not Fixed`
- Rationale: `createReviewer()` still validates and logs specialties but does not persist reviewer-specialty/pool metadata.
- Evidence:
  - `app/service/ReviewService.php:24`
  - `app/service/ReviewService.php:34`
  - `app/service/ReviewService.php:38`

### 3. Login flow exposes session identifier and lacks explicit session-ID rotation
- Status: `Not Fixed`
- Rationale: Login still returns `session_id()` as token and no explicit session regeneration is present at login.
- Evidence:
  - `app/service/AuthService.php:86`
  - `app/service/AuthService.php:99`

### 4. Documentation overstates automated test scope (E2E claim mismatch)
- Status: `Not Fixed`
- Rationale: README still claims unit/API/E2E are executed by one script, while the script path runs PHPUnit suites only.
- Evidence:
  - `README.md:106`
  - `run_tests.sh:13`
  - `docker/run_tests_entry.sh:78`
  - `docker/run_tests_entry.sh:101`

### 5. Separation of duties for catalog drafting vs moderation is collapsed
- Status: `Not Fixed`
- Rationale: Catalog creation/submission and moderation remain under `CONTENT_MODERATOR` role.
- Evidence:
  - `route/app.php:102`
  - `route/app.php:110`
  - `route/app.php:115`

### 6. README references missing artifact (`api-spec.md`)
- Status: `Not Fixed`
- Rationale: README still references `api-spec.md`, and the file is still absent.
- Evidence:
  - `README.md:55`
  - `api-spec.md` (missing in repository root)

### 7. Key startup enforcement claim is stronger than code behavior
- Status: `Not Fixed`
- Rationale: README still claims startup refusal without keys, but enforcement remains in crypto helper calls, not in bootstrap startup path.
- Evidence:
  - `README.md:72`
  - `app/common.php:29`
  - `public/index.php:6`

### 8. Appointment auto-expire path is not transaction-protected per item
- Status: `Not Fixed`
- Rationale: `expirePending()` still mutates status/history/audit in-loop without transaction boundaries around each item.
- Evidence:
  - `app/service/AppointmentService.php:206`
  - `app/service/AppointmentService.php:217`
  - `app/service/AppointmentService.php:221`
  - `app/service/AppointmentService.php:222`

### 9. Operator-specific production role/workspace is not explicit
- Status: `Not Fixed`
- Rationale: Role enum still lacks an operator role and completion endpoint remains under planner RBAC.
- Evidence:
  - `database/schema.sql:12`
  - `route/app.php:90`
  - `app/controller/production/WorkOrder.php:59`

### 10. Cypress assets exist without project-level Node manifest
- Status: `Not Fixed`
- Rationale: Cypress config exists, but `package.json` is still not present for reproducible E2E dependency/script execution.
- Evidence:
  - `cypress.config.js:1`
  - `package.json` (missing in repository root)

## Final Assessment

None of the previously reported 10 issues are resolved by current static repository evidence.

Static boundary still applies:

- This recheck did not execute the project or tests.
- Runtime behavior, browser rendering, and full deployment integration remain manual verification items.
