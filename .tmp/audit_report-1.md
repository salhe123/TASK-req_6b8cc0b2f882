# Delivery Acceptance and Project Architecture Audit (Static-Only)

## 1. Verdict
- **Overall conclusion: Partial Pass**

The project is substantial and maps to many prompt requirements, but it contains multiple **material requirement-fit and security/professionalism gaps** (including several **High** issues) that prevent a full pass.

## 2. Scope and Static Verification Boundary
- **What was reviewed:** repository structure, docs/config, route/middleware/auth, controllers/services/models, SQL schema/migrations/seeds, frontend templates/assets, and test code.
- **What was not reviewed:** runtime behavior, browser interactions, DB/container startup behavior, actual cron execution, Docker networking, and real E2E execution.
- **What was intentionally not executed:** app startup, Docker, PHPUnit/Cypress, external services (per static-only boundary).
- **Claims requiring manual verification:**
  - Actual end-to-end runbook correctness in target offline environment.
  - Real session/cookie behavior across browsers/reverse proxies.
  - Actual immutability enforcement if deployed via migrations rather than `database/schema.sql`.
  - True test pass rates/coverage percentages claimed in README.

## 3. Repository / Requirement Mapping Summary
- **Prompt core goals mapped:** appointments lifecycle + evidence upload, production MPS/MRP/capacity, catalog normalization/dedup + moderation, review assignment/scorecards/conflict checks, finance import/reconciliation/settlement, RBAC/audit/risk throttling.
- **Main implementation areas reviewed:** `route/app.php`, `app/service/*`, `app/controller/*`, `database/schema.sql`, `view/*`, and `tests/*`.
- **Main constraints checked:** offline-oriented design, date/time formats, 2-hour reschedule rule with admin override reason, 10 MB evidence limit, 12-week planning window, scorecard validation, risk thresholds, transaction usage, append-only audit/history intent, and auth/RBAC boundaries.

## 4. Section-by-section Review

### 1. Hard Gates
#### 1.1 Documentation and static verifiability
- **Conclusion: Partial Pass**
- **Rationale:** README provides startup/config/testing instructions and role credentials, but has key static inconsistencies.
- **Evidence:**
  - Startup/run/test instructions present: `README.md:67`, `README.md:104`, `README.md:122`.
  - README references non-existent `api-spec.md`: `README.md:55`.
  - README says all unit/API/E2E run via `run_tests.sh`: `README.md:106`; script runs Docker test entry only: `run_tests.sh:7`, `run_tests.sh:13`; entry runs PHPUnit suites only (no Cypress): `docker/run_tests_entry.sh:78`, `docker/run_tests_entry.sh:101`.
  - README claims app "refuses to start" without keys: `README.md:72`; key enforcement is only called in encrypt/HMAC helpers, not startup bootstrap: `app/common.php:29`, `app/common.php:43`, `app/common.php:59`, `public/index.php:6`.
- **Manual verification note:** full runbook validity in a clean environment is **Manual Verification Required**.

#### 1.2 Material deviation from Prompt
- **Conclusion: Partial Pass**
- **Rationale:** Core domain areas exist, but several prompt-critical semantics are weakened.
- **Evidence:**
  - Catalog drafting/submission is limited to `CONTENT_MODERATOR`, collapsing submitter/moderator separation: `route/app.php:102`, `route/app.php:110`.
  - Reviewer pool “creation” does not persist specialties/pool membership (log + echo only): `app/service/ReviewService.php:24`, `app/service/ReviewService.php:34`, `app/service/ReviewService.php:38`.
  - Dedup (<0.85 confidence) path stores placeholder action `APPROVE` before moderator review: `app/service/CatalogService.php:337`, `app/service/CatalogService.php:342`.

