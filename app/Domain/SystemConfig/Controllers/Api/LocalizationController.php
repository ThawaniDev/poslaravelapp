<?php

namespace App\Domain\SystemConfig\Controllers\Api;

use App\Domain\SystemConfig\Requests\BulkImportTranslationsRequest;
use App\Domain\SystemConfig\Requests\PublishVersionRequest;
use App\Domain\SystemConfig\Requests\SaveLocaleRequest;
use App\Domain\SystemConfig\Requests\SaveOverrideRequest;
use App\Domain\SystemConfig\Requests\SaveTranslationRequest;
use App\Domain\SystemConfig\Requests\TranslationFilterRequest;
use App\Domain\SystemConfig\Services\LocalizationService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalizationController extends BaseApiController
{
    public function __construct(private readonly LocalizationService $service) {}

    /**
     * GET /settings/locales
     */
    public function listLocales(Request $request): JsonResponse
    {
        $activeOnly = $request->has('active_only') ? $request->boolean('active_only') : null;
        $locales = $this->service->listLocales($activeOnly);

        return $this->success($locales, __('localization.locales_listed'));
    }

    /**
     * POST /settings/locales
     */
    public function saveLocale(SaveLocaleRequest $request): JsonResponse
    {
        $locale = $this->service->saveLocale($request->validated());

        return $this->success($locale, __('localization.locale_saved'));
    }

    /**
     * GET /settings/translations
     */
    public function getTranslations(TranslationFilterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $translations = $this->service->getTranslations(
            locale: $data['locale'],
            category: $data['category'] ?? null,
            search: $data['search'] ?? null,
            storeId: $data['store_id'] ?? null,
            perPage: $data['per_page'] ?? 50,
        );

        return $this->success($translations, __('localization.translations_listed'));
    }

    /**
     * POST /settings/translations
     */
    public function saveTranslation(SaveTranslationRequest $request): JsonResponse
    {
        $translation = $this->service->saveTranslation($request->validated());

        return $this->success($translation, __('localization.translation_saved'));
    }

    /**
     * POST /settings/translations/bulk-import
     */
    public function bulkImport(BulkImportTranslationsRequest $request): JsonResponse
    {
        $count = $this->service->bulkImportTranslations($request->validated()['translations']);

        return $this->success(['imported' => $count], __('localization.bulk_imported'));
    }

    /**
     * GET /settings/translation-overrides
     */
    public function getOverrides(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => ['required', 'uuid'],
            'locale' => ['nullable', 'string', 'max:5'],
        ]);

        $overrides = $this->service->getOverrides(
            storeId: $request->input('store_id'),
            locale: $request->input('locale'),
        );

        return $this->success($overrides, __('localization.overrides_listed'));
    }

    /**
     * POST /settings/translation-overrides
     */
    public function saveOverride(SaveOverrideRequest $request): JsonResponse
    {
        $override = $this->service->saveOverride($request->validated());

        return $this->success($override, __('localization.override_saved'));
    }

    /**
     * DELETE /settings/translation-overrides/{id}
     */
    public function removeOverride(string $id): JsonResponse
    {
        $this->service->removeOverride($id);

        return $this->success(null, __('localization.override_removed'));
    }

    /**
     * POST /settings/publish-translations
     */
    public function publishVersion(PublishVersionRequest $request): JsonResponse
    {
        $version = $this->service->publishVersion(
            publishedBy: $request->user()->id,
            notes: $request->validated()['notes'] ?? null,
        );

        return $this->success($version, __('localization.version_published'));
    }

    /**
     * GET /settings/translation-versions
     */
    public function listVersions(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 20);
        $versions = $this->service->listVersions($perPage);

        return $this->success($versions, __('localization.versions_listed'));
    }

    /**
     * GET /settings/export-translations
     */
    public function exportTranslations(Request $request): JsonResponse
    {
        $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'store_id' => ['nullable', 'uuid'],
        ]);

        $map = $this->service->exportTranslations(
            locale: $request->input('locale'),
            storeId: $request->input('store_id'),
        );

        return $this->success($map, __('localization.translations_exported'));
    }
}
