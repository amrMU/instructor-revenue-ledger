# Architecture Decisions

This document records the key business and architectural decisions made for the Instructor Revenue Ledger challenge, including the assumptions made where requirements were intentionally left open.

## ADR-001: Revenue Recognition

### Decision

Subscription payments are collected upfront.

The platform’s share is recognized immediately, while the instructors’ share is earned progressively over the subscription term.

No discount or repricing logic is assumed beyond the configured plan price.

Instructor earnings stop when the subscription term ends.

A renewal or a new subscription starts a new financial allocation cycle.

### Rationale

The challenge states that students pay for the full subscription term upfront while instructors receive a revenue share.

Separating cash collection from instructor revenue recognition prevents instructors from becoming entitled to the entire subscription amount on the first day of a multi-month subscription.

This also provides a consistent basis for handling mid-term refunds and changes in the instructors involved during the subscription term.

### Consequences

- The full subscription payment is captured at the start of the subscription.
- The platform share becomes recognized immediately.
- Instructor earnings accrue progressively over the active subscription term.
- Instructor earnings stop when the subscription term ends.
- A renewal or new subscription creates a new financial allocation context.

## ADR-002: Instructor Revenue Allocation

### Decision

Each subscription payment is split into:

- The platform share
- The instructors' revenue share

The revenue-share percentage is determined for each earning period.

The percentage used for a completed earning period is stored with its financial records.

Any later change to the configured percentage affects future earning periods only and does not modify completed historical allocations.

The revenue allocation process receives the set of instructors involved for each earning period.

Determining which instructors are involved is treated as an upstream LMS responsibility and is outside the scope of the financial core implemented in this challenge.

The financial core is responsible only for allocating the instructors' revenue share among the instructors provided for that earning period.

For this implementation, the instructors' revenue pool is divided equally between the provided instructors.

Changes to the instructors involved affect future earning periods only. Completed historical allocations are never recalculated.

### Rationale

The challenge states that instructors receive a revenue share from subscription income, while the remaining amount belongs to the platform.

The challenge does not define a fixed revenue-share percentage, how involved instructors are identified, or a weighted allocation model between instructors.

To preserve historical financial accuracy, the percentage used for each earning period is stored with that period's financial records and is not recalculated from future configuration changes.

To avoid introducing unsupported LMS business rules, instructor discovery is kept outside the financial core.

Because the set of instructors involved may change during the subscription term, allocation is based on the instructor set applicable to each earning period rather than a single instructor set fixed for the entire subscription.

Equal distribution is used because the challenge does not define a weighted allocation model.

### Consequences

- Revenue-share percentages are not hard-coded.
- Each earning period preserves the percentage applied to it.
- Changes to the configured percentage affect future earning periods only.
- The financial core does not infer instructors from watch time, enrollments, or course activity.
- The set of involved instructors may change during the subscription term.
- Instructor changes affect future earning periods only.
- Each provided instructor receives an equal share of the instructors' revenue pool for that earning period.
- Completed historical allocations are immutable and are never recalculated when the instructor set changes.

## ADR-003: Money Representation and Rounding

### Decision

All monetary amounts are stored and calculated using integer minor units.

For EGP, amounts are stored in piastres.

Floating-point arithmetic is not used for financial calculations.

When an instructors' revenue amount cannot be divided evenly between the instructors involved in an earning period, each instructor receives the same whole minor-unit amount.

Any indivisible remainder is assigned to the platform and recorded explicitly as a rounding adjustment.

### Rationale

Financial calculations must use exact arithmetic.

Assigning the rounding remainder to one instructor would make an otherwise equal allocation unequal.

Assigning the indivisible remainder to the platform keeps instructor allocations equal while ensuring that every minor unit remains accounted for.

Recording the remainder as a dedicated rounding adjustment preserves a clear audit trail and prevents unexplained financial differences.

### Consequences

