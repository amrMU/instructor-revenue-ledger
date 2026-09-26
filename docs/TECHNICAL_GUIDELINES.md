# Instructor Revenue Ledger — Complete Implementation Guidelines

This single document contains the complete technical, code, view, testing, and Codex implementation rules for the Laravel hiring challenge.

## 1. Sources of truth

Use this order of authority:

1. The hiring challenge defines the required scope and deliverables.
2. `docs/architecture_desion.md` is the authoritative source for all business and financial decisions intentionally left open by the challenge.
3. This document defines implementation, code, view, and testing standards.

Do not introduce a business rule that is absent from both the challenge and `docs/architecture_desion.md`.

Do not silently change an architecture decision in code. If implementation exposes a genuine contradiction, document and resolve it in `docs/architecture_desion.md` before continuing.

The challenge requires a submitted file named `docs/ARCHITECTURE.md`. Keep `docs/architecture_desion.md` authoritative. The required `docs/ARCHITECTURE.md` should refer to or summarize it without creating a second, conflicting set of business decisions.

## 2. Required implementation scope

Implement only the money core required by the challenge:

- Database schema and migrations for subscriptions, instructor earnings or balances, and payouts
- Revenue allocation from subscription payments after the platform cut
- An Artisan payout command
- Queued payout jobs
- A mock payment provider
- Provider status lookup for resolving uncertain results
- Meaningful Pest unit tests for core business logic and financial calculations
- Tests proving that duplicate payout runs, retried jobs, and unreliable provider responses cannot double-pay
- One simple Filament v3 screen showing instructor balance and payout history
- Factories and seeders needed to run and demonstrate the solution
- Required README, architecture, AI-usage, test evidence, and video material

The system must be able to determine at any time:

- How much each instructor is owed
- How much has already been paid
- How much remains outstanding

## 3. Required stack

Use the stack specified by the challenge:

- Laravel 11
- Livewire v3
- Alpine.js
- Filament v3
- Pest
- MySQL
- Laravel queues

Docker is optional. Keep or add it only when it materially simplifies setup. Do not change required major framework versions.

## 4. Explicit non-goals

Do not build a complete LMS or introduce unrelated product features.

Unless `docs/architecture_desion.md` explicitly requires them, do not add:

- Course discovery or management systems
- Watch-time, engagement, or instructor-ranking algorithms
- Tax, invoicing, payroll, or multi-currency systems
- Refund penalties, refund windows, cancellation fees, or repricing rules
- Minimum payout thresholds
- Multiple payment-provider integrations
- A full accounting or event-sourcing platform
- Redis, RabbitMQ, Kafka, Horizon, or additional infrastructure not required by the challenge
- Additional admin dashboards or financial write screens
- Plan-change implementation

The senior bonus about changing plans mid-term is discussion only. Do not build it.

## 5. Backend architecture

Use this dependency direction consistently:

```text
Controller / Command / Job
            ↓
          Service
            ↓
        Repository
            ↓
          Database
```

External payment access is a separate boundary:

```text
Service → Payment Provider Contract → Mock Payment Provider
```

Do not bypass these layers for business workflows. Avoid generic base repositories, generic CRUD services, and abstractions with no use in this challenge.

## 6. Layer responsibilities

### 6.1 Controllers

Controllers may:

- Receive HTTP requests
- Delegate validation to Form Requests
- Call a service
- Return a response, resource, redirect, or view

Controllers must not contain:

- Business rules
- Financial calculations
- Database queries
- Database transactions
- Payout state transitions
- Payment-provider calls

### 6.2 Artisan commands

The payout command is an orchestration entry point. It may find work in bounded batches and call the payout service.

It must not calculate balances, reserve earnings, move money, or call the payment provider directly.

Running the command repeatedly, concurrently, or from two servers must remain safe.

### 6.3 Queued jobs

Jobs must be small, retry-safe orchestration units.

Every job must:

- Reload persisted state
- Check the current payout status
- Delegate the next valid action to a service
- Be safe when executed more than once

A job must not assume it is running for the first time.

### 6.4 Services

Services own business use cases and orchestration. They may:

- Apply decisions from `docs/architecture_desion.md`
- Perform financial calculations
- Coordinate repositories
- Open short database transactions
- Enforce valid state transitions
- Coordinate provider calls and reconciliation
- Enforce idempotency rules

Do not call an external provider while holding long-running database locks.

### 6.5 Repositories

Repositories own persistence and database retrieval. They may:

- Query and persist models
- Perform bounded batch reads
- Apply row locks
- Expose database operations required by services

Repositories must not:

- Decide financial policy
- Calculate revenue shares
- Decide payout eligibility
- Call payment providers
- Call services