### 2. Delivery Completeness
#### 2.1 Core explicit requirements coverage
- **Conclusion: Partial Pass**
- **Rationale:** Many explicit flows are implemented, but some prompt requirements are only partially satisfied.
- **Evidence (implemented examples):**
  - Appointment strict parse format: `app/service/AppointmentService.php:344`.
  - 2-hour reschedule restriction + admin override reason: `app/service/AppointmentService.php:68`, `app/service/AppointmentService.php:73`.
  - 24h auto-expire: `app/service/AppointmentService.php:206`.
  - Provider check-in/out + evidence gate + 10 MB limit: `app/service/AppointmentService.php:165`, `app/service/AppointmentService.php:180`, `app/service/AppointmentService.php:193`, `app/service/AppointmentService.php:266`.
  - 12-week capacity window + 90% warning: `app/service/ProductionService.php:299`, `app/service/ProductionService.php:316`.
  - Scorecard dimension count + weight=100 + narrative + 1..5 scores: `app/service/ReviewService.php:162`, `app/service/ReviewService.php:167`, `app/service/ReviewService.php:240`, `app/service/ReviewService.php:243`.
  - CSV checksum + signed callback + variance/duplicate anomalies: `app/service/FinanceService.php:24`, `app/service/FinanceService.php:31`, `app/service/FinanceService.php:481`, `app/service/FinanceService.php:275`, `app/service/FinanceService.php:295`.
- **Evidence (gaps):**
  - No durable reviewer-pool specialization model: `app/service/ReviewService.php:24`.
  - Catalog merge policy under 0.85 not truly moderator-gated: `app/service/CatalogService.php:337`, `app/service/CatalogService.php:342`.
  - “Operators report completion/rework/downtime” is implemented through planner-only endpoints (no operator role/workspace): role enum/routes show planner only for work order completion paths: `database/schema.sql:12`, `route/app.php:81`, `route/app.php:90`, `app/controller/production/WorkOrder.php:59`.
- **Manual verification note:** blind-review privacy effect is **Cannot Confirm Statistically** (flag exists, full anonymization behavior not proven end-to-end).

#### 2.2 End-to-end deliverable vs partial/demo
- **Conclusion: Pass**
- **Rationale:** Multi-module full-stack structure exists with controllers/services/models/routes/views/tests, not a toy snippet.
- **Evidence:**
  - Route surface across modules: `route/app.php:12`, `route/app.php:27`, `route/app.php:63`, `route/app.php:97`, `route/app.php:121`, `route/app.php:147`, `route/app.php:180`.
  - Domain services by module: `app/service/AppointmentService.php:12`, `app/service/ProductionService.php:12`, `app/service/CatalogService.php:11`, `app/service/ReviewService.php:9`, `app/service/FinanceService.php:9`, `app/service/RiskService.php:8`.
  - UI workspaces present: `view/appointments/index.html:1`, `view/provider/queue.html:1`, `view/production/mps.html:1`, `view/reviews/assignments.html:1`, `view/finance/reconciliation.html:1`, `view/admin/audit.html:1`.

### 3. Engineering and Architecture Quality
#### 3.1 Structure and module decomposition
- **Conclusion: Pass**
- **Rationale:** Reasonable decomposition by domain module with service layer and routing boundaries.
- **Evidence:**
  - Clear moduleized controllers: `app/controller/*` (e.g., `app/controller/finance/Settlement.php:1`, `app/controller/review/Review.php:1`).
  - Core logic primarily in services: `app/service/*.php`.

#### 3.2 Maintainability/extensibility
- **Conclusion: Partial Pass**
- **Rationale:** Generally maintainable, but there are hard-coded/placeholder patterns that limit correctness and extension.
- **Evidence:**
  - Explicit placeholder in moderation decision pipeline: `app/service/CatalogService.php:342`.
  - Reviewer pool comment indicates temporary handling: `app/service/ReviewService.php:24`.
  - MRP explosion marked simplified with fixed batch logic: `app/service/ProductionService.php:97`, `app/service/ProductionService.php:99`.

### 4. Engineering Details and Professionalism
#### 4.1 Error handling, logging, validation, API design
- **Conclusion: Partial Pass**
- **Rationale:** Strong baseline exists (status codes, audit logging, validation), but some high-impact gaps remain.
- **Evidence (strengths):**
  - Consistent JSON responses: `app/common.php:8`, `app/common.php:20`.
  - Central exception mapping: `app/ExceptionHandle.php:30`, `app/ExceptionHandle.php:38`.
  - Broad audit logging usage: `app/service/AuditService.php:27`.
  - Many transaction boundaries for critical flows: `app/service/AppointmentService.php:21`, `app/service/FinanceService.php:330`, `app/service/ReviewService.php:173`.
