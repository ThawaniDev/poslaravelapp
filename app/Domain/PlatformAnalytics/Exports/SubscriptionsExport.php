<?php

namespace App\Domain\PlatformAnalytics\Exports;

use App\Domain\PlatformAnalytics\Models\PlatformPlanStat;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubscriptionsExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
    ) {}

    public function collection()
    {
        return PlatformPlanStat::with('subscriptionPlan')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->orderBy('date')
            ->orderBy('subscription_plan_id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Plan Name',
            'Active Subscriptions',
            'Trial Subscriptions',
            'Churned',
            'MRR (SAR)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->date->format('Y-m-d'),
            $row->subscriptionPlan?->name ?? 'Unknown',
            $row->active_count,
            $row->trial_count,
            $row->churned_count,
            number_format((float) $row->mrr, 2),
        ];
    }

    public function title(): string
    {
        return 'Subscriptions ' . $this->dateFrom . ' to ' . $this->dateTo;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
