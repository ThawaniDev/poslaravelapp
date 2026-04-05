<?php

namespace App\Imports;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\PredefinedCatalog\Models\PredefinedCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PredefinedCategoriesImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public int $imported = 0;

    public int $skipped = 0;

    /** @var array<string> */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header is row 1

            $businessType = BusinessType::where('name', $row['business_type_name'])->first();
            if (! $businessType) {
                $this->errors[] = "Row {$rowNumber}: Business type '{$row['business_type_name']}' not found.";
                $this->skipped++;

                continue;
            }

            $parentId = null;
            if (! empty($row['parent_category_name'])) {
                $parent = PredefinedCategory::where('name', $row['parent_category_name'])
                    ->where('business_type_id', $businessType->id)
                    ->first();
                if (! $parent) {
                    $this->errors[] = "Row {$rowNumber}: Parent category '{$row['parent_category_name']}' not found.";
                    $this->skipped++;

                    continue;
                }
                $parentId = $parent->id;
            }

            PredefinedCategory::create([
                'business_type_id' => $businessType->id,
                'parent_id' => $parentId,
                'name' => $row['name'],
                'name_ar' => $row['name_ar'],
                'description' => $row['description'] ?? null,
                'description_ar' => $row['description_ar'] ?? null,
                'image_url' => $row['image_url'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => $this->toBool($row['is_active'] ?? 'yes'),
            ]);

            $this->imported++;
        }
    }

    public function rules(): array
    {
        return [
            'business_type_name' => 'required|string',
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
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
