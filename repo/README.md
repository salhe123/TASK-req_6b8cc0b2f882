# Precision Hardware Manufacturing & Service Operations Portal

**Project Type:** `fullstack`

A full-stack offline-capable portal for a U.S.-based electronics manufacturer. It unifies production planning (MPS/MRP), service appointment scheduling, expert product reviews, hardware catalog management with deduplication, content moderation, financial settlement operations, and risk/credit controls — all with strict RBAC, immutable audit logging, and transactional integrity.

## Architecture & Tech Stack

* **Frontend:** HTML, CSS, JavaScript, Layui 2.9 (locally bundled, no CDN)
* **Backend:** PHP 8.1, ThinkPHP 6.1
* **Database:** MySQL 8.0
* **Containerization:** Docker & Docker Compose (Required)

## Project Structure

```text
.
├── app/                    # ThinkPHP controllers, services, models, middleware, commands
├── config/                 # ThinkPHP configuration (app, database, middleware, session)
├── database/               # schema.sql, seed.sql, seed.php, migrations/, seeds/
├── public/                 # Web root (index.php, static/ bundled Layui assets)
├── route/                  # REST + frontend page route definitions
├── view/                   # Layui HTML templates per module
├── tests/                  # PHPUnit unit, integration, and API suites
├── cypress/                # Optional Cypress E2E specs (not run by run_tests.sh)
├── docker/                 # Container entrypoints, cron schedule
├── .env.example            # Example environment variables — MANDATORY FOR TASK WITH .ENV VARIABLE
├── Dockerfile              # Production image (PHP 8.1 + Apache)
├── Dockerfile.test         # Self-contained test image (PHP + MariaDB)
├── docker-compose.yml      # Multi-container orchestration — MANDATORY
├── run_tests.sh            # Standardized test execution script — MANDATORY
└── README.md               # Project documentation — MANDATORY
```

## Prerequisites

To ensure a consistent environment, this project is designed to run entirely within containers. You must have the following installed:
* [Docker](https://docs.docker.com/get-docker/)
* [Docker Compose](https://docs.docker.com/compose/install/)

## Running the Application

1. **Build and Start Containers:**
   Use Docker Compose to build the images and spin up the entire stack in detached mode.
   ```bash
   docker-compose up --build -d
   ```

   Note: The Docker should copy the example environment file (if any) and populate any necessary keys.
   ```bash
   cp .env.example .env
   ```

2. **Access the App:**
   * Frontend: `http://localhost:8080`
   * Backend API: `http://localhost:8080/api`
   * Health Check: `http://localhost:8080/api/health`
   * Login Page: `http://localhost:8080/login`

3. **Stop the Application:**
   ```bash
   docker-compose down -v
   ```

## Environment Rules (Strict)

This project is designed to run fully inside Docker containers.

Do **not** use local runtime/package installation commands such as:
* `npm install`
* `pip install`
* `apt-get`
* manual DB creation/migration outside the provided container flow

Use only:
* `docker-compose up ...` / `docker-compose down ...` for app lifecycle
* `./run_tests.sh` for the primary backend test gate

## Testing

Primary automated gate (Dockerized): PHPUnit unit + integration + API suites via `run_tests.sh`.

Optional browser E2E harness: Cypress specs under `cypress/e2e` (run separately when needed).

Make sure the script is executable, then run it:

```bash
chmod +x run_tests.sh
./run_tests.sh
```
*Note: The `run_tests.sh` script should output a standard exit code (`0` for success, non-zero for failure) to integrate smoothly with CI/CD validators.*

Optional Cypress run:
```bash
npm run cypress:run
```

## Verification Method

After startup (`docker-compose up --build -d`), verify both API and UI behavior:

1. **Health/API Check (curl)**
   ```bash
   curl -s http://localhost:8080/api/health
   ```
   Expected: JSON response with `status` equal to `ok`.

2. **Authentication Check (API)**
   Use curl or Postman to call:
   * `POST http://localhost:8080/api/auth/login`
   * Body example:
     ```json
     {
       "username": "admin",
       "password": "Admin12345!"
     }
     ```
   Expected: success response containing a role/token payload.

3. **UI Flow Check (Browser)**
   * Open `http://localhost:8080/login`
   * Log in with a seeded account (for example `admin / Admin12345!`)
   * Confirm dashboard loads and module navigation is visible
   * Open one module page (for example `/appointments` or `/finance/payments`) and confirm data renders without 401/403 errors for authorized role

## Seeded Credentials

The database is pre-seeded with the following test users on startup. Use these credentials to verify authentication and role-based access controls. Authentication is by **username** (the portal does not use email).

| Role | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **System Admin** | `admin` | `Admin12345!` | Full access to all modules, user management, risk config, audit logs. |
| **Production Planner** | `planner1` | `Planner12345!` | Manage MPS schedules, work orders, capacity monitoring, work centers. |
| **Service Coordinator** | `coordinator1` | `Coordinator1!` | Create and manage customer appointments, reschedule, cancel. |
| **Provider / Technician** | `provider1` | `Provider1234!` | Daily appointment queue, check-in/check-out, upload evidence. |
| **Operator** | `operator1` | `Operator1234!` | Start/complete work orders on the shop floor. |
| **Product Specialist** | `specialist1` | `Specialist123!` | Draft and submit catalog entries. Cannot approve — separation of duties. |
| **Content Moderator** | `moderator1` | `Moderator123!` | Approve/reject catalog entries, review deduplication merges. |
| **Reviewer** | `reviewer1` | `Reviewer1234!` | Submit scored reviews via configurable scorecards, blind review. |
| **Review Manager** | `reviewmanager1` | `ReviewMgr1234!` | Manage reviewer pool, create scorecards, assign reviews, publish submissions. |
| **Finance Clerk** | `finance1` | `Finance12345!` | Import CSV payments, run reconciliation, create settlements, reports. |