Repository interfaces are not required merely for symmetry. The payment provider does require a contract because it is an external boundary and must be replaceable in tests.

### 6.6 Models

Eloquent models may contain:

- Relationships
- Casts
- Scopes
- Small state helpers

Do not place multi-step financial workflows, provider operations, or large calculations in models or observers.

### 6.7 Validation

Use Form Requests for HTTP input shape, types, format, and basic constraints.

Business invariants must also be protected by services and, where possible, database constraints. Request validation alone is not enough for financial correctness.

## 7. Financial correctness

Implement financial behavior exactly as defined in `docs/architecture_desion.md`, including its decisions about:

- Revenue recognition
- Earning periods
- Revenue-share snapshots
- Instructor-set snapshots and allocation
- Money representation and rounding
- Refunds and adjustments
- Immutable financial history
- Instructor balance derivation
- Payout eligibility
- Payout snapshots
- Payout states
- Idempotency and concurrency
- Provider timeouts and reconciliation

The implementation must preserve these guarantees:

- Never use binary floating-point values for money.
- Every amount must be traceable to persisted financial records.
- Confirmed financial history must not be rewritten to hide a correction.
- Corrections and refunds use new adjustment records when required by the architecture.
- A payout has a stable identity, fixed amount, and exact underlying earnings.
- The same earning cannot belong to more than one payout.
- New earnings do not change an already-created payout.
- A provider timeout is an uncertain result, not proof of failure.
- The same logical provider operation reuses the same idempotency key.
- No retry, duplicate job, overlapping command, or concurrent server may cause a duplicate payment.

Use database transactions, unique constraints, row locks, and atomic state changes where required. Application-level `exists` checks alone are insufficient.

## 8. Payment-provider boundary

The mock provider must model exactly the outcomes required by the challenge:

- Confirmed success
- Permanent failure
- Timeout after the provider has already succeeded
- Later status lookup revealing the real outcome

The provider contract must accept a stable idempotency key.

Persist enough provider-operation state to reconcile an uncertain response safely. Do not store credentials, secrets, tokens, or sensitive payment data in financial records.

The runtime mock may choose outcomes randomly as required by the challenge. Tests must use controllable deterministic fakes.

## 9. Database standards

- Use explicit foreign keys and suitable delete behavior.
- Store money using the exact representation required by `docs/architecture_desion.md`.
- Use explicit timestamps for financial events and provider attempts where needed.
- Use unique constraints to enforce idempotency and prevent one earning from being assigned to multiple payouts.
- Do not use a mutable instructor balance column as the sole source of financial truth.
- Keep financial history reproducible from persisted records.
- Migrations must be reversible and must not depend on local-only data.
- Factories and seeders must create coherent demonstration data without inventing new business features.

## 10. Scale and data access

Design for 500,000 active subscriptions and tens of millions of underlying records without building unnecessary distributed infrastructure.

- Add indexes for actual lookup, eligibility, status, foreign-key, and reconciliation paths.
- Process large result sets in bounded batches or cursors.
- Never load all subscriptions, earnings, payouts, or instructors into memory.
- Avoid N+1 queries.
- Select only the columns needed for batch work.
- Keep financial transactions short.
- Prefer database-enforced correctness over process-local assumptions.
- Optimize demonstrated access paths instead of hypothetical features.

## 11. General code standards

- Follow PSR-12 and Laravel conventions.
- Enable strict types in project-owned PHP files where consistent with the repository baseline.
- Use typed parameters and return types.
- Use dependency injection.
- Centralize financial state values using enums or an equivalent explicit definition.
- Use business-specific names such as `CreatePayoutService`, `InstructorLedgerRepository`, and `ReconcilePayoutJob`.
- Avoid vague names such as `Helper`, `Manager`, `CommonService`, `handleData`, or `processStuff`.
- Keep methods focused.
- Prefer early returns over deeply nested conditionals.
- Do not leave dead code, speculative abstractions, or commented-out implementations.
- Do not hard-code financial percentages, provider outcomes, identifiers, or environment-specific values in business code.
- Use comments for non-obvious invariants and trade-offs, not ordinary syntax.
- Keep controllers, commands, jobs, models, Filament classes, and views thin.

## 12. View and frontend standards

### 12.1 Required UI scope

The challenge requires one simple Filament v3 screen showing:

- Instructor balance
- Payout history

Read-only is sufficient. Do not expand this into a full administration product.

Livewire v3 and Alpine.js are part of the stack, but use them only where the required screen genuinely needs server-side interaction or small client-side behavior.

### 12.2 View responsibility boundaries

```text
Blade / Filament view → presentation
Livewire / Filament class → UI state and orchestration
Service → business rules and financial calculations
Repository → persistence and queries
```

