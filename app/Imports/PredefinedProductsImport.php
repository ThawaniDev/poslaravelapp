<?php

namespace App\Imports;

use App\Domain\Catalog\Enums\ProductUnit;
use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\PredefinedCatalog\Models\PredefinedCategory;
use App\Domain\PredefinedCatalog\Models\PredefinedProduct;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PredefinedProductsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public int $imported = 0;

    public int $skipped = 0;

    /** @var array<string> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $businessType = BusinessType::where('name', $row['business_type_name'])->first();
            if (! $businessType) {
                $this->errors[] = "Row {$rowNumber}: Business type '{$row['business_type_name']}' not found.";
                $this->skipped++;

                continue;
            }

            $category = PredefinedCategory::where('name', $row['category_name'])
                ->where('business_type_id', $businessType->id)
                ->first();
            if (! $category) {
                $this->errors[] = "Row {$rowNumber}: Category '{$row['category_name']}' not found for business type '{$row['business_type_name']}'.";
                $this->skipped++;

                continue;
            }

            $unitValue = strtolower(trim($row['unit'] ?? 'piece'));
            $unit = ProductUnit::tryFrom($unitValue) ?? ProductUnit::Piece;

            PredefinedProduct::create([
                'business_type_id' => $businessType->id,
                'predefined_category_id' => $category->id,
                'name' => $row['name'],
                'name_ar' => $row['name_ar'],
                'description' => $row['description'] ?? null,
                'description_ar' => $row['description_ar'] ?? null,
                'sku' => $row['sku'] ?? null,
                'barcode' => $row['barcode'] ?? null,
                'sell_price' => (float) $row['sell_price'],
                'cost_price' => ! empty($row['cost_price']) ? (float) $row['cost_price'] : null,
                'tax_rate' => (float) ($row['tax_rate'] ?? 15),
                'unit' => $unit,
                'is_weighable' => $this->toBool($row['is_weighable'] ?? 'no'),
                'tare_weight' => (float) ($row['tare_weight'] ?? 0),
                'is_active' => $this->toBool($row['is_active'] ?? 'yes'),
                'age_restricted' => $this->toBool($row['age_restricted'] ?? 'no'),
                'image_url' => $row['image_url'] ?? null,
            ]);

            $this->imported++;
        }
    }

    public function rules(): array
    {
        return [
            'business_type_name' => 'required|string',
            'category_name' => 'required|string',
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'sell_price' => 'required|numeric|min:0',
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['yes', '1', 'true'], true);
    }
}
