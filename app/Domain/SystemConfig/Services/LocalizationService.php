<?php

namespace App\Domain\SystemConfig\Services;

use App\Domain\SystemConfig\Enums\TranslationCategory;
use App\Domain\SystemConfig\Models\MasterTranslationString;
use App\Domain\SystemConfig\Models\SupportedLocale;
use App\Domain\SystemConfig\Models\TranslationOverride;
use App\Domain\SystemConfig\Models\TranslationVersion;
use Illuminate\Support\Facades\DB;

class LocalizationService
{
    /**
     * List supported locales, optionally filtered by active status.
     */
    public function listLocales(?bool $activeOnly = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = SupportedLocale::query();

        if ($activeOnly !== null) {
            $query->where('is_active', $activeOnly);
        }

        return $query->orderByDesc('is_default')->orderBy('locale_code')->get();
    }

    /**
     * Save (create or update) a supported locale.
     */
    public function saveLocale(array $data): SupportedLocale
    {
        return DB::transaction(function () use ($data) {
            // If setting as default, unset others
            if (!empty($data['is_default'])) {
                SupportedLocale::where('is_default', true)->update(['is_default' => false]);
            }

            return SupportedLocale::updateOrCreate(
                ['locale_code' => $data['locale_code']],
                $data,
            );
        });
    }

    /**
     * Get translations for a locale with optional category and search filters.
     */
    public function getTranslations(
        string $locale,
        ?string $category = null,
        ?string $search = null,
        ?string $storeId = null,
        int $perPage = 50,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = MasterTranslationString::query();

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('string_key', 'like', "%{$search}%")
                    ->orWhere('value_en', 'like', "%{$search}%")
                    ->orWhere('value_ar', 'like', "%{$search}%");
            });
        }

        $paginated = $query->orderBy('category')->orderBy('string_key')->paginate($perPage);

        // Attach store overrides if storeId provided
        if ($storeId) {
            $keys = $paginated->pluck('string_key')->toArray();
            $overrides = TranslationOverride::where('store_id', $storeId)
                ->where('locale', $locale)
                ->whereIn('string_key', $keys)
                ->pluck('custom_value', 'string_key')
                ->toArray();

            $paginated->getCollection()->transform(function ($item) use ($overrides) {
                $item->setAttribute('override_value', $overrides[$item->string_key] ?? null);
                return $item;
            });
        }

        return $paginated;
    }

    /**
     * Save (create or update) a master translation string.
     */
    public function saveTranslation(array $data): MasterTranslationString
    {
        return MasterTranslationString::updateOrCreate(
            ['string_key' => $data['string_key']],
            $data,
        );
    }

    /**
     * Bulk import translation strings.
     */
    public function bulkImportTranslations(array $translations): int
    {
        $count = 0;
        DB::transaction(function () use ($translations, &$count) {
            foreach ($translations as $row) {
                MasterTranslationString::updateOrCreate(
                    ['string_key' => $row['string_key']],
                    $row,
                );
                $count++;
            }
        });

        return $count;
    }

    /**
     * Get store-specific translation overrides.
     */
    public function getOverrides(string $storeId, ?string $locale = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = TranslationOverride::where('store_id', $storeId);

        if ($locale) {
            $query->where('locale', $locale);
        }

        return $query->orderBy('string_key')->get();
    }

    /**
     * Save a store-specific translation override.
     */
    public function saveOverride(array $data): TranslationOverride
    {
        return TranslationOverride::updateOrCreate(
            [
                'store_id' => $data['store_id'],
                'string_key' => $data['string_key'],
                'locale' => $data['locale'],
            ],
            ['custom_value' => $data['custom_value'], 'updated_at' => now()],
        );
    }

    /**
     * Remove a store-specific translation override.
     */
    public function removeOverride(string $overrideId): bool
    {
        return (bool) TranslationOverride::where('id', $overrideId)->delete();
    }

    /**
     * Publish a new translation version snapshot.
     */
    public function publishVersion(string $publishedBy, ?string $notes = null): TranslationVersion
    {
        $allStrings = MasterTranslationString::orderBy('string_key')->get(['string_key', 'value_en', 'value_ar']);
        $hash = hash('sha256', $allStrings->toJson());

        return TranslationVersion::create([
            'version_hash' => $hash,
            'published_at' => now(),
            'published_by' => $publishedBy,
            'notes' => $notes,
        ]);
    }

    /**
     * List translation versions.
     */
    public function listVersions(int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return TranslationVersion::orderByDesc('published_at')->paginate($perPage);
    }

    /**
     * Export translations for a locale as a flat key→value map (for client sync).
     */
    public function exportTranslations(string $locale, ?string $storeId = null): array
    {
        $strings = MasterTranslationString::all();
        $field = $locale === 'ar' ? 'value_ar' : 'value_en';

        $map = [];
        foreach ($strings as $s) {
            $map[$s->string_key] = $s->{$field};
        }

        // Apply store overrides on top
        if ($storeId) {
            $overrides = TranslationOverride::where('store_id', $storeId)
                ->where('locale', $locale)
                ->pluck('custom_value', 'string_key')
                ->toArray();
            $map = array_merge($map, $overrides);
        }

        return $map;
    }
}
