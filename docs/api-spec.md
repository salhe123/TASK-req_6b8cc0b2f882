# Precision Hardware Manufacturing & Service Operations Portal - API Specification

## Authentication (`/api/auth`)

### POST `/login`
Request:
{
  "username": "admin",
  "password": "SecurePass123!"
}

Response:
{
  "token": "session-token",
  "expiresIn": 900,
  "role": "SYSTEM_ADMIN"
}

Errors:
- 401 Invalid credentials
- 423 Account locked

---

### POST `/logout`
Response:
{
  "message": "Logged out"
}

---

### POST `/change-password`
Request:
{
  "oldPassword": "OldPass1234!",
  "newPassword": "NewPass5678!"
}

---

## Users (`/api/admin/users`)

### POST `/`
Create user:
{
  "username": "planner1",
  "password": "TempPass123!",
  "role": "PRODUCTION_PLANNER"
}

---

### GET `/`
Filters:
- role
- status
- page
- size

---

### PUT `/{id}`
Update user role or status

---

### PUT `/{id}/lock`
### PUT `/{id}/unlock`

---

## Appointments (`/api/appointments`)

### POST `/`
{
  "customerId": 1,
  "dateTime": "04/17/2026 02:30 PM",
  "location": "Building A, Floor 2",
  "providerId": 5
}

---

### GET `/`
Filters:
- status (PENDING, CONFIRMED, IN_PROGRESS, COMPLETED, EXPIRED, CANCELLED)
- providerId
- dateFrom
- dateTo
- page, size

---

### GET `/{id}`

---

### GET `/{id}/history`
Returns immutable state transition timeline

---

### PUT `/{id}/confirm`

---

### PUT `/{id}/reschedule`
{
  "newDateTime": "04/18/2026 10:00 AM"
}

Errors:
- 409 Cannot reschedule within 2 hours of start time

---

### PUT `/{id}/cancel`
{
  "reason": "Customer requested"
}

---

### PUT `/{id}/repair`
Admin-only reversal:
{
  "targetState": "CONFIRMED",
  "reason": "Incorrect cancellation"
}

---

### PUT `/{id}/check-in`
Provider checks in to start job

---

### PUT `/{id}/check-out`
Provider checks out to complete job

---

### POST `/{id}/attachments`
Upload photos or PDFs (max 10 MB each)

---

### GET `/{id}/attachments`

---

## Provider Queue (`/api/provider/queue`)

### GET `/`
Returns daily appointment queue for the authenticated provider
Filters:
- date

---

## Production - Master Schedule (`/api/production/mps`)

### POST `/`
{
  "productId": 1,
  "weekStart": "04/20/2026",
  "quantity": 500
}

---

### GET `/`
Returns rolling 12-week schedule
Filters:
- productId
- weekStart
- weekEnd

---

### PUT `/{id}`

---

### DELETE `/{id}`

---

## Production - Work Orders (`/api/production/work-orders`)

### POST `/explode`
Explode MPS demand into work orders:
{
  "mpsId": 1
}

Response:
{
  "workOrdersCreated": 5
}

---

### GET `/`
Filters:
- status
- workCenterId
- dateRange
- page, size

---

### GET `/{id}`

---

### PUT `/{id}/start`
Transitions a work order from PENDING to IN_PROGRESS.
Errors:
- 409 Cannot start work order in state COMPLETED

---

### GET `/{id}/history`
Returns immutable work-order state transition timeline.

---

### PUT `/{id}/complete`
{
  "quantityCompleted": 480,
  "quantityRework": 15,
  "downtimeMinutes": 30,
  "reasonCode": "MATERIAL_DELAY"
}
Errors:
- 409 Cannot transition work order from COMPLETED to COMPLETED

---

## Production - Capacity (`/api/production/capacity`)

### GET `/`
Returns capacity loading per work center
Filters:
- workCenterId
- weekStart

Response:
{
  "workCenterId": 1,
  "plannedHours": 38,
  "capacityHours": 40,
  "loadPercent": 95,
  "warning": true
}

---

## Product Catalog (`/api/catalog/products`)

### POST `/`
{
  "name": "Intel Core i9-13900K",
  "category": "CPU",
  "specs": {
    "clockSpeed": "3.0 GHz",
    "cores": 24,
    "socket": "LGA 1700"
  }
}

---

### GET `/`
Filters:
- category (CPU, GPU, MOTHERBOARD)
- status (DRAFT, SUBMITTED, APPROVED, REJECTED)
- keyword
- page, size

---

### GET `/{id}`

---

### PUT `/{id}`

---

### POST `/{id}/submit`
Triggers completeness/consistency scoring and routes to moderation

Response:
{
  "completenessScore": 0.92,
  "consistencyScore": 0.88,
  "status": "SUBMITTED"
}

---

### GET `/duplicates`
Returns similarity-matched pairs above threshold

---

## Moderation (`/api/moderation`)

### GET `/pending`
Filters:
- type (PRODUCT, MERGE)
- page, size

---

### POST `/bulk-action`
{
  "ids": [1, 2, 3],
  "action": "APPROVE"
}

---