- **Evidence (gaps):**
  - Session token exposed in login response and no explicit session ID regeneration on login: `app/service/AuthService.php:86`, `app/service/AuthService.php:99`.
  - `expirePending()` updates appointment + history + audit without transaction per item: `app/service/AppointmentService.php:217`, `app/service/AppointmentService.php:221`, `app/service/AppointmentService.php:222`.

#### 4.2 Product-like vs demo-like
- **Conclusion: Partial Pass**
- **Rationale:** Product shape is present, but some required controls appear unfinished/placeholder.
- **Evidence:**
  - Product-like breadth exists (RBAC, risk, moderation, reconciliation).
  - Placeholder/draft behavior in dedup moderation and reviewer pool persistence: `app/service/CatalogService.php:342`, `app/service/ReviewService.php:24`.

### 5. Prompt Understanding and Requirement Fit
#### 5.1 Business-goal and constraints fit
- **Conclusion: Partial Pass**
- **Rationale:** Strong alignment overall, but key governance/flow semantics are weakened.
- **Evidence:**
  - Strong fits: appointment lifecycle/state + repair endpoint: `route/app.php:40`, `route/app.php:46`, `app/service/AppointmentService.php:130`.
  - Governance mismatch for catalog specialist vs moderator separation: `route/app.php:102`, `route/app.php:110`.
  - Reviewer pool curation not materially implemented: `app/service/ReviewService.php:14`, `app/service/ReviewService.php:38`.
  - Dedup low-confidence path not enforced as moderator approval workflow: `app/service/CatalogService.php:337`, `app/service/CatalogService.php:342`.

### 6. Aesthetics (Frontend)
#### 6.1 Visual and interaction quality
- **Conclusion: Pass**
- **Rationale:** UI has clear layout hierarchy, role-grouped navigation, status colors, and action feedback.
- **Evidence:**
  - Role-distinct navigation/workspaces: `view/layout/base.html:30`, `view/layout/base.html:47`, `view/layout/base.html:57`, `view/layout/base.html:66`, `view/layout/base.html:75`, `view/layout/base.html:86`.
  - Appointment status feedback on actions: `view/appointments/index.html:57`, `view/appointments/index.html:65`, `view/appointments/index.html:74`.
  - Provider queue check-in/out upload feedback: `view/provider/queue.html:23`, `view/provider/queue.html:29`, `view/provider/queue.html:53`.
- **Manual verification note:** responsive behavior and cross-browser rendering are **Manual Verification Required**.

## 5. Issues / Suggestions (Severity-Rated)

### Blocker / High
1. **Severity: High**  
   **Title:** Catalog low-confidence dedup flow is not truly moderator-gated  
   **Conclusion:** Fail  
   **Evidence:** `app/service/CatalogService.php:337`, `app/service/CatalogService.php:342`, `app/service/CatalogService.php:343`  
   **Impact:** Prompt requires merge policy needing moderator approval when confidence < 0.85; current code stores placeholder `APPROVE` and uses submitter as `moderator_id`, weakening governance integrity.  
   **Minimum actionable fix:** Persist a neutral `PENDING_REVIEW` merge candidate record and require explicit moderator action endpoint to finalize.

2. **Severity: High**  
   **Title:** Reviewer pool curation is largely non-persistent  
   **Conclusion:** Fail  
   **Evidence:** `app/service/ReviewService.php:24`, `app/service/ReviewService.php:34`, `app/service/ReviewService.php:38`  
   **Impact:** “Review Managers curate reviewer pool” is not materially implemented; specialties are not stored for future assignment/selection policy.  
   **Minimum actionable fix:** Add persistent reviewer-profile table (or extend user profile) for specialties/availability and update assignment logic to use it.

3. **Severity: High**  
   **Title:** Login flow exposes session identifier and lacks explicit session-ID rotation  
   **Conclusion:** Fail  
   **Evidence:** `app/service/AuthService.php:86`, `app/service/AuthService.php:99`  
   **Impact:** Increases session fixation/hijack risk in an authenticated system handling finance/admin actions.  
   **Minimum actionable fix:** Regenerate session ID on successful login and stop returning raw session ID as API token.

4. **Severity: High**  
   **Title:** Documentation overstates automated test scope (E2E claim mismatch)  
   **Conclusion:** Fail  
   **Evidence:** `README.md:106`, `run_tests.sh:13`, `docker/run_tests_entry.sh:78`, `docker/run_tests_entry.sh:101`  
   **Impact:** Delivery acceptance and quality signal are unreliable; reviewers may assume browser E2E coverage that is not executed by documented command.  
   **Minimum actionable fix:** Either run Cypress in test pipeline or update README to state only PHPUnit suites are run.

