# Test Coverage Audit

## Scope & Method
- Mode: static inspection only (no test execution, no app/runtime execution).
- Route source: `route/app.php:9-213` (API endpoints only).
- HTTP test source: `tests/api/*.php` via `tests/ApiTestCase.php:13-106` (real curl HTTP client to `http://localhost:80`).
- Non-HTTP test source: `tests/unit/*.php`, `tests/integration/*.php`.

## Project Type Detection
- README does not explicitly declare one of the required top labels (`backend|fullstack|web|android|ios|desktop`) at the top.
- Inferred type: **fullstack**.
- Evidence: `README.md:3` ("full-stack"), `README.md:7-8` (frontend + backend stack both declared).

## Backend Endpoint Inventory
1. GET `/api/health`
2. POST `/api/auth/login`
3. POST `/api/auth/logout`
4. POST `/api/auth/change-password`
5. GET `/api/admin/users`
6. POST `/api/admin/users`
7. PUT `/api/admin/users/:id/lock`
8. PUT `/api/admin/users/:id/unlock`
9. PUT `/api/admin/users/:id`
10. GET `/api/appointments`
11. POST `/api/appointments`
12. GET `/api/appointments/:id`
13. GET `/api/appointments/:id/history`
14. PUT `/api/appointments/:id/confirm`
15. PUT `/api/appointments/:id/reschedule`
16. PUT `/api/appointments/:id/cancel`
17. PUT `/api/appointments/:id/repair`
18. PUT `/api/appointments/:id/check-in`
19. PUT `/api/appointments/:id/check-out`
20. POST `/api/appointments/:id/attachments`
21. GET `/api/appointments/:id/attachments`
22. GET `/api/provider/queue`
23. GET `/api/production/work-centers`
24. GET `/api/production/work-centers/:id`
25. POST `/api/production/work-centers`
26. PUT `/api/production/work-centers/:id`
27. DELETE `/api/production/work-centers/:id`
28. GET `/api/production/mps`
29. POST `/api/production/mps`
30. PUT `/api/production/mps/:id`
31. DELETE `/api/production/mps/:id`
32. GET `/api/production/work-orders`
33. GET `/api/production/work-orders/:id`
34. GET `/api/production/work-orders/:id/history`
35. POST `/api/production/work-orders/explode`
36. PUT `/api/production/work-orders/:id/start`
37. PUT `/api/production/work-orders/:id/complete`
38. PUT `/api/production/work-orders/:id/repair`
39. GET `/api/production/capacity`
40. GET `/api/catalog/products`
41. POST `/api/catalog/products`
42. GET `/api/catalog/products/duplicates`
43. GET `/api/catalog/products/:id`
44. PUT `/api/catalog/products/:id`
45. POST `/api/catalog/products/:id/submit`
46. GET `/api/moderation/pending`
47. POST `/api/moderation/bulk-action`
48. POST `/api/moderation/merge-review`
49. GET `/api/reviews/reviewers`
50. POST `/api/reviews/reviewers`
51. GET `/api/reviews/reviewers/:id/conflicts`
52. POST `/api/reviews/assignments/auto`
53. GET `/api/reviews/assignments`
54. POST `/api/reviews/assignments`
55. GET `/api/reviews/scorecards`
56. POST `/api/reviews/scorecards`
57. POST `/api/reviews/submissions/:id/publish`
58. POST `/api/reviews/submissions`
59. GET `/api/finance/payments`
60. GET `/api/finance/payments/:id`
61. POST `/api/finance/payments/import`
62. GET `/api/finance/receipts`
63. POST `/api/finance/receipts/callback`
64. GET `/api/finance/receipts/:id/verify`
65. PUT `/api/finance/receipts/:id/bind`
66. GET `/api/finance/receipts/:id`
67. POST `/api/finance/reconciliation/run`
68. GET `/api/finance/reconciliation/anomalies`
69. GET `/api/finance/settlements`
70. POST `/api/finance/settlements`
71. GET `/api/finance/settlements/:id/report`
72. GET `/api/admin/risk/scores`
73. GET `/api/admin/risk/ip-scores`
74. GET `/api/admin/risk/flags`
75. PUT `/api/admin/risk/flags/:id/clear`
76. GET `/api/admin/risk/throttles`
77. PUT `/api/admin/risk/throttles`
78. GET `/api/admin/audit/logs`
79. GET `/api/dashboard`
80. GET `/api/admin/dashboard`

