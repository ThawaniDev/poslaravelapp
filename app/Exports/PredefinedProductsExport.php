<?php

namespace App\Exports;

use App\Domain\PredefinedCatalog\Models\PredefinedProduct;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PredefinedProductsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function query()
    {
        return PredefinedProduct::query()
            ->with(['businessType', 'predefinedCategory'])
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Business Type',
            'Category',
            'Name (EN)',
            'Name (AR)',
            'Description (EN)',
            'Description (AR)',
            'SKU',
            'Barcode',
            'Sell Price',
            'Cost Price',
            'Tax Rate %',
            'Unit',
            'Weighable',
            'Tare Weight',
            'Active',
            'Age Restricted',
            'Image URL',
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->businessType?->name,
            $product->predefinedCategory?->name,
            $product->name,
            $product->name_ar,
            $product->description,
            $product->description_ar,
            $product->sku,
            $product->barcode,
            $product->sell_price,
            $product->cost_price,
            $product->tax_rate,
            $product->unit?->value,
            $product->is_weighable ? 'Yes' : 'No',
            $product->tare_weight,
            $product->is_active ? 'Yes' : 'No',
            $product->age_restricted ? 'Yes' : 'No',
            $product->image_url,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