### POST `/merge-review`
{
  "productIdA": 1,
  "productIdB": 2,
  "action": "MERGE",
  "keepId": 1
}

---

## Reviews (`/api/reviews`)

### GET `/reviewers`
List reviewer pool

---

### POST `/reviewers`
{
  "userId": 10,
  "specialties": ["CPU", "GPU"]
}

---

### GET `/reviewers/{id}/conflicts`
Returns vendors with active conflict-of-interest (12-month window)

---

### POST `/assignments`
{
  "productId": 1,
  "reviewerId": 10,
  "blind": true
}

Errors:
- 409 Conflict of interest detected

---

### POST `/assignments/auto`
{
  "productId": 1,
  "blind": true
}

Auto-assigns eligible reviewer, skipping conflicted ones

---

### GET `/scorecards`

---

### POST `/scorecards`
{
  "name": "Hardware Review v2",
  "dimensions": [
    { "name": "Build Quality", "weight": 30 },
    { "name": "Performance", "weight": 25 },
    { "name": "Value", "weight": 20 },
    { "name": "Documentation", "weight": 25 }
  ]
}

Validation:
- 3-7 dimensions
- Weights must total 100%
- Rating levels: 1-5

---

### POST `/submissions`
{
  "assignmentId": 1,
  "scorecardId": 1,
  "ratings": [
    { "dimensionId": 1, "score": 4, "narrative": "Solid build..." }
  ]
}

Errors:
- 400 Narrative required for each dimension

---

### POST `/submissions/{id}/publish`

---

## Finance - Payments (`/api/finance/payments`)

### POST `/import`
Upload CSV batch file with checksum validation

Response:
{
  "imported": 45,
  "skipped": 2,
  "checksumValid": true
}

---

### GET `/`
Filters:
- dateFrom
- dateTo
- status
- page, size

---

### GET `/{id}`

---

## Finance - Receipts (`/api/finance/receipts`)

### GET `/`
Filters:
- paymentId
- dateRange

---

### GET `/{id}`
Returns signed internal receipt. Includes `signatureValid` boolean from HMAC re-verification.

---

### GET `/{id}/verify`
Re-verifies the HMAC signature for a single receipt on demand.
Returns 200 when valid, 409 when invalid.

---

### POST `/callback`
Inbound bank callback. Payload:
{
  "receiptNumber": "RCP-20260416-000001",
  "amount": 150.00,
  "issuedAt": "2026-04-16 10:00:00",
  "signature": "<hex HMAC-SHA256>"
}

Responses:
- 200: Callback verified
- 400: Signature mismatch or missing fields
- 404: Unknown receipt
- 409: Amount does not match receipt

---

## Finance - Reconciliation (`/api/finance/reconciliation`)

### POST `/run`
{
  "dateFrom": "04/01/2026",
  "dateTo": "04/15/2026"
}

Response:
{
  "matched": 120,
  "mismatches": 3,
  "duplicateFingerprints": 1,
  "varianceAlerts": 0
}

---

### GET `/anomalies`
Returns anomaly alerts (>$50 daily variance, duplicate fingerprints)

---

## Finance - Settlements (`/api/finance/settlements`)

### POST `/`
{
  "weekEnding": "04/19/2026",
  "platformFeePercent": 8
}

Response:
{
  "totalSettled": 15000.00,
  "platformFee": 1200.00,
  "providerPayouts": 13800.00,
  "transactionCount": 45
}

---

### GET `/`
Filters:
- weekEnding
- status
- page, size

---

### GET `/{id}/report`
Returns printable fund flow report

---

## Risk & Credit (`/api/admin/risk`)

### GET `/scores`
Filters:
- userId
- scoreBelow

---

### GET `/flags`
Returns active anomaly flags

---

### PUT `/flags/{id}/clear`
Admin clears a flagged account

---

### GET `/throttles`
Returns current throttle configuration

---

### PUT `/throttles`
{
  "requestsPerMinute": 60,
  "appointmentsPerHour": 10
}

---

## Audit (`/api/admin/audit`)

### GET `/logs`
Filters:
- userId
- action
- dateFrom
- dateTo
- page, size

---

## Dashboard (`/api/dashboard`) — role-aware

### GET `/`
Returns `{ role, stats }` scoped to the caller's role. Each role sees only
the slice relevant to their workspace:

- SYSTEM_ADMIN: all metrics
- SERVICE_COORDINATOR / PROVIDER: appointments (provider filtered by ownership)
- PRODUCTION_PLANNER: work orders
- CONTENT_MODERATOR: pendingModeration
- REVIEW_MANAGER / REVIEWER: assignedReviews
- FINANCE_CLERK: weeklySettlementTotal, pendingPayments

Legacy admin-only alias `/api/admin/dashboard` remains for backwards compatibility.

## Production - Work Orders additions

### PUT `/{id}/repair`
Admin-only privileged reversal (parity with appointment repair):
{
  "targetState": "IN_PROGRESS",
  "reason": "Operator-entered quantity was wrong"
}
Errors:
- 400 Invalid target state or missing reason
- 403 Not SYSTEM_ADMIN