- No floating-point arithmetic is used for money.
- Instructor allocations remain equal.
- No instructor receives preferential treatment due to rounding.
- Any indivisible remainder is assigned to the platform.
- Rounding adjustments are recorded explicitly and remain auditable.
- Every minor unit of the original payment remains accounted for.

## ADR-004: Monthly Earning Period

### Decision

Instructor revenue is earned in monthly earning periods during the active subscription term.

For each monthly earning period, the applicable revenue-share percentage and the set of involved instructors are determined for that period.

Changes to the revenue-share percentage or instructor set affect future earning periods only and never modify completed historical periods.

Instructor earnings stop when the subscription term ends.

### Rationale

Monthly earning periods provide a simple and predictable model for progressive instructor revenue recognition.

They provide clear boundaries for:

- Revenue-share configuration changes
- Changes to the instructors involved
- Refund handling
- Payout eligibility
- Historical financial reporting

### Consequences

- Instructor earnings are recognized monthly.
- Each completed month becomes an immutable financial period.
- Revenue-share changes take effect from the next earning period.
- Instructor-set changes take effect from the next earning period.
- Completed earning periods are never recalculated.
- Earnings stop when the subscription term ends.

## ADR-005: Refund Handling

### Decision

Refunds are prorated based on the unused portion of the subscription term.

Instructor earnings already recognized for completed earning periods remain unchanged.

Future instructor earnings stop from the effective refund date.

Any required financial correction is recorded as a separate adjustment entry rather than modifying historical financial records.

No refund fees, penalties, or refund-window rules are assumed because they are not defined by the challenge.

### Rationale

Students pay for the full subscription term upfront, while instructor revenue is earned progressively.

A prorated refund separates the consumed portion of the subscription from the unused portion without rewriting completed financial history.

### Consequences

- Completed instructor earnings remain unchanged.
- Future earnings stop after the effective refund date.
- Refund-related corrections are represented as new adjustment entries.
- Historical records are never rewritten because of a refund.
- No additional refund policy is introduced beyond the challenge requirements.

## ADR-006: Immutable Financial Records

### Decision

Confirmed financial records are immutable.

Existing earnings, allocations, payouts, and adjustments are never edited or deleted to change financial history.

Corrections are represented by new compensating or adjustment entries.

### Rationale

Financial history must remain auditable and reproducible.

Editing confirmed historical records would make it difficult to determine what actually happened and could introduce balance inconsistencies.

### Consequences

- Confirmed financial records are append-only.
- Refunds and corrections create new adjustment records.
- Historical calculations remain traceable.
- Financial events can be audited back to their original source.

## ADR-007: Instructor Balance Calculation

### Decision

The instructor's financial balance is derived from financial ledger records rather than maintained as the primary source of truth in a mutable balance column.

The system must be able to determine:

- Total earned
- Total paid
- Outstanding balance

from the instructor's financial records.

### Rationale

The challenge explicitly requires the system to answer how much each instructor is owed, how much has already been paid, and how much remains outstanding.

A ledger-derived balance avoids inconsistencies between a mutable balance value and the underlying financial history.

### Consequences

- The financial ledger is the source of truth.
- Instructor balances are derived from ledger records.
- A cached or summarized balance may be introduced later for performance, but it must not replace the ledger as the authoritative source.

## ADR-008: Payout Eligibility

### Decision

Only earned and unpaid instructor amounts are eligible for payout.

Future or unearned subscription revenue is never included in a payout.

The payout process may run on a schedule or be manually triggered.

When it runs, it considers all currently earned and unpaid amounts that are not already reserved by another payout.

No minimum payout threshold is assumed.

### Rationale

Instructor revenue is recognized progressively.

Only revenue that has already become earned should be transferred to instructors.

The challenge does not define a payout threshold or a specific payout frequency, so no additional rule is introduced.

### Consequences

