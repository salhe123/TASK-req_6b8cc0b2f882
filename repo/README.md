# Precision Hardware Manufacturing & Service Operations Portal

A full-stack offline-capable portal built for a U.S.-based electronics manufacturer, unifying production planning (MPS/MRP), service appointment scheduling, expert product reviews, hardware catalog management with deduplication, content moderation, financial settlement operations, and risk/credit controls — all with strict RBAC, immutable audit logging, and transactional integrity.

## Architecture & Tech Stack

* **Frontend:** HTML, CSS, JavaScript, Layui 2.9 (locally bundled, no CDN)
* **Backend:** PHP 8.1, ThinkPHP 6.1 Framework
* **Database:** MySQL 8.0 (MariaDB in test container)
* **Containerization:** Docker (single self-contained test image)

## Project Structure

```text
.
├── app/
│   ├── controller/           # Route controllers organized by module
│   │   ├── auth/             # Login, logout, password change
│   │   ├── admin/            # User management, dashboard, risk, audit
│   │   ├── appointment/      # Appointment lifecycle & state machine
│   │   ├── provider/         # Provider daily queue
│   │   ├── production/       # MPS, work orders, capacity, work centers
│   │   ├── catalog/          # Product catalog & standardization
│   │   ├── moderation/       # Bulk approve/reject, merge review
│   │   ├── review/           # Reviewer pool, scorecards, submissions
│   │   └── finance/          # Payments, receipts, reconciliation, settlements
│   ├── model/                # Eloquent-style ORM models
│   ├── service/              # Business logic layer (state machines, scoring, etc.)
│   ├── middleware/            # Auth + RBAC middleware
│   └── command/              # CLI commands (expire, risk score, audit archive)
├── config/                   # ThinkPHP configuration files
├── database/
│   ├── schema.sql            # Full database schema (23 tables, triggers)
│   ├── seed.sql              # Initial seed data
│   └── seed.php              # PHP seeder for bcrypt passwords
├── public/                   # Web root (index.php, static assets)
│   └── static/               # Layui, CSS, JS (bundled locally)
├── route/
│   └── app.php               # All API route definitions
├── view/                     # Layui HTML templates per module
├── tests/
│   ├── unit/                 # PHPUnit unit tests
│   ├── integration/          # PHPUnit in-process integration tests
│   └── api/                  # PHPUnit API tests (real HTTP)
├── cypress/
│   └── e2e/                  # Cypress E2E specs (optional, see README testing section)
├── docker/
│   ├── run_tests_entry.sh    # Test container entrypoint
│   └── crontab               # Scheduled tasks (expire, risk, archive)
├── .env.example              # Example environment variables
├── composer.json             # PHP dependencies
├── Dockerfile                # Production image (PHP 8.1 + Apache)
├── Dockerfile.test           # Self-contained test image (PHP + MySQL)
├── docker-compose.yml        # Multi-container orchestration (app + MySQL)
├── run_tests.sh              # Standardized test execution script
└── README.md                 # This file
```

## Prerequisites

This project is designed to run entirely within Docker. You must have:

* [Docker](https://docs.docker.com/get-docker/) (v20.10+)

No local PHP, MySQL, Node.js, or Composer installation is required.

### Offline / air-gapped deployment

The build supports three dependency paths (first match wins):

1. **Committed `vendor/` directory** — zero network. For air-gapped targets, run `composer install` on a connected workstation, then commit the resulting `vendor/` directory alongside `composer.lock`. Neither the production nor the test image will reach the network.
2. **`composer.lock` + internal mirror** — if `vendor/` is absent but `composer.lock` is present, the build runs `composer install --prefer-dist` with deterministic version resolution. Point Composer at an internal mirror via `composer config repositories.0 composer <url>`.
3. **Public registry** — last-resort fallback, not recommended for locked-down environments.

Generate `composer.lock` on a connected workstation (`composer install` in the repo root) before cutting the air-gap build; commit the resulting `composer.lock` (and optionally `vendor/`) alongside the code drop.

## Running the Application

1. **Copy the environment file and set required secrets:**
   ```bash
   cp .env.example .env
   # Both keys MUST be set (min 32 characters) — the app refuses to start without them.
   # Generate strong values with openssl and paste them into .env:
   echo "APP_KEY = $(openssl rand -base64 48)" >> .env
   echo "HMAC_KEY = $(openssl rand -base64 48)" >> .env
   ```

   Required env entries:
   * `APP_KEY` — AES-256-CBC encryption key for sensitive fields (appointment locations, bank details).
   * `HMAC_KEY` — HMAC-SHA256 key for receipt signatures and audit-archive integrity.

2. **Build and start containers:**
   ```bash
   docker compose up --build -d
   ```

3. **Run database schema and seeds:**
   ```bash
   cat database/schema.sql | docker compose exec -T mysql mysql -u root -proot_secret precision_portal
   cat database/seed.sql | docker compose exec -T mysql mysql -u root -proot_secret precision_portal
   docker compose exec -T app php database/seed.php
   ```

4. **Access the app:**
   * Portal: `http://localhost:8080`
   * Login Page: `http://localhost:8080/login`
   * Health Check: `http://localhost:8080/api/health`

5. **Stop the application:**
   ```bash
   docker compose down -v
   ```

## Testing

### PHPUnit (unit + integration + API)

Executed in a self-contained Docker image that provisions PHP + MariaDB, runs
the schema/seeds, and executes every PHPUnit suite. This is what `./run_tests.sh`
runs and what CI should gate on.

```bash
chmod +x run_tests.sh
./run_tests.sh
```

The script exits with code `0` on success, non-zero on failure.

### Cypress E2E (separate)

Cypress specs live in `cypress/e2e/` and are **not** run by `run_tests.sh`.
They exist so a developer can exercise the browser flows locally once the
portal is up. Install dependencies from `package.json` and run:

```bash
npm install
npm run cypress:open    # interactive
# or
npm run cypress:run     # headless
```

Cypress requires the portal to already be reachable at `http://localhost:8080`.

## Seeded Credentials

The database is pre-seeded with one user per role on startup. Use these credentials to verify authentication and role-based access controls.

| Role | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **System Admin** | `admin` | `Admin12345!` | Full access to all modules, user management, risk config, audit logs. |
| **Production Planner** | `planner1` | `Planner12345!` | Manage MPS schedules, work orders, capacity monitoring, work centers. |
| **Service Coordinator** | `coordinator1` | `Coordinator1!` | Create and manage customer appointments, reschedule, cancel. |
| **Provider / Technician** | `provider1` | `Provider1234!` | Daily appointment queue, check-in/check-out, upload evidence. |
| **Reviewer** | `reviewer1` | `Reviewer1234!` | Submit scored reviews via configurable scorecards, blind review. |
| **Review Manager** | `reviewmanager1` | `ReviewMgr1234!` | Manage reviewer pool, create scorecards, assign reviews, publish submissions. |
| **Product Specialist** | `specialist1` | `Specialist123!` | Draft and submit catalog entries. Cannot approve — separation of duties. |
| **Operator** | `operator1` | `Operator1234!` | Start/complete work orders on the shop floor. |
| **Content Moderator** | `moderator1` | `Moderator123!` | Approve/reject catalog entries, review deduplication merges. |
| **Finance Clerk** | `finance1` | `Finance12345!` | Import CSV payments, run reconciliation, create settlements, reports. |
