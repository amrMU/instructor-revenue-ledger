# AI Usage

AI (Codex) was used in this repository to accelerate the one-shot implementation, boilerplate generation, test construction, command-line verification, and final code review.

The workflow was:

1. Read the existing `architecture_desion.md` and `TECHNICAL_GUIDELINES.md` in full.
2. Inspected the Laravel starter, draft migrations, dependencies, and Docker environment.
3. Generated and adapted migrations, enums, models, repositories, services, provider boundary, jobs, commands, factories, seeding, Filament UI, and Pest tests.
4. Ran clean migrations, seeders, commands, provider scenarios, formatting checks, and the full suite; failures were reviewed and corrected.

All business and financial decisions came from the existing authoritative architecture document. The documented Controller/Command/Job → Service → Repository layering, integer-money rules, immutable history, payout snapshot, stable idempotency identity, and timeout reconciliation design were preserved.

Generated code was executed, reviewed, and adapted against database behavior and test results rather than accepted without verification. The main intentional trade-off is a compact challenge-focused implementation: instructor discovery and production provider integration remain external, and no unsupported LMS or infrastructure features were introduced. Where the ADR omitted the destination of a payment-duration remainder, the implementation uses the final earning period and calls out that narrow assumption in project documentation.