Views and UI classes must not become an alternative service layer.

### 12.3 Blade rules

Blade templates may:

- Render prepared data
- Format labels, dates, and already-calculated monetary values
- Show validation errors and empty states
- Use small presentation-only conditions
- Render reusable components

Blade templates must not:

- Query Eloquent or the database
- Call repositories or payment providers
- Calculate balances, revenue shares, refunds, rounding, or payout eligibility
- Open transactions
- Change financial state
- Contain large `@php` blocks

Formatting a known minor-unit value for display is allowed. Deriving the financial value inside the view is not.

### 12.4 Filament rules

- Show instructor identity and the balance figures defined by the architecture.
- Show payout history with useful persisted statuses and timestamps.
- Keep the screen read-only.
- Do not trigger payouts, refunds, retries, or reconciliation from the screen.
- Do not duplicate balance rules in Filament columns or callbacks.
- Avoid custom dashboards, charts, analytics, filters, and navigation sections that do not help verify the required information.

### 12.5 Livewire and Alpine.js

- Use Livewire only for necessary server-owned UI state.
- Use Alpine.js only for small local interactions.
- Do not implement financial state in browser-side data.
- Do not duplicate the same state in Livewire and Alpine without a clear need.
- Keep public component state minimal.
- Validate any user-controlled input.

### 12.6 View organization

Group views by feature and follow Laravel and Filament conventions.

```text
resources/views/
├── components/
├── layouts/
└── livewire/
    └── ...
```

Create a Blade component only when markup or presentation behavior is genuinely reused. Prefer descriptive names such as `money`, `payout-status-badge`, and `empty-state`.

### 12.7 Display standards

- Display money consistently in EGP while preserving the stored representation from `docs/architecture_desion.md`.
- Label owed, paid, and outstanding values clearly.
- Centralize status labels and colors.
- Provide a clear empty state when there are no payouts.
- Use semantic headings and table labels.
- Preserve readable contrast and keyboard access using Filament defaults where possible.
- Keep the screen usable at ordinary desktop and mobile widths.

### 12.8 Prohibited UI scope

Do not add:

- Instructor or student portals
- Course-management screens
- Editable financial records
- Manual balance adjustments
- Provider credential screens
- Unrequired analytics or charts
- Plan-change UI

## 13. Testing standards

### 13.1 Mandatory challenge tests

Use Pest. The suite must contain meaningful unit tests for core business logic and financial calculations.

At minimum, prove that:

1. Running the payout process twice never double-pays.
2. Retried jobs never double-pay.
3. Unreliable provider responses never cause duplicate payments.

These are mandatory requirements, not optional examples.

### 13.2 Architecture-decision coverage

Cover every implemented financial decision in `docs/architecture_desion.md`, including where applicable:

- Revenue recognition across earning periods
- Revenue-share changes affecting only the periods defined by the architecture
- Instructor-set changes affecting only future periods
- Equal instructor allocation
- Uneven splits and explicit rounding treatment
- Conservation of every minor unit
- Refund before payout
- Refund after payout or reservation
- Immutable history and compensating adjustments
- Correct owed, paid, and outstanding totals
- Fixed payout amount and exact payout items
- Legal payout state transitions
- Stable provider idempotency keys
- Timeout after success followed by status reconciliation
- Permanent provider failure and any permitted retry path

Do not invent a test expectation that creates a new business rule. Expected behavior must come from the challenge or `docs/architecture_desion.md`.

### 13.3 Concurrency and idempotency coverage

Application-level mocks alone are insufficient when correctness depends on the database.

Prove that:

- Two payout creation attempts cannot reserve the same earning twice.
- Duplicate payout-command runs converge on one valid result.
- A duplicate queued job cannot submit a paid payout again.
- A payout with an uncertain result is reconciled before another transfer is allowed.
- The same logical payout reuses its provider idempotency key.

Where true parallel execution is impractical, directly test the database constraint or atomic transition that closes the race and document what the test proves.

### 13.4 Test levels

Use unit tests for deterministic rules such as:

- Allocation
- Earning-period calculations
- Rounding
- Financial totals
- State-transition decisions

Use integration or feature tests for behavior depending on:

- Database transactions
- Locks
- Unique constraints
- Command execution
- Job retries
- Ledger queries

Keep UI tests proportional to the single read-only screen. Verify balance, payout history, and empty-state rendering.

### 13.5 Provider tests

Use deterministic provider fakes to cover:

- Confirmed success
- Confirmed permanent failure
- Timeout after actual success
- Later status lookup confirming success
- Later status lookup confirming failure, if supported by the architecture

Assert both provider calls and persisted payout/provider state. A test must fail if a second transfer occurs while the first result is uncertain.

