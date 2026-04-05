<?php

namespace App\Exports;

use App\Domain\PredefinedCatalog\Models\PredefinedCategory;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PredefinedCategoriesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function query()
    {
        return PredefinedCategory::query()
            ->with(['businessType', 'parent'])
            ->orderBy('sort_order');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Business Type',
            'Parent Category',
            'Name (EN)',
            'Name (AR)',
            'Description (EN)',
            'Description (AR)',
            'Image URL',
            'Sort Order',
            'Active',
        ];
    }

    public function map($category): array
    {
        return [
            $category->id,
            $category->businessType?->name,
            $category->parent?->name,
            $category->name,
            $category->name_ar,
            $category->description,
            $category->description_ar,
            $category->image_url,
            $category->sort_order,
            $category->is_active ? 'Yes' : 'No',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
