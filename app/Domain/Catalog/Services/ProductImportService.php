<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Bulk import service for products.
 *
 * Accepts a CSV or XLSX file and a column-mapping array that maps
 * canonical product fields → CSV column index (0-based).
 *
 * Result includes per-row outcome (created / failed) and a list of
 * validation errors for the failed rows so callers can render an
 * error report.
 */
class ProductImportService
{
    public const MAX_ROWS = 10000;

    public const FIELDS = [
        'name', 'name_ar', 'sku', 'barcode', 'category_path',
        'sell_price', 'cost_price', 'unit', 'tax_rate', 'description',
    ];

    /**
     * Parse a file into rows: [['col0','col1',...], ...].
     * Skips the first row which is treated as the header.
     */
    public function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $rows = Excel::toArray(new class implements ToArray {
                public function array(array $array): array { return $array; }
            }, $file);
            $rows = $rows[0] ?? [];
        } else {
            $reader = Reader::createFromPath($file->getRealPath(), 'r');
            $reader->setHeaderOffset(null);
            $rows = iterator_to_array(
                Statement::create()->process($reader)->getRecords(),
                false,
            );
        }

        if (empty($rows)) {
            return ['header' => [], 'rows' => []];
        }

        $header = array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $rows[0]);
        $body = array_slice($rows, 1);

        return ['header' => $header, 'rows' => $body];
    }

    /**
     * Run the import.
     *
     * @param  array<string,int>  $mapping  ['name' => 0, 'sell_price' => 4, ...]
     */
    public function import(
        string $organizationId,
        UploadedFile $file,
        array $mapping,
    ): array {
        $parsed = $this->parseFile($file);
        $rows = $parsed['rows'];

        if (count($rows) > self::MAX_ROWS) {
            return [
                'created' => 0,
                'failed' => 0,
                'errors' => [],
                'message' => 'Row limit exceeded ('.self::MAX_ROWS.').',
            ];
        }

        $created = 0;
        $failed = 0;
        $errors = [];

        $categoryCache = [];

        foreach ($rows as $rowIndex => $cols) {
            $rowNumber = $rowIndex + 2; // +1 for header, +1 for 1-based

            try {
                $get = function (string $field) use ($mapping, $cols) {
                    if (!isset($mapping[$field])) {
                        return null;
                    }
                    $value = $cols[$mapping[$field]] ?? null;
                    if ($value === null || $value === '') {
                        return null;
                    }
                    return is_string($value) ? trim($value) : $value;
                };

                $name = $get('name');
                $sellPrice = $get('sell_price');

                if (empty($name) || $sellPrice === null) {
                    $failed++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Name and sell_price are required.'];
                    continue;
                }

                $payload = [
                    'organization_id' => $organizationId,
                    'name' => $name,
                    'name_ar' => $get('name_ar'),
                    'sku' => $get('sku'),
                    'barcode' => $get('barcode'),
                    'sell_price' => (float) $sellPrice,
                    'cost_price' => $get('cost_price') !== null ? (float) $get('cost_price') : null,
                    'unit' => $get('unit') ?? 'piece',
                    'tax_rate' => $get('tax_rate') !== null ? (float) $get('tax_rate') : 15.00,
                    'description' => $get('description'),
                    'is_active' => true,
                    'sync_version' => 1,
                ];

                if ($categoryPath = $get('category_path')) {
                    $payload['category_id'] = $this->resolveCategoryPath(
                        $organizationId,
                        $categoryPath,
                        $categoryCache,
                    );
                }

                DB::transaction(function () use ($payload) {
                    Product::create($payload);
                });

                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            }
        }

        return [
            'created' => $created,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Resolve "Parent > Child > Leaf" into a category id; creates missing
     * categories along the way and caches results within this import.
     */
    private function resolveCategoryPath(string $orgId, string $path, array &$cache): ?string
    {
        $cacheKey = $orgId.'|'.$path;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $parts = array_map('trim', explode('>', $path));
        $parentId = null;

        foreach ($parts as $name) {
            if ($name === '') {
                continue;
            }

            $cat = Category::where('organization_id', $orgId)
                ->where('parent_id', $parentId)
                ->where('name', $name)
                ->first();

            if (!$cat) {
                $cat = Category::create([
                    'organization_id' => $orgId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => 0,
                    'sync_version' => 1,
                ]);
            }

            $parentId = $cat->id;
        }

        return $cache[$cacheKey] = $parentId;
    }
}