### 13.6 Financial invariants

Use explicit assertions proving that:

- No amount is created or lost during allocation.
- No earning belongs to more than one payout.
- A paid payout is terminal.
- Retrying the same logical operation does not increase the amount paid.
- Outstanding balance agrees with authoritative financial records.
- Corrections add records instead of rewriting confirmed history.

Prefer exact integer assertions over formatted currency strings.

### 13.7 Test data

- Use factories for valid baseline records.
- Override only fields relevant to the scenario.
- Use explicit amounts and dates in financial tests.
- Freeze time for period-boundary and retry scenarios.
- Avoid shared mutable fixtures.
- Avoid order-dependent tests.
- Name tests by observable behavior and outcome.

### 13.8 Test evidence

- Document the full test command in `README.md`.
- Ensure the suite passes from a clean documented setup.
- Capture the passing-test screenshot required by the challenge.
- Demonstrate at least three supplied failure scenarios in the required video.

Do not weaken assertions or replace deterministic tests with a video demonstration.

## 14. Documentation and submission

The final repository must include:

### Code

- Source code
- Migrations
- Factories
- Seeders

### Documentation

- `README.md` with setup, test instructions, and assumptions
- `docs/architecture_desion.md` as the authoritative decision record
- `docs/ARCHITECTURE.md` using the filename required by the challenge and referring to the authoritative decisions
- `docs/AI_USAGE.md` with truthful AI workflow and ownership details

The architecture documentation must cover:

- Key architectural decisions
- Revenue allocation strategy
- Idempotency approach
- Provider timeout handling
- Scaling considerations
- Known limitations

The AI-usage document must cover:

- How AI was used
- Main prompts or workflows
- Fully generated versus manually designed or modified work
- Decisions personally made by the candidate
- What differentiates the solution
- Intentional trade-offs and improvements

### Evidence

- Screenshot of passing tests
- Required 15–20 minute video link

Do not claim guarantees that are not enforced by code or tests.

## 15. Definition of done

The implementation is complete only when:

- Every required challenge deliverable exists.
- Financial behavior matches `docs/architecture_desion.md`.
- Controller → Service → Repository direction is preserved.
- Mandatory failure and duplicate-payment tests pass.
- Refund, rounding, balance, timeout, retry, and concurrency behavior follows the architecture and is covered in proportion to risk.
- The Filament screen shows balance and payout history without containing financial logic.
- Tests pass from the documented setup.
- Factories and seeders provide coherent demonstration data.
- Required documentation and evidence are complete.
- No feature or infrastructure was added merely for completeness.

---

# Codex Implementation Prompt

Implement the Instructor Revenue Ledger hiring challenge in this repository.

Before changing code, read the full hiring challenge, `docs/architecture_desion.md`, and this complete guideline file.

Follow this authority order:

1. The hiring challenge defines required scope and deliverables.
2. `docs/architecture_desion.md` is authoritative for business and financial decisions left open by the challenge.
3. This document defines implementation, code, view, and testing standards.

Preserve this dependency direction:

```text
Controller / Command / Job → Service → Repository → Database
Service → Payment Provider Contract → Mock Provider
```

Implement every required deliverable and nothing beyond the challenge:

- Schema and migrations for subscriptions, instructor financial records, and payouts
- Revenue allocation after the platform cut
- Artisan payout command and retry-safe queued jobs
- Mock provider with success, permanent failure, timeout after success, and later status lookup
- Correct owed, paid, and outstanding balance reporting
- Pest tests for core calculations and all mandatory duplicate-payment scenarios
- One simple read-only Filament v3 screen for instructor balance and payout history
- Required factories, seeders, documentation, test-evidence support, and video support

Financial correctness is the priority. Enforce idempotency and concurrency through database-backed guarantees, fixed payout snapshots, stable provider-operation identity, and reconciliation of uncertain outcomes.

Never treat a timeout as confirmed failure. Never use floating point for money. Never rewrite confirmed financial history when the architecture requires an adjustment.

Keep views presentation-only. Keep controllers, commands, jobs, models, Filament classes, and Blade files thin. Do not place database queries or financial calculations in Blade.

Do not add a full LMS, additional dashboards, unsupported refund or pricing policies, extra infrastructure, multiple providers, tax or multi-currency features, or the discussion-only plan-change bonus.

Work in small verifiable increments. Before every implementation step, identify the exact challenge requirement and architecture decision it satisfies. Run relevant tests after each step and the full suite before completion. Keep documentation synchronized with the final code.

If a real conflict exists between the challenge and `docs/architecture_desion.md`, do not guess or silently change behavior. Report the exact conflict and the smallest decision needed to resolve it.