5. **Severity: High**  
   **Title:** Separation of duties for catalog drafting vs moderation is collapsed  
   **Conclusion:** Fail  
   **Evidence:** `route/app.php:102`, `route/app.php:110`, `route/app.php:115`  
   **Impact:** Prompt flow says entries are drafted/submitted then routed to Moderators; same role currently performs drafting/submission and moderation, weakening review controls.  
   **Minimum actionable fix:** Introduce separate role/permissions for catalog authors and keep moderation actions moderator-only.

### Medium
6. **Severity: Medium**  
   **Title:** `README` references missing artifact (`api-spec.md`)  
   **Conclusion:** Fail  
   **Evidence:** `README.md:55`  
   **Impact:** Static verification friction; reviewer cannot inspect claimed API spec.  
   **Minimum actionable fix:** Add `api-spec.md` or remove/update reference.

7. **Severity: Medium**  
   **Title:** Key startup enforcement claim is stronger than code behavior  
   **Conclusion:** Partial Fail  
   **Evidence:** `README.md:72`, `app/common.php:29`, `public/index.php:6`  
   **Impact:** Operators may assume boot-time fail-fast for secrets, but enforcement is deferred until crypto helpers are used.  
   **Minimum actionable fix:** Add explicit bootstrap validation for required keys during app initialization.

8. **Severity: Medium**  
   **Title:** Appointment auto-expire path is not transaction-protected per item  
   **Conclusion:** Partial Fail  
   **Evidence:** `app/service/AppointmentService.php:217`, `app/service/AppointmentService.php:221`, `app/service/AppointmentService.php:222`  
   **Impact:** A partial failure could mutate status without matching history/audit row, weakening strict traceability guarantees.  
   **Minimum actionable fix:** Wrap each expire mutation + history + audit write in a transaction.

9. **Severity: Medium**  
   **Title:** Operator-specific production role/workspace is not explicit  
   **Conclusion:** Partial Fail  
   **Evidence:** `database/schema.sql:12`, `route/app.php:90`, `app/controller/production/WorkOrder.php:59`  
   **Impact:** Prompt explicitly states operators report completion/rework/downtime; implementation routes these actions under planner-only role.  
   **Minimum actionable fix:** Add operator role and RBAC-scoped endpoints/UI for reporting.

### Low
10. **Severity: Low**  
    **Title:** Cypress assets exist without project-level Node manifest  
    **Conclusion:** Partial Fail  
    **Evidence:** `cypress.config.js:1`  
    **Impact:** E2E reproducibility is unclear from repository alone.  
    **Minimum actionable fix:** Add `package.json` with Cypress scripts or document external test-runner packaging.

## 6. Security Review Summary

- **Authentication entry points:** **Partial Pass**  
  - Evidence: `/api/auth/login` etc. `route/app.php:12`, login logic `app/service/AuthService.php:41`.  
  - Reasoning: Username/password + lockout + idle timeout exist, but session token handling is weak (`app/service/AuthService.php:99`).

- **Route-level authorization:** **Pass**  
  - Evidence: role-guarded routes across modules `route/app.php:25`, `route/app.php:90`, `route/app.php:119`, `route/app.php:155`, `route/app.php:188`, `route/app.php:193`.  
  - Reasoning: Broad RBAC coverage is present and admin bypass is explicit `app/middleware/AuthMiddleware.php:35`.

- **Object-level authorization:** **Partial Pass**  
  - Evidence: appointment ownership guard `app/controller/appointment/Appointment.php:18`; review submission owner check `app/service/ReviewService.php:220`; reviewer-only conflicts self-check `app/controller/review/Review.php:76`; reviewer assignment list scoping `app/controller/review/Review.php:40`.  
  - Reasoning: Strong in several critical paths, but not uniformly demonstrated for every resource type.

- **Function-level authorization:** **Partial Pass**  
  - Evidence: repair/admin checks `app/controller/appointment/Appointment.php:194`, `app/controller/production/WorkOrder.php:100`; step-up hold for selected sensitive endpoints `route/app.php:35`, `route/app.php:154`, `route/app.php:176`.  
  - Reasoning: Present but selective; sensitive-scope choices are reasonable yet not fully comprehensive.

