<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\InstructorFinancialSummaryService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class InstructorFinancials extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financials';

    protected static ?string $navigationLabel = 'Balance & payouts';

    protected static ?string $slug = '/';

    protected static string $view = 'filament.pages.instructor-financials';

    public function getTitle(): string|Htmlable
    {
        return 'Instructor financials';
    }

    public function getViewData(): array
    {
        $instructorId = (int) auth()->id();

        return app(InstructorFinancialSummaryService::class)->forInstructor($instructorId);
    }
}
