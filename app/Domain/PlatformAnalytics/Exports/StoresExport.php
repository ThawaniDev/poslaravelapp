<?php

namespace App\Domain\PlatformAnalytics\Exports;

use App\Domain\PlatformAnalytics\Models\StoreHealthSnapshot;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StoresExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
    ) {}

    public function collection()
    {
        return StoreHealthSnapshot::with('store')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->orderBy('date')
            ->orderBy('store_id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Store Name',
            'Sync Status',
            'ZATCA Compliance',
            'Error Count',
            'Last Activity',
        ];
    }

    public function map($row): array
    {
        return [
            $row->date->format('Y-m-d'),
            $row->store?->name ?? 'Unknown',
            $row->sync_status->value ?? $row->sync_status,
            $row->zatca_compliance === true ? 'Yes' : ($row->zatca_compliance === false ? 'No' : 'N/A'),
            $row->error_count,
            $row->last_activity_at?->format('Y-m-d H:i:s') ?? 'N/A',
        ];
    }

    public function title(): string
    {
        return 'Stores ' . $this->dateFrom . ' to ' . $this->dateTo;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
