<?php

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PredefinedCategoryTemplateExport implements WithHeadings, ShouldAutoSize, WithStyles
{
    public function headings(): array
    {
        return [
            'business_type_name',
            'parent_category_name',
            'name',
            'name_ar',
            'description',
            'description_ar',
            'image_url',
            'sort_order',
            'is_active',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getComment('A1')->getText()->createTextRun('Required. Must match an existing Business Type name exactly.');
        $sheet->getComment('B1')->getText()->createTextRun('Optional. Must match an existing category name. Leave empty for top-level.');
        $sheet->getComment('C1')->getText()->createTextRun('Required. English name.');
        $sheet->getComment('D1')->getText()->createTextRun('Required. Arabic name.');
        $sheet->getComment('E1')->getText()->createTextRun('Optional. English description.');
        $sheet->getComment('F1')->getText()->createTextRun('Optional. Arabic description.');
        $sheet->getComment('G1')->getText()->createTextRun('Optional. Full image URL.');
        $sheet->getComment('H1')->getText()->createTextRun('Optional. Numeric. Default: 0.');
        $sheet->getComment('I1')->getText()->createTextRun('Optional. Yes/No. Default: Yes.');

        // Add a sample row
        $sheet->fromArray([
            ['Restaurant', '', 'Beverages', 'مشروبات', 'Hot and cold drinks', 'مشروبات ساخنة وباردة', '', '1', 'Yes'],
            ['Restaurant', 'Beverages', 'Coffee', 'قهوة', 'Coffee drinks', 'مشروبات القهوة', '', '1', 'Yes'],
        ], null, 'A2');

        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2EFDA']]],
            '2:3' => ['font' => ['color' => ['rgb' => '808080'], 'italic' => true]],
        ];
    }
}
