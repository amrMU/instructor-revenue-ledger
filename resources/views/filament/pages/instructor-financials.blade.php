<x-filament-panels::page>
    <x-filament::section>
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $instructor->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $instructor->email }}</p>
        </div>
    </x-filament::section>

    <div class="grid gap-4 md:grid-cols-3" aria-label="Instructor balance">
        @foreach ([
            'Total earned' => $balance->totalEarned,
            'Total paid' => $balance->totalPaid,
            'Outstanding balance' => $balance->outstandingBalance,
        ] as $label => $amount)
            <x-filament::section>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($amount / 100, 2) }} EGP</p>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Payout history">
        @if ($payouts->isEmpty())
            <p class="text-sm text-gray-500">No payouts have been created yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700 dark:text-gray-200">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400"><tr class="border-b border-gray-200 dark:border-white/10"><th class="p-3">Reference</th><th class="p-3">Amount</th><th class="p-3">Status</th><th class="p-3">Created</th><th class="p-3">Paid</th></tr></thead>
                    <tbody>
                    @foreach ($payouts as $payout)
                        <tr class="border-b border-gray-200 last:border-0 dark:border-white/10">
                            <td class="p-3">#{{ $payout->id }}</td>
                            <td class="p-3">{{ number_format($payout->amount_minor / 100, 2) }} EGP</td>
                            <td class="p-3">{{ str($payout->status->value)->replace('_', ' ')->title() }}</td>
                            <td class="p-3">{{ $payout->created_at->format('Y-m-d H:i') }}</td>
                            <td class="p-3">{{ $payout->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