Evidence: `route/app.php:9-213`.

## API Test Mapping Table

| Endpoint | Covered | Test Type | Test Files | Evidence |
|---|---|---|---|---|
| GET `/api/health` | yes | true no-mock HTTP | `tests/api/AuthApiTest.php` | `testHealthEndpoint` (`tests/api/AuthApiTest.php:98-106`) |
| POST `/api/auth/login` | yes | true no-mock HTTP | `tests/api/AuthApiTest.php` | `testLoginSuccess` (`tests/api/AuthApiTest.php:10-25`) |
| POST `/api/auth/logout` | yes | true no-mock HTTP | `tests/api/AuthApiTest.php` | `testLogout` (`tests/api/AuthApiTest.php:56-66`) |
| POST `/api/auth/change-password` | yes | true no-mock HTTP | `tests/api/AuthApiTest.php` | `testChangePasswordSuccess` (`tests/api/AuthApiTest.php:108-134`) |
| GET `/api/admin/users` | yes | true no-mock HTTP | `tests/api/UserApiTest.php` | `testListUsers` (`tests/api/UserApiTest.php:10-30`) |
| POST `/api/admin/users` | yes | true no-mock HTTP | `tests/api/UserApiTest.php` | `testCreateUser` (`tests/api/UserApiTest.php:45-61`) |
| PUT `/api/admin/users/:id/lock` | yes | true no-mock HTTP | `tests/api/UserApiTest.php` | `testLockUser` (`tests/api/UserApiTest.php:75-92`) |
| PUT `/api/admin/users/:id/unlock` | yes | true no-mock HTTP | `tests/api/UserApiTest.php` | `testUnlockUser` (`tests/api/UserApiTest.php:94-111`) |
| PUT `/api/admin/users/:id` | yes | true no-mock HTTP | `tests/api/UserApiTest.php` | `testUpdateUserRole` (`tests/api/UserApiTest.php:121-134`) |
| GET `/api/appointments` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testListAppointments` (`tests/api/AppointmentApiTest.php:195-204`) |
| POST `/api/appointments` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testCreateAppointment` (`tests/api/AppointmentApiTest.php:10-26`) |
| GET `/api/appointments/:id` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testGetAppointment` (`tests/api/AppointmentApiTest.php:206-221`) |
| GET `/api/appointments/:id/history` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testFullLifecycle` (`tests/api/AppointmentApiTest.php:62-66`) |
| PUT `/api/appointments/:id/confirm` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testFullLifecycle` (`tests/api/AppointmentApiTest.php:40-44`) |
| PUT `/api/appointments/:id/reschedule` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testRescheduleSuccess` (`tests/api/AppointmentApiTest.php:231-247`) |
| PUT `/api/appointments/:id/cancel` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testCancelPending` (`tests/api/AppointmentApiTest.php:73-86`) |
| PUT `/api/appointments/:id/repair` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testAdminRepair` (`tests/api/AppointmentApiTest.php:174-193`) |
| PUT `/api/appointments/:id/check-in` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testFullLifecycle` (`tests/api/AppointmentApiTest.php:45-50`) |
| PUT `/api/appointments/:id/check-out` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testFullLifecycle` (`tests/api/AppointmentApiTest.php:55-59`) |
| POST `/api/appointments/:id/attachments` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testUploadAttachmentNoFile` (`tests/api/AppointmentApiTest.php:340-355`) |
| GET `/api/appointments/:id/attachments` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testListAttachments` (`tests/api/AppointmentApiTest.php:268-281`) |
| GET `/api/provider/queue` | yes | true no-mock HTTP | `tests/api/AppointmentApiTest.php` | `testProviderQueue` (`tests/api/AppointmentApiTest.php:283-305`) |
| GET `/api/production/work-centers` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testListWorkCenters` (`tests/api/ProductionApiTest.php:10-21`) |
| GET `/api/production/work-centers/:id` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testGetWorkCenterById` (`tests/api/ProductionApiTest.php:185-200`) |
| POST `/api/production/work-centers` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testCreateWorkCenter` (`tests/api/ProductionApiTest.php:23-35`) |
| PUT `/api/production/work-centers/:id` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testUpdateWorkCenter` (`tests/api/ProductionApiTest.php:154-166`) |
| DELETE `/api/production/work-centers/:id` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testDeleteWorkCenter` (`tests/api/ProductionApiTest.php:202-219`) |
| GET `/api/production/mps` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testListMps` (`tests/api/ProductionApiTest.php:145-152`) |
| POST `/api/production/mps` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testMpsExplodeComplete` (`tests/api/ProductionApiTest.php:37-50`) |
| PUT `/api/production/mps/:id` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testUpdateMps` (`tests/api/ProductionApiTest.php:96-107`) |
| DELETE `/api/production/mps/:id` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testDeleteMps` (`tests/api/ProductionApiTest.php:109-120`) |
| GET `/api/production/work-orders` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testListWorkOrdersWithFilter` (`tests/api/ProductionApiTest.php:237-245`) |
| GET `/api/production/work-orders/:id` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testGetWorkOrder` (`tests/api/ProductionApiTest.php:122-143`) |
| GET `/api/production/work-orders/:id/history` | no | none | - | no HTTP request found in `tests/api/*.php` |
| POST `/api/production/work-orders/explode` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testMpsExplodeComplete` (`tests/api/ProductionApiTest.php:51-57`) |
| PUT `/api/production/work-orders/:id/start` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testMpsExplodeComplete` (`tests/api/ProductionApiTest.php:64-67`) |
| PUT `/api/production/work-orders/:id/complete` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testMpsExplodeComplete` (`tests/api/ProductionApiTest.php:67-78`) |
| PUT `/api/production/work-orders/:id/repair` | no | none | - | no HTTP request found in `tests/api/*.php` |
| GET `/api/production/capacity` | yes | true no-mock HTTP | `tests/api/ProductionApiTest.php` | `testCapacityLoading` (`tests/api/ProductionApiTest.php:80-94`) |
| GET `/api/catalog/products` | yes | true no-mock HTTP | `tests/api/CatalogApiTest.php` | `testListProductsWithFilters` (`tests/api/CatalogApiTest.php:52-61`) |
| POST `/api/catalog/products` | yes | true no-mock HTTP | `tests/api/CatalogApiTest.php` | `testCreateProduct` (`tests/api/CatalogApiTest.php:10-25`) |
| GET `/api/catalog/products/duplicates` | yes | true no-mock HTTP | `tests/api/CatalogApiTest.php` | `testDuplicates` (`tests/api/CatalogApiTest.php:78-85`) |
| GET `/api/catalog/products/:id` | yes | true no-mock HTTP | `tests/api/BlindReviewProductReadApiTest.php` | `testBlindAssignedReviewerSeesMaskedProduct` (`tests/api/BlindReviewProductReadApiTest.php:34-58`) |
| PUT `/api/catalog/products/:id` | yes | true no-mock HTTP | `tests/api/CatalogOwnershipApiTest.php` | `testCrossSpecialistCannotModifyOrSubmit` (`tests/api/CatalogOwnershipApiTest.php:41-47`) |
| POST `/api/catalog/products/:id/submit` | yes | true no-mock HTTP | `tests/api/CatalogApiTest.php` | `testSubmitProduct` (`tests/api/CatalogApiTest.php:27-50`) |
| GET `/api/moderation/pending` | yes | true no-mock HTTP | `tests/api/ModerationApiTest.php` | `testPendingQueue` (`tests/api/ModerationApiTest.php:23-34`) |
| POST `/api/moderation/bulk-action` | yes | true no-mock HTTP | `tests/api/ModerationApiTest.php` | `testBulkApprove` (`tests/api/ModerationApiTest.php:36-52`) |
| POST `/api/moderation/merge-review` | yes | true no-mock HTTP | `tests/api/ModerationApiTest.php` | `testMergeReviewMerge` (`tests/api/ModerationApiTest.php:77-94`) |
| GET `/api/reviews/reviewers` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testListReviewers` (`tests/api/ReviewApiTest.php:110-135`) |
| POST `/api/reviews/reviewers` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testCreateReviewer` (`tests/api/ReviewApiTest.php:137-149`) |
| GET `/api/reviews/reviewers/:id/conflicts` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testGetConflicts` (`tests/api/ReviewApiTest.php:151-158`) |
| POST `/api/reviews/assignments/auto` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testFullReviewFlow` (`tests/api/ReviewApiTest.php:70-77`) |
| GET `/api/reviews/assignments` | no | none | - | no HTTP request found in `tests/api/*.php` |
| POST `/api/reviews/assignments` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testManualAssignment` (`tests/api/ReviewApiTest.php:160-184`) |
| GET `/api/reviews/scorecards` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testListScorecardsWithDimensions` (`tests/api/ReviewApiTest.php:186-197`) |
| POST `/api/reviews/scorecards` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testCreateScorecard` (`tests/api/ReviewApiTest.php:10-26`) |
| POST `/api/reviews/submissions/:id/publish` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testFullReviewFlow` (`tests/api/ReviewApiTest.php:104-108`) |
| POST `/api/reviews/submissions` | yes | true no-mock HTTP | `tests/api/ReviewApiTest.php` | `testFullReviewFlow` (`tests/api/ReviewApiTest.php:89-103`) |
| GET `/api/finance/payments` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testListPayments` (`tests/api/FinanceApiTest.php:10-20`) |
| GET `/api/finance/payments/:id` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testGetPayment` (`tests/api/FinanceApiTest.php:112-133`) |
| POST `/api/finance/payments/import` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testImportNoFile` (`tests/api/FinanceApiTest.php:163-169`) |
| GET `/api/finance/receipts` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testListReceipts` (`tests/api/FinanceApiTest.php:22-30`) |
| POST `/api/finance/receipts/callback` | yes | true no-mock HTTP | `tests/api/FinanceCallbackApiTest.php` | `testCallbackMissingSignatureReturns400` (`tests/api/FinanceCallbackApiTest.php:14-22`) |
| GET `/api/finance/receipts/:id/verify` | yes | true no-mock HTTP | `tests/api/FinanceCallbackApiTest.php` | `testVerifyReceiptReachableByFinance` (`tests/api/FinanceCallbackApiTest.php:35-49`) |
| PUT `/api/finance/receipts/:id/bind` | no | none | - | no HTTP request found in `tests/api/*.php` |
| GET `/api/finance/receipts/:id` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testGetReceipt` (`tests/api/FinanceApiTest.php:135-153`) |
| POST `/api/finance/reconciliation/run` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testRunReconciliation` (`tests/api/FinanceApiTest.php:32-48`) |
| GET `/api/finance/reconciliation/anomalies` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testAnomalies` (`tests/api/FinanceApiTest.php:50-57`) |
| GET `/api/finance/settlements` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testListSettlements` (`tests/api/FinanceApiTest.php:59-67`) |
| POST `/api/finance/settlements` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testCreateSettlement` (`tests/api/FinanceApiTest.php:77-91`) |
| GET `/api/finance/settlements/:id/report` | yes | true no-mock HTTP | `tests/api/FinanceApiTest.php` | `testSettlementReport` (`tests/api/FinanceApiTest.php:93-110`) |
| GET `/api/admin/risk/scores` | yes | true no-mock HTTP | `tests/api/RiskApiTest.php` | `testListScores` (`tests/api/RiskApiTest.php:10-17`) |
| GET `/api/admin/risk/ip-scores` | no | none | - | no HTTP request found in `tests/api/*.php` |
| GET `/api/admin/risk/flags` | yes | true no-mock HTTP | `tests/api/RiskApiTest.php` | `testListFlags` (`tests/api/RiskApiTest.php:19-26`) |
| PUT `/api/admin/risk/flags/:id/clear` | yes | true no-mock HTTP | `tests/api/RiskApiTest.php` | `testClearFlag` (`tests/api/RiskApiTest.php:73-91`) |
| GET `/api/admin/risk/throttles` | yes | true no-mock HTTP | `tests/api/RiskApiTest.php` | `testGetThrottles` (`tests/api/RiskApiTest.php:28-41`) |
| PUT `/api/admin/risk/throttles` | yes | true no-mock HTTP | `tests/api/RiskApiTest.php` | `testUpdateThrottles` (`tests/api/RiskApiTest.php:43-63`) |
| GET `/api/admin/audit/logs` | yes | true no-mock HTTP | `tests/api/DashboardApiTest.php` | `testAuditLogs` (`tests/api/DashboardApiTest.php:35-51`) |
| GET `/api/dashboard` | no | unit-only / indirect | `tests/integration/ControllerIntegrationTest.php` | `testDashboardIndex` calls controller directly (`tests/integration/ControllerIntegrationTest.php:140-149`) |
| GET `/api/admin/dashboard` | yes | true no-mock HTTP | `tests/api/DashboardApiTest.php` | `testDashboardCounters` (`tests/api/DashboardApiTest.php:10-33`) |

## API Test Classification

1. True No-Mock HTTP
- All `tests/api/*.php` classes extending `ApiTestCase`.
- Evidence: real curl transport + URL requests in `tests/ApiTestCase.php:13-106`.

2. HTTP with Mocking
- **None found**.

3. Non-HTTP (unit/integration without HTTP)
- `tests/unit/*.php` (pure logic tests).
- `tests/integration/*.php` service/controller/middleware in-process tests.
- Direct controller invocation (bypass HTTP router): `tests/integration/ControllerIntegrationTest.php:15-20` and many methods.

## Mock Detection
- No explicit mocking framework calls detected (`jest.mock`, `vi.mock`, `sinon.stub`, `Mockery`, PHPUnit mock builders): search returned no matches in `tests/` and `app/`.
- HTTP-layer bypass observed (not mocking, but not endpoint-level HTTP):
  - direct controller calls via `call_user_func_array` in `tests/integration/ControllerIntegrationTest.php:15-20`.
  - request object injection with `self::$app->instance('think\Request', $request)` in `tests/integration/ControllerIntegrationTest.php:29,41,63...`.

## Coverage Summary
- Total endpoints: **80**
- Endpoints with HTTP tests: **74**
- Endpoints with true no-mock HTTP tests: **74**
- HTTP coverage: **90.89%**
- True API coverage: **90.89%**
- Uncovered endpoints:
  - GET `/api/production/work-orders/:id/history`
  - PUT `/api/production/work-orders/:id/repair`
  - GET `/api/reviews/assignments`
  - PUT `/api/finance/receipts/:id/bind`
  - GET `/api/admin/risk/ip-scores`
  - GET `/api/dashboard`

## Unit Test Summary

### Backend Unit Tests
- Test files: `tests/unit/*.php` (12 files).
- Controllers covered:
  - Direct controller integration coverage exists (not unit): `tests/integration/ControllerIntegrationTest.php`.
- Services covered:
  - `AuthService`, `CatalogService`, `AppointmentService`, `ProductionService`, `FinanceService`, `ReviewService`, `ModerationService`, `RiskService`, `AuditService` (via unit/integration suites).
- Repositories covered:
  - No dedicated repository layer classes found; persistence tested via `Db::name(...)` in integration tests.
- Auth/guards/middleware covered:
  - `AuthMiddleware`, `ThrottleMiddleware` in `tests/integration/MiddlewareIntegrationTest.php:29-150`.
  - Step-up behavior covered at API level (`tests/api/StepUpHoldApiTest.php:19-50`).

Important backend modules not tested (or not directly endpoint-tested):
- `production.WorkOrder/history` route handler endpoint path (no HTTP test).
- `production.WorkOrder/repair` route handler endpoint path (no HTTP test).
- `review.Review/listAssignments` endpoint path (no HTTP test).
- `finance.Receipt/bind` endpoint path (no HTTP test).
- `admin.Risk/ipScores` endpoint path (no HTTP test).
- `admin.Dashboard/index` via `/api/dashboard` route path (no HTTP test for this alias).

### Frontend Unit Tests (STRICT)
- Frontend test files (`*.test.*` / `*.spec.*`): **NONE** (`find` returned empty).
- Frameworks/tools detected for frontend unit tests: **NONE**.
- Evidence of frontend test framework present only for E2E: Cypress (`package.json:5-11`, `cypress/e2e/*.cy.js`).
- Tests importing/rendering frontend components/modules: **NONE**.
- Important frontend modules/components not unit tested:
  - global frontend runtime helper: `public/static/js/app.js:5-105`.
  - page modules/templates under `view/` (e.g., `view/auth/login.html`, `view/dashboard/index.html`, `view/appointments/*.html`, `view/production/*.html`, `view/finance/*.html`, `view/catalog/products.html`).

**Frontend unit tests: MISSING**

**CRITICAL GAP**: project is fullstack, but no frontend unit-test suite is present.

### Cross-Layer Observation
- Testing is backend-heavy: strong API + backend unit/integration coverage, but zero frontend unit coverage.
- Fullstack balance is insufficient under strict criteria.

## API Observability Check
- Strength: Most API tests explicitly show method/path, request payload, and response assertions.
  - Example: `tests/api/AppointmentApiTest.php:33-70` (multi-step lifecycle with payload and response-field assertions).
- Weak spots:
  - Conditional assertions that may skip deep path checks if seed data absent (e.g., payment/receipt read tests): `tests/api/FinanceApiTest.php:121-131`, `:142-153`.

## Tests Check
- Success paths: broadly present across auth/appointments/production/catalog/review/finance/risk.
- Failure/validation paths: present (e.g., weak password, invalid role, missing fields, RBAC denial).
- Edge cases: present in key areas (state transitions, checksum/signature, lockout, reschedule windows).
- Auth/permissions: present across many role-protected endpoints.
- Integration boundaries: present via integration suites + API suites.
- Over-mocking risk: low (no explicit mocking libs detected).
- `run_tests.sh` check: **Docker-based (OK)** (`run_tests.sh:7,13`).

## End-to-End Expectations (Fullstack)
- Real FE↔BE E2E specs exist (`cypress/e2e/*.cy.js`), but they are optional and not part of primary gate (`package.json:5`, README notes optional Cypress at `README.md:23`).
- Missing frontend unit tests are only partially compensated by backend API coverage + optional E2E.

## Test Coverage Score (0-100)
- **90.89 / 100**

## Score Rationale
- + strong true no-mock HTTP API coverage (74/80).
- + broad backend unit/integration depth with meaningful assertions.
- - uncovered API endpoints in production/review/finance/risk/dashboard alias.
- - **critical** absence of frontend unit tests for a fullstack project.
- - some API tests rely on optional data presence for deep assertions.

## Key Gaps
1. No frontend unit tests (critical for fullstack).
2. 6 uncovered API endpoints (listed in Coverage Summary).
3. Optional-data branches reduce deterministic assertion depth for some finance read endpoints.

## Confidence & Assumptions
- Confidence: **high** for endpoint inventory and HTTP mapping (route + test files are explicit).
- Assumptions:
  - only `route/app.php` defines API endpoints for this repo.
  - static inspection cannot validate runtime route registration conditions.

---

# README Audit

## README Location
- Found at required location: `README.md`.

## Hard Gate Evaluation

### Formatting
- PASS: Markdown structure is readable and organized (`README.md:1-90`).

### Startup Instructions (fullstack/backend)
- PASS: includes `docker-compose up` command (`README.md:44` contains `docker-compose up --build -d`).

### Access Method
- PASS: URL + port clearly documented (`README.md:53-56`).

### Verification Method
- **FAIL**: no concrete "how to verify working system" flow (no curl/Postman API verification and no explicit UI verification checklist).
- Evidence: README ends at credentials; no verification section beyond run instructions (`README.md:63-90`).

### Environment Rules (Docker-contained, no runtime installs)
- PASS: no prohibited `npm install` / `pip install` / `apt-get` / manual DB setup instructions detected.
- Containerized run path documented (`README.md:39-61`, `run_tests.sh:7-13`).

### Demo Credentials (auth exists)
- PASS: username + password + role matrix provided and includes all operational roles (`README.md:79-90`).

## Engineering Quality
- Tech stack clarity: good (`README.md:5-10`).
- Architecture explanation: moderate (module list is clear, architectural flows are brief).
- Testing instructions: present but slightly inconsistent wording.
  - README says all tests (incl E2E) via `run_tests.sh` (`README.md:65`), while package metadata says Cypress is optional (`package.json:5`).
- Security/roles: good credential and RBAC role coverage (`README.md:77-90`).
- Workflow guidance: basic run/stop/testing present.
- Presentation quality: generally good, but lacks explicit verification and explicit project-type label token.

## High Priority Issues
1. Missing required verification method section (no explicit API/UI validation procedure after startup).

## Medium Priority Issues
1. Required top-of-README project-type declaration token is missing (`backend|fullstack|web|android|ios|desktop` not explicitly stated as such).
2. Testing wording inconsistency: README implies all tests via `run_tests.sh` (`README.md:65`), while Cypress is described as optional (`README.md:23`, `package.json:5`).

## Low Priority Issues
1. No explicit architecture interaction diagram/flow (optional quality improvement, not a hard gate).

## Hard Gate Failures
1. Verification Method: FAILED.

## README Verdict
- **FAIL**

## Final Verdicts
- Test Coverage Audit Verdict: **PARTIAL PASS with CRITICAL GAP** (frontend unit tests missing; 6 API endpoints uncovered).
- README Audit Verdict: **FAIL** (hard-gate verification method missing).
