<?php

namespace App\Domain\LabelPrinting\Controllers\Api;

use App\Domain\LabelPrinting\Requests\CreateLabelTemplateRequest;
use App\Domain\LabelPrinting\Resources\LabelPrintHistoryResource;
use App\Domain\LabelPrinting\Resources\LabelTemplateResource;
use App\Domain\LabelPrinting\Services\LabelService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelController extends BaseApiController
{
    public function __construct(
        private readonly LabelService $labelService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $templates = $this->labelService->listTemplates($request->user()->organization_id);
        return $this->success(LabelTemplateResource::collection($templates));
    }

    public function presets(): JsonResponse
    {
        $presets = $this->labelService->getPresets();
        return $this->success(LabelTemplateResource::collection($presets));
    }

    public function store(CreateLabelTemplateRequest $request): JsonResponse
    {
        $template = $this->labelService->create(
            $request->validated(),
            $request->user(),
        );
        return $this->created(new LabelTemplateResource($template));
    }

    public function show(string $template): JsonResponse
    {
        $found = $this->labelService->find($template);
        return $this->success(new LabelTemplateResource($found));
    }

    public function update(Request $request, string $template): JsonResponse
    {
        $found = $this->labelService->find($template);
        if ($found->organization_id !== $request->user()->organization_id && !$found->is_preset) {
            return $this->notFound('Template not found.');
        }

        $data = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'label_width_mm'  => ['sometimes', 'numeric', 'min:20', 'max:200'],
            'label_height_mm' => ['sometimes', 'numeric', 'min:15', 'max:150'],
            'layout_json'     => ['sometimes', 'array'],
            'is_default'      => ['sometimes', 'boolean'],
        ]);

        try {
            $updated = $this->labelService->update($found, $data);
            return $this->success(new LabelTemplateResource($updated));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, string $template): JsonResponse
    {
        $found = $this->labelService->find($template);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Template not found.');
        }
        try {
            $this->labelService->delete($found);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(null, 'Template deleted successfully.');
    }

    // ─── Print History ───────────────────────────────────────

    public function printHistory(Request $request): JsonResponse
    {
        $paginator = $this->labelService->getPrintHistory($request->user()->store_id);
        $result = $paginator->toArray();
        $result['data'] = LabelPrintHistoryResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function recordPrint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id'   => ['required', 'uuid'],
            'product_count' => ['required', 'integer', 'min:1'],
            'total_labels'  => ['required', 'integer', 'min:1'],
            'printer_name'  => ['nullable', 'string', 'max:255'],
        ]);

        $data['store_id'] = $request->user()->store_id;
        $data['printed_by'] = $request->user()->id;

        $history = $this->labelService->recordPrintHistory($data);
        return $this->created(new LabelPrintHistoryResource($history));
    }
}
