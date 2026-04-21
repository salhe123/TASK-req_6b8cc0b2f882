# Test Coverage And README Audit Report

## Stack Reality Check

This repository is a **ThinkPHP 6.1 (PHP 8.1) + Layui 2.9 + MySQL 8.0** full-stack
application delivered as a Docker-orchestrated project (see `repo/metadata.json:4-8`,
`repo/composer.json`, `repo/route/app.php`, `repo/view/`). The prior revision of this
report asserted a Flask + HTMX + pytest stack; those claims were incorrect and have
been removed. All tests execute under **PHPUnit** inside a self-contained Docker
image (`precision-portal-tests`) built from `repo/Dockerfile.test` and driven by
`repo/run_tests.sh`.

## Tests Check

### Test Suites Present (from `repo/phpunit.xml`)

- **Unit** (`repo/tests/unit/`) — 12 files covering state machines, service-layer
  logic, validation rules, and middleware units.
- **Integration** (`repo/tests/integration/`) — 14 files exercising services +
  controllers + schema in-process against a live MariaDB instance (no HTTP).
- **API** (`repo/tests/api/`) — 14 files issuing real HTTP requests against a
  `php -S` server on port 80, covering route + middleware + controller +
  database end-to-end.

No frontend-component test suite is present (the prior `tests/frontend/test_htmx.py`
and `tests/e2e/test_full_workflow.py` claims were false — those files do not exist
in the repository). An optional Cypress scaffold exists at `repo/cypress/e2e/`
but is **not** run by `run_tests.sh`.

## `run_tests.sh` Static Verification

- `repo/run_tests.sh` exists, is executable, and runs `set -euo pipefail`
  (`repo/run_tests.sh:2`).
- Builds `Dockerfile.test` into an image tagged **`precision-portal-tests`**
  (`repo/run_tests.sh:7`) — *not* `fieldservice-tests` as previously claimed.
- Runs the container via `docker run --rm precision-portal-tests`
  (`repo/run_tests.sh:13`).
- Container entrypoint `repo/docker/run_tests_entry.sh` provisions MariaDB,
  applies `database/schema.sql` + `database/seed.sql` + `database/seed.php`,
  then runs `vendor/bin/phpunit` for Unit+Integration with coverage (Clover +
  HTML), boots `php -S` on port 80, then runs the API suite — *not* `pytest`
  as previously claimed.
- Coverage gate is enforced at **85%** line coverage
  (`repo/docker/run_tests_entry.sh:117`) — fails non-zero if unmet.
- No host-local PHP/Node/Composer dependency in the primary flow.

## Endpoint Inventory (from `repo/route/app.php`)

All routes are prefixed `/api/*` unless marked as a frontend page route.