- Unearned revenue cannot be paid.
- Already-paid earnings cannot be included again.
- Earnings already reserved by an active payout cannot be selected again.
- The payout cadence is independent from the monthly earning cadence.
- The Artisan payout command can be safely executed manually or by a scheduler.

## ADR-009: Payout Snapshot

### Decision

When a payout is created, it includes the instructor's eligible earned and unpaid amounts available at that time.

The exact earnings included in the payout are linked to that payout and become reserved.

The payout amount is fixed once the payout is created.

Any earnings recognized after the payout is created are excluded and become eligible for a future payout.

### Rationale

A payout must represent a stable financial obligation.

Recalculating the instructor's current balance during retries could cause the amount of the same payout to change.

Fixing the payout amount and its underlying earnings makes retries, provider reconciliation, and duplicate-payment prevention deterministic.

### Consequences

- A payout always has a fixed amount.
- Each payout can be traced to the exact earnings it contains.
- The same earning cannot be included in two payouts.
- New earnings are handled by future payouts.
- Retrying the same payout does not recalculate the instructor's current balance.

## ADR-010: Payout Idempotency and Concurrency

### Decision

A payout is treated as a uniquely identifiable financial operation.

Re-running the payout command, retrying a queued payout job, or executing payout processing concurrently on multiple workers or servers must never cause the same earnings to be paid more than once.

Idempotency is enforced using database-level constraints and transactional state changes in addition to application-level checks.

Financial state transitions that reserve earnings or create payouts are performed atomically.

### Rationale

The challenge explicitly requires the system to tolerate:

- Overlapping payout schedules
- Manual payout re-triggers
- Multiple servers processing payouts concurrently
- Retried background jobs

Application-level existence checks alone are not sufficient under concurrent execution.

### Consequences

- The same earning cannot be reserved by two payouts.
- The same earning cannot be paid twice.
- Duplicate job execution is safe.
- Concurrent payout processes converge on one valid financial result.
- Database constraints form part of the financial correctness model.

## ADR-011: Payment Provider Failure and Reconciliation

### Decision

Every payout is sent to the payment provider using a stable idempotency key associated with that payout.

Retries for the same payout reuse the same idempotency key.

A provider timeout is not treated as a confirmed payment failure.

If the provider outcome is uncertain, the payout moves to a pending-confirmation state.

The system checks the provider transaction status before attempting another payment.

A new payment attempt is allowed only after the provider confirms that the previous attempt did not succeed.

### Rationale

The challenge states that the external provider may time out after already moving the money.

Immediately retrying a timed-out payment could therefore double-pay the instructor.

A stable provider operation identity and explicit reconciliation step allow the system to safely resolve uncertain outcomes.

### Consequences

- A timeout does not automatically trigger another payment.
- Retried jobs reuse the same provider idempotency key.
- Unknown provider outcomes require reconciliation.
- A confirmed successful provider transaction is never submitted again.
- Provider status checks are part of the payout workflow.

## ADR-012: Payout State Machine

### Decision

A payout can be in one of the following states:

- `pending`
- `processing`
- `pending_confirmation`
- `paid`
- `failed`

State transitions are explicit.

### State Meaning

`pending`

The payout has been created and its earnings have been reserved, but it has not yet been submitted to the provider.

`processing`

The payout is currently being submitted to the payment provider.

`pending_confirmation`

The provider outcome is uncertain and must be reconciled before another payment attempt is allowed.

`paid`

The payment provider has confirmed that the payout succeeded.

`failed`

The provider has definitively confirmed that the payment attempt failed.

### Rationale

Explicit states make failure handling, retries, and reconciliation predictable.

A payout must never be retried based solely on the fact that a worker failed or a request timed out.

### Consequences

- `paid` is a terminal state.
- `pending_confirmation` cannot trigger a new payment attempt before reconciliation.
- `failed` may be retried according to the payout workflow.
- Queue workers can determine the next valid action from persisted payout state.