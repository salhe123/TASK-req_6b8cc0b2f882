## Business Logic Questions Log

### 1. How does the appointment state machine work?
- **Problem:** The prompt lists create, confirm, reschedule, cancel, and auto-expire but does not define every valid transition.
- **My Understanding:** Appointments follow a strict linear state flow with time-based rules.
- **Solution:** States: PENDING → CONFIRMED → IN_PROGRESS → COMPLETED. PENDING auto-expires after 24 hours. Reschedule blocked within 2 hours of start unless Admin overrides with a logged reason. Cancel allowed from PENDING or CONFIRMED. All transitions are immutable history entries; reversal only via Admin "repair" with full audit trail.

---

### 2. How does conflict-of-interest recusal work for reviews?
- **Problem:** Reviewers cannot review vendors they worked with in the last 12 months, but the source of that relationship data is not specified.
- **My Understanding:** The system must track reviewer-vendor associations and enforce a 12-month cooling-off window.
- **Solution:** Maintain a `reviewer_vendor_history` table with reviewer ID, vendor ID, and end date. On assignment, reject any reviewer whose last association with that vendor ended less than 12 months ago. Support both auto-assignment (skip conflicted reviewers) and manual assignment (block with error message).

---

### 3. How do offline payments and x work?
- **Problem:** The system is offline, so there are no real payment gateway callbacks. The prompt mentions signed internal receipts and CSV bank batch imports.
- **My Understanding:** Finance Clerks import payment data locally; the portal generates its own signed receipts as proof of record.
- **Solution:** Finance uploads CSV files (with checksum validation). The portal generates signed internal receipts using a local HMAC key. Reconciliation compares uploaded bank records against portal receipts, flags mismatches, raises alerts when daily variance exceeds $50.00 or duplicate receipt fingerprints are detected.

---

### 4. How does the weekly settlement and commission cycle work?
- **Problem:** The prompt mentions an 8% platform fee and weekly settlement cycles but does not define cut-off rules or partial settlement handling.
- **My Understanding:** Settlements batch all completed and reconciled transactions within a 7-day window.
- **Solution:** Settlement runs weekly (configurable cut-off day). Only COMPLETED and reconciled jobs are included. Platform fee is 8% by default (admin-adjustable). Commission splits and settlement postings are wrapped in a single database transaction — all succeed or all roll back. Finance can generate printable fund flow reports per cycle.

---

### 5. How should product deduplication and merge work?
- **Problem:** The prompt specifies similarity fingerprints with a 0.85 confidence threshold but does not detail what happens above vs. below that threshold.
- **My Understanding:** High-confidence duplicates can be auto-merged; low-confidence ones need human review.
- **Solution:** When similarity score is >= 0.85, auto-merge and log the action. Below 0.85, flag the pair for Moderator review with a side-by-side comparison. Moderator can approve merge, reject, or mark as distinct. All merge decisions are logged with before/after snapshots.

---

### 6. How does risk scoring and throttling work?
- **Problem:** The prompt lists several abnormal behavior thresholds but does not define how they combine or escalate.
- **My Understanding:** Each rule is an independent trigger; hitting any one applies its configured response.
- **Solution:** Evaluate rules independently: >20 postings/day, >5 cancellations/week, duplicate device fingerprints, or suspicious IP patterns each trigger their own flag. Throttles (60 req/min, 10 appointments/hour) are enforced at the middleware level. Flagged accounts are placed on step-up review hold until Admin clears them. Risk scores update nightly from success rate, dispute rate, and cancellation history.