| # | Method | Path | Controller | RBAC | Extra Middleware |
|---|---|---|---|---|---|
| 1 | GET | `/api/health` | `Index/health` | — | — |
| 2 | POST | `/api/auth/login` | `auth.Auth/login` | — | — |
| 3 | POST | `/api/auth/logout` | `auth.Auth/logout` | Auth | — |
| 4 | POST | `/api/auth/change-password` | `auth.Auth/changePassword` | Auth | — |
| 5 | GET | `/api/admin/users` | `admin.User/index` | SYSTEM_ADMIN | — |
| 6 | POST | `/api/admin/users` | `admin.User/create` | SYSTEM_ADMIN | — |
| 7 | PUT | `/api/admin/users/:id` | `admin.User/update` | SYSTEM_ADMIN | — |
| 8 | PUT | `/api/admin/users/:id/lock` | `admin.User/lock` | SYSTEM_ADMIN | — |
| 9 | PUT | `/api/admin/users/:id/unlock` | `admin.User/unlock` | SYSTEM_ADMIN | — |
| 10 | GET | `/api/appointments` | `appointment.Appointment/index` | SERVICE_COORDINATOR, PROVIDER | — |
| 11 | POST | `/api/appointments` | `appointment.Appointment/create` | SERVICE_COORDINATOR | Throttle, StepUp |
| 12 | GET | `/api/appointments/:id` | `appointment.Appointment/read` | SERVICE_COORDINATOR, PROVIDER | — |
| 13 | GET | `/api/appointments/:id/history` | `appointment.Appointment/history` | SERVICE_COORDINATOR, PROVIDER | — |
| 14 | PUT | `/api/appointments/:id/confirm` | `appointment.Appointment/confirm` | SERVICE_COORDINATOR | — |
| 15 | PUT | `/api/appointments/:id/reschedule` | `appointment.Appointment/reschedule` | SERVICE_COORDINATOR, SYSTEM_ADMIN | — |
| 16 | PUT | `/api/appointments/:id/cancel` | `appointment.Appointment/cancel` | SERVICE_COORDINATOR | — |
| 17 | PUT | `/api/appointments/:id/repair` | `appointment.Appointment/repair` | SYSTEM_ADMIN | — |
| 18 | PUT | `/api/appointments/:id/check-in` | `appointment.Appointment/checkIn` | PROVIDER | — |
| 19 | PUT | `/api/appointments/:id/check-out` | `appointment.Appointment/checkOut` | PROVIDER | — |
| 20 | POST | `/api/appointments/:id/attachments` | `appointment.Appointment/uploadAttachment` | PROVIDER | — |
| 21 | GET | `/api/appointments/:id/attachments` | `appointment.Appointment/listAttachments` | SERVICE_COORDINATOR, PROVIDER | — |
| 22 | GET | `/api/provider/queue` | `provider.Queue/index` | PROVIDER | — |
| 23 | GET | `/api/production/work-centers` | `production.WorkCenter/index` | PRODUCTION_PLANNER | — |
| 24 | GET | `/api/production/work-centers/:id` | `production.WorkCenter/read` | PRODUCTION_PLANNER | — |
| 25 | POST | `/api/production/work-centers` | `production.WorkCenter/create` | PRODUCTION_PLANNER | — |
| 26 | PUT | `/api/production/work-centers/:id` | `production.WorkCenter/update` | PRODUCTION_PLANNER | — |
| 27 | DELETE | `/api/production/work-centers/:id` | `production.WorkCenter/delete` | PRODUCTION_PLANNER | — |
| 28 | GET | `/api/production/mps` | `production.Mps/index` | PRODUCTION_PLANNER | — |
| 29 | POST | `/api/production/mps` | `production.Mps/create` | PRODUCTION_PLANNER | — |
| 30 | PUT | `/api/production/mps/:id` | `production.Mps/update` | PRODUCTION_PLANNER | — |
| 31 | DELETE | `/api/production/mps/:id` | `production.Mps/delete` | PRODUCTION_PLANNER | — |
| 32 | GET | `/api/production/work-orders` | `production.WorkOrder/index` | PRODUCTION_PLANNER, OPERATOR | — |
| 33 | GET | `/api/production/work-orders/:id` | `production.WorkOrder/read` | PRODUCTION_PLANNER, OPERATOR | — |
| 34 | GET | `/api/production/work-orders/:id/history` | `production.WorkOrder/history` | PRODUCTION_PLANNER, OPERATOR | — |
| 35 | POST | `/api/production/work-orders/explode` | `production.WorkOrder/explode` | PRODUCTION_PLANNER | — |
| 36 | PUT | `/api/production/work-orders/:id/start` | `production.WorkOrder/start` | OPERATOR, PRODUCTION_PLANNER | — |
| 37 | PUT | `/api/production/work-orders/:id/complete` | `production.WorkOrder/complete` | OPERATOR, PRODUCTION_PLANNER | — |
| 38 | PUT | `/api/production/work-orders/:id/repair` | `production.WorkOrder/repair` | SYSTEM_ADMIN | — |
| 39 | GET | `/api/production/capacity` | `production.Capacity/index` | PRODUCTION_PLANNER | — |
| 40 | GET | `/api/catalog/products` | `catalog.Product/index` | PRODUCT_SPECIALIST, CONTENT_MODERATOR, PRODUCTION_PLANNER, REVIEWER | — |
| 41 | POST | `/api/catalog/products` | `catalog.Product/create` | PRODUCT_SPECIALIST | — |
| 42 | GET | `/api/catalog/products/duplicates` | `catalog.Product/duplicates` | CONTENT_MODERATOR | — |
| 43 | GET | `/api/catalog/products/:id` | `catalog.Product/read` | PRODUCT_SPECIALIST, CONTENT_MODERATOR, PRODUCTION_PLANNER, REVIEWER | — |
| 44 | PUT | `/api/catalog/products/:id` | `catalog.Product/update` | PRODUCT_SPECIALIST | — |
| 45 | POST | `/api/catalog/products/:id/submit` | `catalog.Product/submit` | PRODUCT_SPECIALIST | — |
| 46 | GET | `/api/moderation/pending` | `moderation.Moderation/pending` | CONTENT_MODERATOR | — |
| 47 | POST | `/api/moderation/bulk-action` | `moderation.Moderation/bulkAction` | CONTENT_MODERATOR | — |
| 48 | POST | `/api/moderation/merge-review` | `moderation.Moderation/mergeReview` | CONTENT_MODERATOR | — |
| 49 | GET | `/api/reviews/reviewers` | `review.Review/listReviewers` | REVIEW_MANAGER, REVIEWER | — |
| 50 | POST | `/api/reviews/reviewers` | `review.Review/createReviewer` | REVIEW_MANAGER | — |
| 51 | GET | `/api/reviews/reviewers/:id/conflicts` | `review.Review/conflicts` | REVIEW_MANAGER, REVIEWER | — |
| 52 | POST | `/api/reviews/assignments/auto` | `review.Review/autoAssign` | REVIEW_MANAGER | — |
| 53 | GET | `/api/reviews/assignments` | `review.Review/listAssignments` | REVIEWER, REVIEW_MANAGER | — |
| 54 | POST | `/api/reviews/assignments` | `review.Review/assign` | REVIEW_MANAGER | — |
| 55 | GET | `/api/reviews/scorecards` | `review.Review/listScorecards` | REVIEW_MANAGER, REVIEWER | — |
| 56 | POST | `/api/reviews/scorecards` | `review.Review/createScorecard` | REVIEW_MANAGER | — |
| 57 | POST | `/api/reviews/submissions` | `review.Review/submit` | REVIEWER | — |
| 58 | POST | `/api/reviews/submissions/:id/publish` | `review.Review/publish` | REVIEW_MANAGER | — |
| 59 | GET | `/api/finance/payments` | `finance.Payment/index` | FINANCE_CLERK | — |
| 60 | GET | `/api/finance/payments/:id` | `finance.Payment/read` | FINANCE_CLERK | — |
| 61 | POST | `/api/finance/payments/import` | `finance.Payment/import` | FINANCE_CLERK | StepUp |
| 62 | GET | `/api/finance/receipts` | `finance.Receipt/index` | FINANCE_CLERK | — |
| 63 | GET | `/api/finance/receipts/:id` | `finance.Receipt/read` | FINANCE_CLERK | — |
| 64 | GET | `/api/finance/receipts/:id/verify` | `finance.Receipt/verify` | FINANCE_CLERK | — |
| 65 | PUT | `/api/finance/receipts/:id/bind` | `finance.Receipt/bind` | FINANCE_CLERK | — |
| 66 | POST | `/api/finance/receipts/callback` | `finance.Receipt/callback` | FINANCE_CLERK | — |
| 67 | POST | `/api/finance/reconciliation/run` | `finance.Reconciliation/run` | FINANCE_CLERK | — |
| 68 | GET | `/api/finance/reconciliation/anomalies` | `finance.Reconciliation/anomalies` | FINANCE_CLERK | — |
| 69 | GET | `/api/finance/settlements` | `finance.Settlement/index` | FINANCE_CLERK | — |
| 70 | POST | `/api/finance/settlements` | `finance.Settlement/create` | FINANCE_CLERK | StepUp |
| 71 | GET | `/api/finance/settlements/:id/report` | `finance.Settlement/report` | FINANCE_CLERK | — |
| 72 | GET | `/api/admin/risk/scores` | `admin.Risk/scores` | SYSTEM_ADMIN | — |
| 73 | GET | `/api/admin/risk/ip-scores` | `admin.Risk/ipScores` | SYSTEM_ADMIN | — |
| 74 | GET | `/api/admin/risk/flags` | `admin.Risk/flags` | SYSTEM_ADMIN | — |
| 75 | PUT | `/api/admin/risk/flags/:id/clear` | `admin.Risk/clearFlag` | SYSTEM_ADMIN | — |
| 76 | GET | `/api/admin/risk/throttles` | `admin.Risk/throttles` | SYSTEM_ADMIN | — |
| 77 | PUT | `/api/admin/risk/throttles` | `admin.Risk/updateThrottles` | SYSTEM_ADMIN | — |
| 78 | GET | `/api/admin/audit/logs` | `admin.Audit/logs` | SYSTEM_ADMIN | — |
| 79 | GET | `/api/dashboard` | `admin.Dashboard/index` | Auth (role-aware) | — |
| 80 | GET | `/api/admin/dashboard` | `admin.Dashboard/index` | SYSTEM_ADMIN | — |
| P1 | GET | `/` | `Index/index` | — | frontend |
| P2 | GET | `/login` | `page.Page/login` | — | frontend |
| P3 | GET | `/dashboard` | `page.Page/dashboard` | Auth | frontend |
| P4 | GET | `/appointments` | `page.Page/appointmentsIndex` | SERVICE_COORDINATOR, PROVIDER | frontend |
| P5 | GET | `/appointments/create` | `page.Page/appointmentsCreate` | SERVICE_COORDINATOR | frontend |
| P6 | GET | `/provider/queue` | `page.Page/providerQueue` | PROVIDER | frontend |
| P7 | GET | `/production/mps` | `page.Page/productionMps` | PRODUCTION_PLANNER | frontend |
| P8 | GET | `/production/work-orders` | `page.Page/productionWorkOrders` | PRODUCTION_PLANNER, OPERATOR | frontend |
| P9 | GET | `/production/capacity` | `page.Page/productionCapacity` | PRODUCTION_PLANNER | frontend |
| P10 | GET | `/catalog/products` | `page.Page/catalogProducts` | PRODUCT_SPECIALIST, CONTENT_MODERATOR, PRODUCTION_PLANNER, REVIEWER | frontend |
| P11 | GET | `/moderation` | `page.Page/moderationIndex` | CONTENT_MODERATOR | frontend |
| P12 | GET | `/reviews/assignments` | `page.Page/reviewsAssignments` | REVIEWER, REVIEW_MANAGER | frontend |
| P13 | GET | `/reviews/scorecards` | `page.Page/reviewsScorecards` | REVIEWER, REVIEW_MANAGER | frontend |
| P14 | GET | `/finance/payments` | `page.Page/financePayments` | FINANCE_CLERK | frontend |
| P15 | GET | `/finance/receipts` | `page.Page/financeReceipts` | FINANCE_CLERK | frontend |
| P16 | GET | `/finance/reconciliation` | `page.Page/financeReconciliation` | FINANCE_CLERK | frontend |
| P17 | GET | `/finance/settlements` | `page.Page/financeSettlements` | FINANCE_CLERK | frontend |
| P18 | GET | `/admin/users` | `page.Page/adminUsers` | SYSTEM_ADMIN | frontend |
| P19 | GET | `/admin/risk` | `page.Page/adminRisk` | SYSTEM_ADMIN | frontend |
| P20 | GET | `/admin/audit` | `page.Page/adminAudit` | SYSTEM_ADMIN | frontend |

