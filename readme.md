# Instructor Revenue Ledger

## Challenge Scope

This Laravel 11 application implements Career 180's financial core: upfront subscription payments, subscription-anchored monthly instructor earnings, an immutable instructor ledger, fixed payout snapshots, queued provider processing, and reconciliation of uncertain provider outcomes.

## Stack

- PHP 8.3
- Laravel 11
- MySQL 8.4, the primary runtime database for this challenge
- Laravel database queue
- Filament 3 and Livewire 3
- Pest 3
- Nginx via Docker Compose

## Quick Start

```bash
cp .env.example .env
docker compose up -d --build
docker exec revenue-ledger-app composer install
docker exec revenue-ledger-app php artisan key:generate
docker exec revenue-ledger-app php artisan migrate:fresh --seed
```

Composer publishes the required Filament assets automatically. Open `http://localhost:8086/instructor` after setup.

### Mock Provider Configuration

`MOCK_PROVIDER_OUTCOME` controls the deterministic behavior of the challenge's mock payout provider:

| Value | Provider behavior | Expected payout state | Reconciliation |
| --- | --- | --- | --- |
| `success` | Records and confirms the transfer immediately | `paid` | Not required |
| `permanent_failure` | Rejects the transfer without moving money | `failed` | Not required |
| `timeout_after_success` | Records a completed transfer, then simulates a lost response | `pending_confirmation` until provider status is checked, then `paid` | Required |

To select an outcome for one manually started worker, pass it directly to that process as shown in the review flows below. This requires no `.env` edit, restart, or cache-clearing command.

An environment value supplied with `docker exec -e MOCK_PROVIDER_OUTCOME=...` has precedence over `.env` for that command. For example, a worker started with `-e MOCK_PROVIDER_OUTCOME=success` will process a successful payout even if `.env` contains `permanent_failure`.

If `MOCK_PROVIDER_OUTCOME` is changed in `.env`, a new `docker exec ... php artisan queue:work` process reads it immediately. Laravel configuration is not cached by this project, so `config:clear` and `optimize:clear` are unnecessary. If the continuously running Compose queue service is active, restart that long-running worker after the edit:

```bash
docker compose restart queue
```

`PAYOUT_BATCH_SIZE` is the default bounded instructor batch for `payouts:process` when `--batch` is omitted. It accepts a positive integer and is constrained by the command to `1` through `1000`; an explicit `--batch` option takes precedence.

## Demo Credentials

### Instructor — Omar Khaled

- Email: `omar.khaled@example.com`
- Password: `password`
- Login: `http://localhost:8086/instructor/login`
- UI: Read-only instructor financial screen

### Instructor — Mariam Adel

- Email: `mariam.adel@example.com`
- Password: `password`
- Login: `http://localhost:8086/instructor/login`
- UI: Read-only instructor financial screen

### Student — Ahmed Hassan

- Email: `ahmed.hassan@example.com`
- Password: `password`
- Login: None
- UI: No student dashboard is implemented because it is outside the challenge scope

There is no separate admin role or reviewer account. Reviewers use either instructor account.

## Seeded Demo Scenario

`php artisan migrate:fresh --seed` always creates:

- Student: Ahmed Hassan
- Instructors: Omar Khaled and Mariam Adel
- Plan: Quarterly, three months
- Payment: 300.00 EGP paid upfront
- Earning periods: three monthly periods anchored to the subscription start; the first two are completed and the third remains open
- Instructor revenue share: 60% for each completed period
- Completed-period gross amount: 100.00 EGP
- Instructor pool per completed period: 60.00 EGP
- Equal allocation: 30.00 EGP per instructor per completed period

Initial balance for each instructor:

- Total earned: **60.00 EGP**
- Total paid: **0.00 EGP**
- Outstanding balance: **60.00 EGP**
- Payout history: empty

## Manual Demo / Review Flow

Stop the continuously running Compose queue service while demonstrating individual states:

```bash
docker compose stop queue
```

Keep this service stopped throughout the manual scenarios. Otherwise it can consume a newly dispatched payout job immediately using the provider outcome that was loaded when that long-running worker started. The explicit `docker exec ... queue:work` commands below then control which outcome processes the job.

Before running a scenario, verify that only the database, application, Nginx, and supporting services are running:

```bash
docker compose ps
```

The `revenue-ledger-queue` service should not show as running during these controlled demonstrations.

If you prefer to change `.env` instead of using `docker exec -e`, stop the Compose queue first, edit `MOCK_PROVIDER_OUTCOME`, and verify the value before creating payouts:

```bash
docker compose stop queue
docker exec revenue-ledger-app php artisan config:show ledger
```

Then run the worker without `-e`; the new process reads the value from `.env`:

```bash
docker exec revenue-ledger-app php artisan queue:work --stop-when-empty --tries=5
```

### Initial Financial State

```bash
docker exec revenue-ledger-app php artisan migrate:fresh --seed
```

