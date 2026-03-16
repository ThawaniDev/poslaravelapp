<?php

namespace App\Domain\Shared\Controllers\Api;

use App\Domain\Shared\Requests\UpdateAccessibilityRequest;
use App\Domain\Shared\Services\AccessibilityService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessibilityController extends BaseApiController
{
    public function __construct(private AccessibilityService $service) {}

    public function getPreferences(Request $request): JsonResponse
    {
        $prefs = $this->service->getPreferences($request->user()->id);

        return $this->success($prefs, __('accessibility.loaded'));
    }

    public function updatePreferences(UpdateAccessibilityRequest $request): JsonResponse
    {
        $prefs = $this->service->updatePreferences(
            $request->user()->id,
            $request->validated(),
        );

        return $this->success($prefs, __('accessibility.updated'));
    }

    public function resetPreferences(Request $request): JsonResponse
    {
        $prefs = $this->service->resetPreferences($request->user()->id);

        return $this->success($prefs, __('accessibility.reset'));
    }

    public function getShortcuts(Request $request): JsonResponse
    {
        $shortcuts = $this->service->getShortcuts($request->user()->id);

        return $this->success($shortcuts, __('accessibility.shortcuts_loaded'));
    }

    public function updateShortcuts(Request $request): JsonResponse
    {
        $request->validate([
            'shortcuts' => ['required', 'array'],
            'shortcuts.*' => ['string', 'max:30'],
        ]);

        $prefs = $this->service->updateShortcuts(
            $request->user()->id,
            $request->input('shortcuts'),
        );

        return $this->success($prefs, __('accessibility.shortcuts_updated'));
    }
}