**Total:** 80 API endpoints + 20 frontend page routes = 100 routes.

## Per-Endpoint Coverage Mapping

Coverage status legend:
- `covered` — at least one happy-path + negative assertion in a test file.
- `basically covered` — happy-path asserted; some negative/boundary cases missing.
- `insufficient` — contract drift, partial surface only, or placeholder-only.
- `uncovered` — no test touches this endpoint directly.

| # | Endpoint | Test File(s) | Status | Gap / Minimum Addition |
|---|---|---|---|---|
| 1 | GET `/api/health` | `tests/api/AuthApiTest.php` (smoke) | basically covered | Add dedicated health assertion |
| 2 | POST `/api/auth/login` | `tests/api/AuthApiTest.php:10-88`, `tests/integration/AuthServiceIntegrationTest.php` | covered | — |
| 3 | POST `/api/auth/logout` | `tests/api/AuthApiTest.php` | covered | — |
| 4 | POST `/api/auth/change-password` | `tests/api/AuthApiTest.php:89-177` | covered | — |
| 5 | GET `/api/admin/users` | `tests/api/UserApiTest.php` | covered | — |
| 6 | POST `/api/admin/users` | `tests/api/UserApiTest.php` | covered | — |
| 7 | PUT `/api/admin/users/:id` | `tests/api/UserApiTest.php` | covered | — |
| 8 | PUT `/api/admin/users/:id/lock` | `tests/api/UserApiTest.php:113-119` | basically covered | Add post-lock login-blocked assertion |
| 9 | PUT `/api/admin/users/:id/unlock` | `tests/api/UserApiTest.php` | basically covered | Add post-unlock login-allowed assertion |
| 10 | GET `/api/appointments` | `tests/api/AppointmentApiTest.php` | covered | — |
| 11 | POST `/api/appointments` | `tests/api/AppointmentApiTest.php:28-80`, `tests/api/StepUpHoldApiTest.php` | covered | — |
| 12 | GET `/api/appointments/:id` | `tests/api/AppointmentApiTest.php` | covered | — |
| 13 | GET `/api/appointments/:id/history` | `tests/integration/SecurityCoverageTest.php:23-55` | covered | — |
| 14 | PUT `/api/appointments/:id/confirm` | `tests/api/AppointmentApiTest.php`, `tests/integration/AppointmentServiceIntegrationTest.php:61-97` | covered | — |
| 15 | PUT `/api/appointments/:id/reschedule` | `tests/api/AppointmentApiTest.php:147-172`, `tests/integration/AppointmentServiceIntegrationTest.php:151-170` | covered | Add boundary test exactly at 2h threshold |
| 16 | PUT `/api/appointments/:id/cancel` | `tests/api/AppointmentApiTest.php` | covered | — |
| 17 | PUT `/api/appointments/:id/repair` | `tests/integration/AppointmentServiceIntegrationTest.php` | covered | — |
| 18 | PUT `/api/appointments/:id/check-in` | `tests/api/AppointmentApiTest.php:28-133` | covered | — |
| 19 | PUT `/api/appointments/:id/check-out` | `tests/api/AppointmentApiTest.php:28-133` | covered | Add evidence-required negative path |
| 20 | POST `/api/appointments/:id/attachments` | `tests/api/AppointmentApiTest.php` | basically covered | Add >10MB rejection + non-PDF/image rejection |
| 21 | GET `/api/appointments/:id/attachments` | `tests/api/AppointmentApiTest.php` | covered | — |
| 22 | GET `/api/provider/queue` | `tests/api/AppointmentApiTest.php` | basically covered | Add dedicated queue ordering test |
| 23 | GET `/api/production/work-centers` | `tests/api/ProductionApiTest.php` | covered | — |
| 24 | GET `/api/production/work-centers/:id` | `tests/api/ProductionApiTest.php` | covered | — |
| 25 | POST `/api/production/work-centers` | `tests/api/ProductionApiTest.php` | covered | — |
| 26 | PUT `/api/production/work-centers/:id` | `tests/api/ProductionApiTest.php` | basically covered | Add capacity-change propagation test |
| 27 | DELETE `/api/production/work-centers/:id` | `tests/api/ProductionApiTest.php` | basically covered | Add in-use-delete rejection test |
| 28 | GET `/api/production/mps` | `tests/api/ProductionApiTest.php` | covered | — |
| 29 | POST `/api/production/mps` | `tests/api/ProductionApiTest.php` | covered | — |
| 30 | PUT `/api/production/mps/:id` | `tests/api/ProductionApiTest.php` | covered | — |
| 31 | DELETE `/api/production/mps/:id` | `tests/api/ProductionApiTest.php` | basically covered | Add cascade-to-work-orders test |
| 32 | GET `/api/production/work-orders` | `tests/api/ProductionApiTest.php`, `tests/integration/ProductionServiceIntegrationTest.php` | covered | — |
| 33 | GET `/api/production/work-orders/:id` | `tests/api/ProductionApiTest.php` | covered | — |
| 34 | GET `/api/production/work-orders/:id/history` | `tests/integration/SecurityCoverageTest.php` | basically covered | Add trigger parity to appointment_history |
| 35 | POST `/api/production/work-orders/explode` | `tests/integration/ProductionServiceIntegrationTest.php` | covered | — |
| 36 | PUT `/api/production/work-orders/:id/start` | `tests/api/ProductionApiTest.php` | covered | — |
| 37 | PUT `/api/production/work-orders/:id/complete` | `tests/api/ProductionApiTest.php`, `tests/unit/ProductionCapacityTest.php` | covered | — |
| 38 | PUT `/api/production/work-orders/:id/repair` | `tests/integration/ProductionServiceIntegrationTest.php` | basically covered | Add admin-only 403 assertion |
| 39 | GET `/api/production/capacity` | `tests/unit/ProductionCapacityTest.php`, `tests/api/ProductionApiTest.php` | covered | — |
| 40 | GET `/api/catalog/products` | `tests/api/CatalogApiTest.php`, `tests/api/BlindReviewProductReadApiTest.php:16-87` | covered | Add blind masking regression for list pagination |
| 41 | POST `/api/catalog/products` | `tests/api/CatalogApiTest.php`, `tests/api/CatalogOwnershipApiTest.php:19-55` | covered | — |
| 42 | GET `/api/catalog/products/duplicates` | `tests/integration/CatalogServiceIntegrationTest.php` | basically covered | Add API-level duplicates assertion |
| 43 | GET `/api/catalog/products/:id` | `tests/api/BlindReviewProductReadApiTest.php` | covered | — |
| 44 | PUT `/api/catalog/products/:id` | `tests/api/CatalogApiTest.php` | covered | — |
| 45 | POST `/api/catalog/products/:id/submit` | `tests/api/CatalogApiTest.php`, `tests/unit/CatalogServiceTest.php` | covered | — |
| 46 | GET `/api/moderation/pending` | `tests/api/ModerationApiTest.php` | covered | — |
| 47 | POST `/api/moderation/bulk-action` | `tests/api/ModerationApiTest.php`, `tests/unit/ModerationTest.php` | covered | — |
| 48 | POST `/api/moderation/merge-review` | `tests/integration/ModerationServiceIntegrationTest.php` | covered | — |
| 49 | GET `/api/reviews/reviewers` | `tests/api/ReviewApiTest.php` | covered | — |
| 50 | POST `/api/reviews/reviewers` | `tests/api/ReviewApiTest.php`, `tests/integration/PoolGovernanceTest.php:27-77` | covered | — |
| 51 | GET `/api/reviews/reviewers/:id/conflicts` | `tests/unit/ReviewConflictTest.php`, `tests/integration/ReviewServiceIntegrationTest.php` | covered | — |
| 52 | POST `/api/reviews/assignments/auto` | `tests/integration/ReviewServiceIntegrationTest.php:94-106` | covered | — |
| 53 | GET `/api/reviews/assignments` | `tests/api/ReviewApiTest.php` | covered | — |
| 54 | POST `/api/reviews/assignments` | `tests/api/ReviewApiTest.php`, `tests/unit/ReviewConflictTest.php` | covered | — |
| 55 | GET `/api/reviews/scorecards` | `tests/api/ReviewApiTest.php` | covered | — |
| 56 | POST `/api/reviews/scorecards` | `tests/unit/ScorecardValidationTest.php` | covered | — |
| 57 | POST `/api/reviews/submissions` | `tests/api/ReviewApiTest.php`, `tests/integration/ReviewServiceIntegrationTest.php` | covered | Add configurable-rating-levels assertion (see audit #2) |
| 58 | POST `/api/reviews/submissions/:id/publish` | `tests/api/ReviewApiTest.php` | covered | — |
| 59 | GET `/api/finance/payments` | `tests/api/FinanceApiTest.php` | covered | — |
| 60 | GET `/api/finance/payments/:id` | `tests/api/FinanceApiTest.php` | covered | — |
| 61 | POST `/api/finance/payments/import` | `tests/api/FinanceApiTest.php`, `tests/integration/SecurityCoverageTest.php:85-188` | covered | — |
| 62 | GET `/api/finance/receipts` | `tests/api/FinanceApiTest.php` | covered | — |
| 63 | GET `/api/finance/receipts/:id` | `tests/api/FinanceApiTest.php` | covered | — |
| 64 | GET `/api/finance/receipts/:id/verify` | `tests/api/FinanceCallbackApiTest.php` | covered | — |
| 65 | PUT `/api/finance/receipts/:id/bind` | `tests/api/FinanceApiTest.php` | basically covered | Add negative (non-matching amount) assertion |
| 66 | POST `/api/finance/receipts/callback` | `tests/api/FinanceCallbackApiTest.php:14-49` | basically covered | Add replay test (same signed callback twice) |
| 67 | POST `/api/finance/reconciliation/run` | `tests/integration/FinanceServiceIntegrationTest.php:160-193`, `tests/api/FinanceApiTest.php:32-48` | covered | Add variance-boundary tests at exactly $50.00 vs $50.01 |
| 68 | GET `/api/finance/reconciliation/anomalies` | `tests/api/FinanceApiTest.php` | covered | — |
| 69 | GET `/api/finance/settlements` | `tests/api/FinanceApiTest.php` | covered | — |
| 70 | POST `/api/finance/settlements` | `tests/integration/FinanceServiceIntegrationTest.php`, `tests/unit/FinanceReconciliationTest.php` | covered | — |
| 71 | GET `/api/finance/settlements/:id/report` | `tests/api/FinanceApiTest.php` | basically covered | Add report-content shape assertion |
| 72 | GET `/api/admin/risk/scores` | `tests/api/RiskApiTest.php:65-71`, `tests/unit/RiskScoringTest.php` | covered | — |
| 73 | GET `/api/admin/risk/ip-scores` | `tests/api/RiskApiTest.php` | basically covered | Add IP-risk scoring expectation |
| 74 | GET `/api/admin/risk/flags` | `tests/api/RiskApiTest.php`, `tests/integration/RiskServiceIntegrationTest.php` | covered | — |
| 75 | PUT `/api/admin/risk/flags/:id/clear` | `tests/api/RiskApiTest.php` | covered | — |
| 76 | GET `/api/admin/risk/throttles` | `tests/api/RiskApiTest.php` | basically covered | Add throttle-value-visibility assertion |
| 77 | PUT `/api/admin/risk/throttles` | `tests/api/RiskApiTest.php`, `tests/integration/MiddlewareIntegrationTest.php:31-103` | covered | — |
| 78 | GET `/api/admin/audit/logs` | `tests/integration/AuditServiceIntegrationTest.php`, `tests/unit/AuditLogTest.php` | covered | — |
| 79 | GET `/api/dashboard` | `tests/api/DashboardApiTest.php:10-33` | **insufficient** | Contract drift: test expects flat keys; controller returns nested `{ role, stats }`. Align to `data.stats.*` (audit #5). |
| 80 | GET `/api/admin/dashboard` | `tests/api/DashboardApiTest.php:65-71` | basically covered | Admin-only 403 assertion present |
| P1–P20 | Frontend page routes | `tests/integration/ControllerIntegrationTest.php`, `tests/integration/MiddlewareIntegrationTest.php` | basically covered | No browser-level E2E; Cypress suite exists but is not run by `run_tests.sh` |

### Security Coverage Audit

- **Authentication:** *sufficiently covered* — `tests/api/AuthApiTest.php`, `tests/integration/AuthServiceIntegrationTest.php`.
- **Route authorization:** *basically covered* — per-endpoint role checks across every `*ApiTest.php`.
- **Object-level authorization:** *basically covered* — `tests/api/AppointmentApiTest.php`, `tests/api/CatalogOwnershipApiTest.php`, `tests/api/BlindReviewProductReadApiTest.php`.
- **Immutable audit history:** *covered* — `tests/integration/SecurityCoverageTest.php:23-55` validates trigger-protected update/delete rejection.
- **Step-up hold:** *insufficient* — only appointment-create path verified in `tests/api/StepUpHoldApiTest.php`; other sensitive-mutation surfaces unexercised (audit #4).
- **CSRF:** *uncovered* — no CSRF middleware exists and no CSRF test coverage exists (audit #3).
- **Tenant / data isolation:** *cannot confirm* — single-tenant architecture; only per-role/object restrictions are tested.

### Final Coverage Judgment

**Partial Pass.** Major happy paths and most failure paths are exercised, but:
- Dashboard contract drift (Endpoint #79) is a live test-vs-code disagreement.
- Step-up enforcement is tested on only one endpoint class.
- CSRF coverage is absent because the control itself is missing.
- No browser-driven E2E validation of the frontend page routes (P1–P20).

## README Audit

### README audit scope
The audit below inspects `repo/README.md` against three criteria:
1. Template conformance (project-supplied template).
2. Factual accuracy vs. repository contents.
3. Rule compliance (containerization rule: README must not contain manual dependency-install commands such as `composer install` or `npm install`).

### README findings

| Area | Observation | Status |
|---|---|---|
| Project name & description | Project name and one-line description present. | pass |
| Tech stack section | ThinkPHP / Layui / MySQL / Docker listed. | pass |
| Project structure tree | ASCII tree with required files (`docker-compose.yml`, `run_tests.sh`, `.env.example`, `README.md`) marked. | pass |
| Prerequisites | Docker + Docker Compose listed. | pass |
| Run the application | `docker compose up --build -d` present. | pass |
| Test instructions | `chmod +x run_tests.sh` + `./run_tests.sh`; exit-code contract stated. | pass |
| Seeded credentials | Per-role credentials table present. | pass |
| **Forbidden manual dependency install — `composer install`** | Present at `repo/README.md:71`, `:72`, `:75`. | **fail (Rule 6)** |
| **Forbidden manual dependency install — `npm install`** | Present at `repo/README.md:136`. | **fail (Rule 6)** |
| E2E/Cypress optional section | Prior README referenced Cypress with `npm install`, which violates Rule 6. | **fail (Rule 6)** |
| API spec reference | README should either reference `docs/api-spec.md` as a doc pointer or omit it — current link is valid. | pass |

### README remediation
- Rewrite `repo/README.md` using the project-supplied template.
- Remove all `composer install` and `npm install` instructions; move dependency install entirely into the Docker image build (`Dockerfile`, `Dockerfile.test`) so the end-user's path is strictly `docker compose up --build -d` and `./run_tests.sh`.
- Keep all offline-determinism context inside the Dockerfiles; README should not describe manual lockfile/vendor workflows.

## Test Coverage Score
**78/100**

## Score Rationale
- Breadth across Unit/Integration/API is strong; 80 API endpoints each have at least one test touching them.
- Real Dockerized test execution with an 85% line-coverage gate is enforced automatically.
- Deductions are driven by:
  - Dashboard API contract drift (1 `insufficient`).
  - Step-up hold surface covered on only one endpoint.
  - CSRF control + coverage entirely missing (control-side and test-side gap).
  - No browser-driven E2E coverage of the 20 frontend page routes.

## Key Gaps
- `/api/dashboard` test contract drift (expects flat keys; controller returns nested `{ role, stats }`).
- Step-up hold coverage only on appointment create path.
- CSRF middleware and associated negative tests entirely absent.
- Callback replay (idempotency) test missing for `POST /api/finance/receipts/callback`.
- Reconciliation variance-boundary assertions missing at exactly `$50.00` vs `$50.01`.
- No browser-driven frontend E2E (Cypress scaffold exists but is not wired into `run_tests.sh`).

## Notes
- This review is static inspection only — no test execution was performed during the audit.
- The previous revision of this file incorrectly described a Flask/HTMX/pytest stack with `fieldservice-tests` and `pytest tests/`. Those claims have been removed; the actual stack is ThinkPHP/Layui/PHPUnit with a `precision-portal-tests` Docker image and a `vendor/bin/phpunit` invocation (see `repo/run_tests.sh:7,13` and `repo/docker/run_tests_entry.sh:78,103`).