Expected: the deterministic users and financial scenario above exist, with no payout yet. Log in as either instructor and verify earned 60.00 EGP, paid 0.00 EGP, and outstanding 60.00 EGP.

### Successful Payout

The default `.env.example` uses immediate success:

```dotenv
MOCK_PROVIDER_OUTCOME=success
```

Create the two instructor payouts:

```bash
docker exec revenue-ledger-app php artisan payouts:process --batch=100
```

Expected: `Created and dispatched 2 payout(s).`

Process both queued jobs using the success outcome:

```bash
docker exec -e MOCK_PROVIDER_OUTCOME=success revenue-ledger-app php artisan queue:work --stop-when-empty --tries=5
```

After refreshing the instructor screen:

- Total earned remains 60.00 EGP
- Total paid becomes 60.00 EGP
- Outstanding balance becomes 0.00 EGP
- Payout history contains one paid 60.00 EGP payout

The mock provider confirms each transfer immediately, each payout becomes `paid`, and reconciliation is not required.

### Duplicate Payout Command

Run the command again:

```bash
docker exec revenue-ledger-app php artisan payouts:process --batch=100
```

Expected: `Created and dispatched 0 payout(s).` The existing ledger entries are already reserved and cannot enter another payout.

### Permanent Provider Failure

No `.env` edit, container restart, or configuration clear is required when the outcome is passed to the worker process:

```bash
docker exec revenue-ledger-app php artisan migrate:fresh --seed
docker exec revenue-ledger-app php artisan payouts:process --batch=100
docker exec -e MOCK_PROVIDER_OUTCOME=permanent_failure revenue-ledger-app php artisan queue:work --stop-when-empty --tries=5
```

Expected: both payouts become `failed`; paid remains 0.00 EGP, outstanding remains 60.00 EGP, exact ledger reservations remain traceable, and rerunning payout creation produces zero new payouts.

The provider does not move money in this mode. The result is definitive, so reconciliation is not required.

### Timeout After Success

Reset and create the two payouts:

```bash
docker exec revenue-ledger-app php artisan migrate:fresh --seed
docker exec revenue-ledger-app php artisan payouts:process --batch=100
```

Process exactly the two payout jobs with the timeout outcome:

```bash
docker exec -e MOCK_PROVIDER_OUTCOME=timeout_after_success revenue-ledger-app php artisan queue:work --once --tries=5
docker exec -e MOCK_PROVIDER_OUTCOME=timeout_after_success revenue-ledger-app php artisan queue:work --once --tries=5
```

Expected: the mock provider has recorded successful transfers, while both application payouts remain `pending_confirmation`. Running `payouts:process` again creates zero payouts and no second transfer is attempted.

This outcome is uncertain only from the application's perspective. Reconciliation is required to discover the already-completed provider result.

### Reconciliation

```bash
docker exec revenue-ledger-app php artisan payouts:reconcile --batch=100
docker exec -e MOCK_PROVIDER_OUTCOME=timeout_after_success revenue-ledger-app php artisan queue:work --stop-when-empty --tries=5
```

Expected: the original provider operations are checked, both payouts become `paid`, and no new transfers occur.

`payouts:reconcile` correctly reports `Dispatched 0 reconciliation job(s).` after a normal successful payout flow because no payout remains in `processing` or `pending_confirmation`. In the timeout scenario above it dispatches two jobs.

To resume the normal background worker afterward:

```bash
docker compose start queue
```

## Tests and Code Quality

```bash
docker exec revenue-ledger-app ./vendor/bin/pest
docker exec revenue-ledger-app ./vendor/bin/pint --test
```

The suite covers exact allocation and conservation, anchored periods, configuration and instructor snapshots, refunds and corrections, balance derivation, immutable history, payout reservation, duplicate commands and jobs, worker interruption, provider failure, timeout-after-success, and reconciliation.

## Key Financial Assumptions

- EGP values are stored and calculated as integer piastres.
- Revenue share and the instructor set are provided by the upstream LMS for each earning period.
- Completed earning periods remain immutable; configuration and instructor changes affect only future periods.
- Equal instructor allocation is used, with indivisible minor units explicitly assigned to the platform.
- Refunds are prorated over the unused subscription term. Completed earnings remain unchanged, future earnings stop, and any required financial correction is appended to the ledger.
- The payment-duration division remainder is assigned to the final earning period so every piastre remains accounted for.

## Intentional Non-Scope

- Full LMS UI and student dashboard
- Course management
- Watch-time allocation and engagement scoring
- Instructor discovery
- Tax logic
- Complex RBAC
- Production payment gateway
- Unrelated CRUD or administration screens

## Documentation

- [`docs/architecture_desion.md`](docs/architecture_desion.md) — authoritative business and financial decisions
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — implementation and technical architecture overview
- [`docs/TECHNICAL_GUIDELINES.md`](docs/TECHNICAL_GUIDELINES.md) — implementation and code constraints
- [`docs/AI_USAGE.md`](docs/AI_USAGE.md) — AI usage disclosure