- **Tenant / user data isolation:** **Cannot Confirm Statistically**  
  - Evidence: single-tenant schema and role model (`database/schema.sql:8` onward) with per-user filters in some endpoints (`app/controller/appointment/Appointment.php:50`, `app/controller/review/Review.php:40`).  
  - Reasoning: No explicit multi-tenant model to evaluate.

- **Admin / internal / debug endpoint protection:** **Pass**  
  - Evidence: admin route groups protected by `SYSTEM_ADMIN` `route/app.php:25`, `route/app.php:188`, `route/app.php:193`, `route/app.php:203`.  
  - Reasoning: No unguarded debug/admin endpoints found in reviewed route file.

## 7. Tests and Logging Review

- **Unit tests:** **Partial Pass**  
  - Evidence: suites configured `phpunit.xml:8`; unit files present `tests/unit/*.php`; several are logic-mirroring rather than directly invoking production services (e.g., `tests/unit/ScorecardValidationTest.php:10`, `tests/unit/MiddlewareTest.php:18`).

- **API / integration tests:** **Partial Pass**  
  - Evidence: API suite configured `phpunit.xml:14`; real HTTP test harness exists `tests/ApiTestCase.php:10`, `tests/ApiTestCase.php:21`; integration suite exists `phpunit.xml:11`.
  - Gap: README overstates E2E inclusion in default run path (`README.md:106`, `docker/run_tests_entry.sh:101`).

- **Logging categories / observability:** **Pass**  
  - Evidence: append-focused audit log service `app/service/AuditService.php:14`; admin log query endpoint `app/controller/admin/Audit.php:11`; schema append-only trigger intent `database/schema.sql:462`.

- **Sensitive-data leakage risk in logs / responses:** **Partial Pass**  
  - Evidence: appointment audit scrubs location plaintext/ciphertext `app/model/Appointment.php:57`; password hidden in model `app/model/User.php:12`; login returns session id token `app/service/AuthService.php:99`.
  - Reasoning: broad hygiene exists, but token exposure is a meaningful risk.

## 8. Test Coverage Assessment (Static Audit)

### 8.1 Test Overview
- **Unit tests exist:** Yes (`tests/unit`, configured in `phpunit.xml:8`).
- **Integration tests exist:** Yes (`tests/integration`, configured in `phpunit.xml:11`).
- **API tests exist:** Yes (`tests/api`, configured in `phpunit.xml:14`; real HTTP harness `tests/ApiTestCase.php:10`).
- **E2E tests exist in repo:** Cypress specs exist (`cypress/e2e/*.cy.js`, config `cypress.config.js:1`) but are **not part of documented default run script** (`docker/run_tests_entry.sh:101`).
- **Documented test commands:** present in README (`README.md:108`), but scope statement is inconsistent with script behavior (`README.md:106` vs `docker/run_tests_entry.sh:101`).

### 8.2 Coverage Mapping Table

