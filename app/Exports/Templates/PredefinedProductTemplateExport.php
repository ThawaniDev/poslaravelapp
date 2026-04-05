<?php

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PredefinedProductTemplateExport implements WithHeadings, ShouldAutoSize, WithStyles
{
    public function headings(): array
    {
        return [
            'business_type_name',
            'category_name',
            'name',
            'name_ar',
            'description',
            'description_ar',
            'sku',
            'barcode',
            'sell_price',
            'cost_price',
            'tax_rate',
            'unit',
            'is_weighable',
            'tare_weight',
            'is_active',
            'age_restricted',
            'image_url',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getComment('A1')->getText()->createTextRun('Required. Must match an existing Business Type name.');
        $sheet->getComment('B1')->getText()->createTextRun('Required. Must match an existing Predefined Category name.');
        $sheet->getComment('C1')->getText()->createTextRun('Required. English product name.');
        $sheet->getComment('D1')->getText()->createTextRun('Required. Arabic product name.');
        $sheet->getComment('E1')->getText()->createTextRun('Optional. English description.');
        $sheet->getComment('F1')->getText()->createTextRun('Optional. Arabic description.');
        $sheet->getComment('G1')->getText()->createTextRun('Optional. Stock Keeping Unit code.');
        $sheet->getComment('H1')->getText()->createTextRun('Optional. Barcode number.');
        $sheet->getComment('I1')->getText()->createTextRun('Required. Numeric price in SAR (e.g. 12.50).');
        $sheet->getComment('J1')->getText()->createTextRun('Optional. Numeric cost price in SAR.');
        $sheet->getComment('K1')->getText()->createTextRun('Optional. Tax percentage (e.g. 15). Default: 15.');
        $sheet->getComment('L1')->getText()->createTextRun('Optional. piece / kg / litre / custom. Default: piece.');
        $sheet->getComment('M1')->getText()->createTextRun('Optional. Yes/No. Default: No.');
        $sheet->getComment('N1')->getText()->createTextRun('Optional. Numeric tare weight in kg. Default: 0.');
        $sheet->getComment('O1')->getText()->createTextRun('Optional. Yes/No. Default: Yes.');
        $sheet->getComment('P1')->getText()->createTextRun('Optional. Yes/No. Default: No.');
        $sheet->getComment('Q1')->getText()->createTextRun('Optional. Full image URL.');

        // Add sample rows
        $sheet->fromArray([
            ['Restaurant', 'Beverages', 'Cappuccino', 'كابتشينو', 'Classic cappuccino', 'كابتشينو كلاسيكي', 'BEV-001', '', '12.00', '4.00', '15', 'piece', 'No', '0', 'Yes', 'No', ''],
            ['Restaurant', 'Beverages', 'Orange Juice', 'عصير برتقال', 'Fresh orange juice', 'عصير برتقال طازج', 'BEV-002', '', '8.50', '3.00', '15', 'litre', 'No', '0', 'Yes', 'No', ''],
        ], null, 'A2');

        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2EFDA']]],
            '2:3' => ['font' => ['color' => ['rgb' => '808080'], 'italic' => true]],
        ];
    }
}
