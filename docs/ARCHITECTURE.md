# Implementation Architecture

The authoritative business decisions remain in [`architecture_desion.md`](architecture_desion.md). This file describes how the implementation realizes them; it does not replace or amend them.

## System at a glance

The financial flow starts when a student buys a plan and ends when the provider confirms an instructor payout:

```text
Student
  └── Subscription ─────────────── belongs to ───────────────> Plan
        ├── Subscription Payment (full term paid upfront)
        └── Earning Periods (1, 3, or 12 anchored periods)
              └── Completed period
                    └── Instructor Ledger Entries (earned money)
                          └── Payout Items (exact reserved entries)
                                └── Payout (fixed amount and state)
                                      └── Provider Transaction
                                            └── success / failure / reconciliation
```

The instructor side of the same relationship is:

```text
Instructor
  ├── Ledger Entries ── source of total earned
  └── Payouts
        ├── Payout Items ── exact ledger entries included in the payout
        └── Provider Transaction ── external operation and reconciliation record
```

`PayoutItem` is the reservation link between a payout and a ledger entry. Its unique `ledger_entry_id` prevents the same earning from entering two payouts.

## Money flow

1. The student pays the full subscription price upfront in one `SubscriptionPayment`.
2. `GenerateEarningPeriodsService` distributes that payment across monthly periods anchored to the subscription start date.
3. When a period ends, the upstream LMS supplies the applicable revenue-share percentage and instructor IDs.
4. `CompleteEarningPeriodService` snapshots that percentage, calculates the platform and instructor amounts, and creates one earning ledger entry per instructor.
5. The instructor balance is derived from the ledger: earned is the signed ledger total, paid is the value of items in paid payouts, and outstanding is earned minus paid.
6. `CreatePayoutService` locks and reserves exact eligible ledger entries, then creates a payout with a fixed amount and stable idempotency key.
7. `ProcessPayoutJob` asks `ProcessPayoutService` to submit that fixed payout through `PaymentProvider`.
8. A confirmed success marks the payout paid. A confirmed failure marks it failed. A timeout moves it to `pending_confirmation` until reconciliation checks the original provider operation.

This separates three different facts that must not be confused:

- `SubscriptionPayment` records cash collected from the student.
- `InstructorLedgerEntry` records money earned by an instructor.
- A paid `Payout` records money confirmed as transferred to that instructor.

## Boundaries

Entry points (commands, queued jobs, and the Filament page) call explicit services. Services own financial calculations, transactions, and state transitions. Repositories own persistence, locking, and bounded retrieval. Only `PaymentProvider` crosses the external provider boundary.

```text
Command / Job / UI → Service → Repository → Database
                              ↘ PaymentProvider → MockPaymentProvider
```

## Revenue and ledger flow

One upfront payment is divided into 1, 3, or 12 periods anchored to the subscription start. Integer division is used; because the ADR does not designate a duration-remainder period, the final period receives that remainder. Completing a period snapshots the instructor share in basis points and the instructor set through its ledger entries. The pool is split equally; indivisible minor units are persisted as the platform rounding adjustment. The database requires completed-period platform, distributed-pool, and rounding values to conserve the gross value.

Ledger entries are append-only earnings, refund adjustments, or corrections. Balances are derived as net ledger value, paid payout-item value, and their difference. Refunds preserve completed periods, cancel open periods, prorate the unused term, and append any required correction.

## Payout safety

Payout creation locks the instructor row and eligible ledger rows in one short transaction. Exact entries are linked through `payout_items`; a unique `ledger_entry_id` is the final database guard against double reservation. The amount and UUID idempotency key never change.

Processing atomically transitions only `pending` or confirmed `failed` payouts to `processing` and persists the provider operation before the provider call. Duplicate jobs cannot transition the same payout again. `paid` is terminal. Provider calls happen outside database locks.

A timeout becomes `pending_confirmation` and dispatches reconciliation. Neither duplicate processing nor a pending-confirmation payout can transfer again. Reconciliation uses the original key: confirmed success marks the payout paid; confirmed failure marks it failed. `processing` records are also reconciliation candidates, covering a worker crash after submission but before local confirmation.

## Scale

Eligibility, status, instructor, period, and provider lookup paths are indexed. Commands scan ordered instructor/payout IDs in bounded batches, while payout reservation uses focused row locks. Scheduled workflows never call `all()` or load the complete ledger.

## Known limitations

- The provider is a challenge mock, not a real banking integration.
- The upstream LMS must supply the period's share and instructor IDs.
- A video URL and test screenshot are submission artifacts and are not generated by application code.
