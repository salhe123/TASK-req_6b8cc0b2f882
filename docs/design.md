# Precision Hardware Manufacturing & Service Operations Portal - System Design Document

## 1. Overview

The Precision Hardware Manufacturing & Service Operations Portal is an offline-capable full-stack platform built with ThinkPHP, Layui, and MySQL for a U.S.-based electronics manufacturer. It unifies production planning, product specification management, expert reviews, service appointment scheduling, and financial settlement into a single system.

The portal supports seven roles: Production Planner, Service Coordinator, Provider/Technician, Reviewer, Content Moderator, Finance Clerk, and System Administrator. It is designed for local deployment with strict auditability, immutable state history, and transactional integrity across all operations.

---

## 2. Architecture

### 2.1 Technology Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML, CSS, JavaScript, Layui |
| Backend | ThinkPHP (PHP) |
| Database | MySQL |
| Security | Bcrypt hashing, AES encryption at rest, RBAC |
| Deployment | Offline-capable local deployment |
| File Storage | Local filesystem (photos, PDFs, CSV imports) |

### 2.2 High-Level Architecture

```text
Role-Based Clients (Planner / Coordinator / Provider / Reviewer / Moderator / Finance / Admin)
        |
        | Layui AJAX + REST-style API calls
        v
Authentication + RBAC + Throttle Middleware
        |
        v
ThinkPHP Controller Layer
        |
        v
Service Layer (State Machines / Business Rules / Validation / Auditing)
        |
        v
Data Access Layer (ThinkPHP ORM)
        |
        v
MySQL Database + Local File Storage
```

### 2.3 Module Structure

- `auth` - authentication, password complexity, session management, idle timeout
- `appointment` - appointment lifecycle, state machine, auto-expire, repair operations
- `production` - MPS (12-week), MRP work orders, capacity loading, reason codes
- `catalog` - hardware product entries, completeness scoring, standardization
- `moderation` - bulk approval/rejection, deduplication merge review
- `review` - reviewer pool, conflict-of-interest, scorecards, blind review
- `finance` - payments, CSV import, receipts, reconciliation, settlements, commissions
- `risk` - credit scoring, anomaly detection, throttling, device fingerprint
- `admin` - user management, RBAC, system configuration, audit logs
- `common` - utilities, validation, file upload handling, error responses

---

## 3. Security Model

### 3.1 Authentication

- Local username/password authentication only
- Minimum 10 characters with complexity rules
- Bcrypt adaptive password hashing
- 15-minute idle session timeout
- Append-only operation logs

### 3.2 Roles and Permissions

| Role | Description |
|------|-------------|
| PRODUCTION_PLANNER | Manage MPS, work orders, capacity monitoring |
| SERVICE_COORDINATOR | Create and manage customer appointments |
| PROVIDER | Daily queue, check-in/out, upload completion evidence |
| REVIEWER | Submit scored reviews via configurable scorecards |
| CONTENT_MODERATOR | Approve/reject catalog entries, review dedup merges |
| FINANCE_CLERK | Record payments, import CSVs, run settlements, generate reports |
| SYSTEM_ADMIN | Full system control, user management, repair operations, risk config |

### 3.3 Data Protection

- Passwords hashed with bcrypt
- Bank last-4 and addresses encrypted at rest (AES)
- Sensitive fields masked in UI
- Operation logs are append-only and immutable
- File uploads limited to 10 MB each (photos, PDFs)

---

## 4. Core Modules

### 4.1 Appointment Module

- Create appointments with date/time (MM/DD/YYYY, 12-hour) and location
- State machine: PENDING → CONFIRMED → IN_PROGRESS → COMPLETED
- Auto-expire PENDING after 24 hours
- Reschedule blocked within 2 hours of start (Admin override with reason)
- Cancel allowed from PENDING or CONFIRMED
- Immutable history; reversal only via Admin "repair" with audit trail

### 4.2 Production Planning Module

- Rolling 12-week Master Production Schedule (MPS)
- Demand explosion into work orders (MRP)
- Operators report completion, rework, and downtime with reason codes
- Capacity loading warnings when work center exceeds 90% planned hours

### 4.3 Product Catalog Module