| Requirement / Risk Point | Mapped Test Case(s) | Key Assertion / Fixture / Mock | Coverage Assessment | Gap | Minimum Test Addition |
|---|---|---|---|---|---|
| Appointment lifecycle state machine | `tests/api/AppointmentApiTest.php:29`; `tests/unit/AppointmentStateMachineTest.php:19` | Full flow create→confirm→check-in→upload→check-out `tests/api/AppointmentApiTest.php:55` | sufficient | None major | Add explicit negative transition API tests for each forbidden edge |
| Appointment date/time format strictness | `tests/unit/DateTimeParsingTest.php:14`; `tests/unit/DateTimeParsingTest.php:40` | Public parser rejects `Y-m-d H:i:s` `tests/unit/DateTimeParsingTest.php:42` | basically covered | Limited API-level bad-format cases | Add API tests for malformed `dateTime` on create/reschedule |
| 2-hour reschedule restriction + admin override reason | `tests/api/AppointmentApiTest.php:250`; `tests/api/AppointmentApiTest.php:148`; `tests/integration/AppointmentServiceIntegrationTest.php:165` | 409 within 2 hours; 400 when admin override lacks reason | sufficient | None major | Add exact boundary test at exactly +2h |
| Provider evidence required before check-out | `tests/api/AppointmentApiTest.php:113`; `tests/integration/AppointmentServiceIntegrationTest.php:79` | 409 without evidence then success after upload | sufficient | No file-type/size negative API tests | Add API tests for >10MB and unsupported MIME |
| RBAC 401/403 core | `tests/api/AuthApiTest.php:89`; `tests/api/AppointmentApiTest.php:136`; `tests/api/ProductionApiTest.php:167`; `tests/api/UserApiTest.php:113` | Unauthenticated 401 and wrong-role 403 assertions | sufficient | Object-level negative coverage still thinner | Add cross-owner resource access tests |
| Object-level auth for review submission | `tests/integration/ReviewServiceIntegrationTest.php:116` | Non-assigned reviewer submit throws 403/HttpException path | basically covered | API-level object-ownership negative tests limited | Add API test where reviewer fetches/submits another reviewer’s assignment |
| CSV checksum validation | `tests/integration/SecurityCoverageTest.php:85`; `tests/integration/SecurityCoverageTest.php:98` | Missing/mismatch checksum throws `HttpException` | sufficient | No API multipart checksum mismatch test | Add API test on `/api/finance/payments/import` with bad checksum |
| Callback signature/amount validation | `tests/integration/SecurityCoverageTest.php:126`; `tests/integration/SecurityCoverageTest.php:147`; `tests/integration/SecurityCoverageTest.php:169` | Rejects bad sig and amount mismatch, accepts valid signature | sufficient | No replay-idempotency assertion | Add replay callback test ensuring duplicate handling policy |
| Reconciliation anomaly thresholds | `tests/integration/FinanceServiceIntegrationTest.php:160`; `tests/integration/FinanceServiceIntegrationTest.php:174`; `tests/unit/FinanceReconciliationTest.php:27` | Variance and duplicate fingerprint assertions | basically covered | Alert persistence behavior not deeply asserted | Add assertions on anomaly flag payload content and dedup semantics |
| Conflict-of-interest enforcement | `tests/integration/ReviewServiceIntegrationTest.php:76`; `tests/integration/ReviewServiceIntegrationTest.php:60` | Matching vendor conflict blocks, unrelated vendor does not | sufficient | API-level conflict block test absent | Add API test for manual assignment conflict 409 |
| Append-only history/audit immutability | `tests/integration/SecurityCoverageTest.php:23`; `tests/integration/SecurityCoverageTest.php:41` | Direct update/delete on history expects exception | basically covered | Audit-log append-only trigger not similarly exercised in tests | Add integration tests for `pp_audit_logs` update/delete rejection |
| Throttling and step-up hold | `tests/integration/MiddlewareIntegrationTest.php:118`; `tests/integration/SecurityCoverageTest.php:190` | 429 on over-limit and STEP_UP_HOLD creation | basically covered | End-to-end blocked endpoint test for STEP_UP not explicit | Add API tests ensuring held accounts receive 423 on guarded routes |

### 8.3 Security Coverage Audit
- **Authentication tests:** **basically covered** (`tests/api/AuthApiTest.php:11`, `tests/integration/AuthServiceIntegrationTest.php:25`).
- **Route authorization tests:** **basically covered** across modules (`tests/api/UserApiTest.php:113`, `tests/api/FinanceApiTest.php:69`, `tests/api/ProductionApiTest.php:167`).
- **Object-level authorization tests:** **insufficient** for complete assurance; some strong coverage exists (`tests/integration/ReviewServiceIntegrationTest.php:116`, appointment owner checks exercised indirectly), but cross-owner API negatives are not comprehensive.
- **Tenant / data isolation tests:** **not applicable / cannot confirm** (no tenant model).
- **Admin/internal protection tests:** **basically covered** (`tests/api/DashboardApiTest.php:65`, `tests/api/RiskApiTest.php:65`).

### 8.4 Final Coverage Judgment
- **Final Coverage Judgment: Partial Pass**

Major flows and many critical constraints are tested, but uncovered/under-covered security and governance risks remain (notably session-token handling, catalog moderation gating semantics, and incomplete object-level negative-path coverage). Severe defects could still remain undetected while tests pass.

## 9. Final Notes
- This audit is static-only; runtime correctness claims are intentionally limited.
- The delivery is substantial and close in many areas, but High-severity governance/security/documentation mismatches should be resolved before acceptance as a production-grade handoff.
