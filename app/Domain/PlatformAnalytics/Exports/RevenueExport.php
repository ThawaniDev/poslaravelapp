<?php

namespace App\Domain\PlatformAnalytics\Exports;

use App\Domain\PlatformAnalytics\Models\PlatformDailyStat;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevenueExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
    ) {}

    public function collection()
    {
        return PlatformDailyStat::whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->orderBy('date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'MRR (SAR)',
            'GMV (SAR)',
            'Active Stores',
            'New Registrations',
            'Total Orders',
            'Churn Count',
        ];
    }

    public function map($row): array
    {
        return [
            $row->date->format('Y-m-d'),
            number_format((float) $row->total_mrr, 2),
            number_format((float) $row->total_gmv, 2),
            $row->total_active_stores,
            $row->new_registrations,
            $row->total_orders,
            $row->churn_count,
        ];
    }

    public function title(): string
    {
        return 'Revenue ' . $this->dateFrom . ' to ' . $this->dateTo;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
