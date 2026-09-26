<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $checks = [
        'plans_valid_duration' => 'duration_months in (1, 3, 12) and price_minor > 0',
        'subscriptions_valid_term' => 'ends_at > starts_at',
        'subscription_payments_valid_amounts' => 'amount_minor > 0 and refunded_amount_minor >= 0 and refunded_amount_minor <= amount_minor',
        'earning_periods_valid_amounts' => "period_end > period_start and (instructor_share_basis_points is null or instructor_share_basis_points <= 10000) and (status <> 'completed' or platform_amount_minor + instructor_pool_minor + rounding_adjustment_minor = gross_amount_minor)",
        'instructor_ledger_entries_nonzero' => 'amount_minor <> 0',
        'payouts_positive' => 'amount_minor > 0',
        'payout_items_nonzero' => 'amount_minor <> 0',
    ];

    /** @var array<string, string> */
    private array $tables = [
        'plans_valid_duration' => 'plans',
        'subscriptions_valid_term' => 'subscriptions',
        'subscription_payments_valid_amounts' => 'subscription_payments',
        'earning_periods_valid_amounts' => 'earning_periods',
        'instructor_ledger_entries_nonzero' => 'instructor_ledger_entries',
        'payouts_positive' => 'payouts',
        'payout_items_nonzero' => 'payout_items',
    ];

    /** @var array<string, string> */
    private array $sqliteChecks = [
        'plans_valid_duration' => 'NEW.duration_months in (1, 3, 12) and NEW.price_minor > 0',
        'subscriptions_valid_term' => 'NEW.ends_at > NEW.starts_at',
        'subscription_payments_valid_amounts' => 'NEW.amount_minor > 0 and NEW.refunded_amount_minor >= 0 and NEW.refunded_amount_minor <= NEW.amount_minor',
        'earning_periods_valid_amounts' => "NEW.period_end > NEW.period_start and (NEW.instructor_share_basis_points is null or NEW.instructor_share_basis_points <= 10000) and (NEW.status <> 'completed' or NEW.platform_amount_minor + NEW.instructor_pool_minor + NEW.rounding_adjustment_minor = NEW.gross_amount_minor)",
        'instructor_ledger_entries_nonzero' => 'NEW.amount_minor <> 0',
        'payouts_positive' => 'NEW.amount_minor > 0',
        'payout_items_nonzero' => 'NEW.amount_minor <> 0',
    ];

    public function up(): void
    {
        foreach ($this->checks as $name => $expression) {
            $table = $this->tables[$name];
            if (DB::getDriverName() === 'sqlite') {
                foreach (['insert', 'update'] as $event) {
                    $sqliteExpression = $this->sqliteChecks[$name];
                    DB::statement("CREATE TRIGGER {$name}_{$event} BEFORE ".strtoupper($event)." ON {$table} WHEN NOT ({$sqliteExpression}) BEGIN SELECT RAISE(ABORT, 'check constraint {$name} failed'); END");
                }
            } else {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
            }
        }

        $immutableGuards = [
            'ledger_entries_no_update' => ['instructor_ledger_entries', 'UPDATE', '1 = 1'],
            'ledger_entries_no_delete' => ['instructor_ledger_entries', 'DELETE', '1 = 1'],
            'payout_items_no_update' => ['payout_items', 'UPDATE', '1 = 1'],
            'payout_items_no_delete' => ['payout_items', 'DELETE', '1 = 1'],
            'completed_periods_no_update' => ['earning_periods', 'UPDATE', "OLD.status = 'completed'"],
            'completed_periods_no_delete' => ['earning_periods', 'DELETE', "OLD.status = 'completed'"],
            'paid_payouts_no_update' => ['payouts', 'UPDATE', "OLD.status = 'paid'"],
            'payouts_no_delete' => ['payouts', 'DELETE', '1 = 1'],
        ];

        if (DB::getDriverName() === 'sqlite') {
            foreach ($immutableGuards as $name => [$table, $event, $condition]) {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'immutable financial record'); END");
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'ledger_entries_no_update',
            'ledger_entries_no_delete',
            'payout_items_no_update',
            'payout_items_no_delete',
            'completed_periods_no_update',
            'completed_periods_no_delete',
            'paid_payouts_no_update',
            'payouts_no_delete',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        foreach ($this->checks as $name => $expression) {
            $table = $this->tables[$name];
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$name}_insert");
                DB::statement("DROP TRIGGER IF EXISTS {$name}_update");
            } else {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$name}");
            }
        }
    }
};