- Draft and submit hardware catalog entries (CPU/GPU/motherboard)
- Automatic completeness and consistency scoring
- Unit and naming standardization (GHz, MB/GB, PCIe versions)
- Parameter extraction via local regex + dictionary-based parser
- Deduplication via similarity fingerprints (0.85 threshold)
- Routed to Moderators for bulk approval/rejection

### 4.4 Review Module

- Reviewer pool management with conflict-of-interest recusal (12-month window)
- Automatic or manual assignment with blind review support
- Configurable scorecards: 3-7 dimensions, weights totaling 100%, 1-5 rating
- Required narrative fields before publication

### 4.5 Finance Module

- Record payments from local POS or bank batch CSV files (checksum validated)
- Signed internal receipts via local HMAC key
- Reconciliation: flag mismatches, $50 daily variance alerts, duplicate fingerprint detection
- Weekly settlement cycles with 8% default platform fee
- Commission splits in single atomic transaction
- Printable fund flow reports

### 4.6 Risk & Credit Module

- Nightly user/provider score from success rate, dispute rate, cancellation patterns
- Abnormal behavior flags: >20 postings/day, >5 cancellations/week
- Multi-accounting detection via device fingerprint + IP risk scoring
- Configurable throttles: 60 req/min per account, 10 appointments/hour
- Step-up review holds for flagged accounts

---

## 5. Data Model

- `users` - authentication, roles (RBAC), status
- `appointments` - lifecycle states, timestamps, location, assigned provider
- `appointment_history` - immutable state transition log
- `appointment_attachments` - uploaded photos/PDFs for completion evidence
- `mps_plans` - 12-week master production schedule entries
- `work_orders` - MRP-generated orders with status and reason codes
- `work_centers` - capacity definitions and planned hours
- `products` - hardware catalog entries with structured parameters
- `product_scores` - completeness and consistency scores
- `moderation_decisions` - approval/rejection records
- `reviewer_vendor_history` - conflict-of-interest tracking
- `review_assignments` - reviewer-to-product mappings
- `review_versions` - scorecard submissions with ratings and narratives
- `payments` - recorded transactions from CSV imports
- `receipts` - signed internal receipt records
- `settlements` - weekly batch settlement records
- `ledger_entries` - commission splits and fund flows
- `risk_scores` - nightly computed user/provider scores
- `anomaly_flags` - detected abnormal behavior records
- `audit_logs` - append-only operation log
- `device_fingerprints` - trusted device tracking

---

## 6. Business Rules Engine

### Appointment Rules

- Strict state machine with immutable history
- Auto-expire: PENDING → EXPIRED after 24 hours (cron/scheduled task)
- Reschedule window: blocked within 2 hours unless Admin override
- Repair operations: Admin-only, fully logged, reversible

### Production Rules

- MPS covers rolling 12 weeks
- Capacity warning at 90% threshold per work center
- Reason codes required for rework and downtime reporting

### Catalog Rules

- Standardize units: GHz, MB/GB, PCIe versions
- Deduplication: auto-merge at >= 0.85 confidence, Moderator review below
- All merge decisions logged with before/after snapshots

### Finance Rules

- CSV import with checksum validation
- Reconciliation flags daily variance > $50.00
- Duplicate receipt fingerprints raise anomaly alerts
- Settlement postings + commission splits in single transaction (all-or-nothing)

### Risk Rules

- Independent threshold evaluation per rule
- Throttles enforced at middleware level
- Flagged accounts held for Admin review

---

## 7. Error Handling

| Code | Description |
|------|-------------|
| 400 | Validation error |
| 401 | Unauthorized / session expired |
| 403 | Access denied (insufficient role) |
| 404 | Resource not found |
| 409 | State machine violation / business rule conflict |
| 423 | Resource locked / repair required |
| 429 | Throttle limit exceeded |
| 500 | Internal system error |

---

## 8. Deployment

- Offline-capable local deployment
- MySQL as local database
- No external API dependencies
- Local filesystem for file uploads and CSV imports
- Scheduled tasks for auto-expire and nightly risk scoring

---

## 9. Testing Strategy

- Unit tests for service layer and state machines
- API integration tests for all endpoints
- Appointment lifecycle and auto-expire tests
- Production capacity threshold tests
- Review conflict-of-interest validation tests
- Finance reconciliation and settlement atomicity tests
- Risk scoring and throttle enforcement tests
- File upload size and type validation tests
