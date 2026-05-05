<?php

namespace App\Domain\LabelPrinting\Controllers\Api;

use App\Domain\LabelPrinting\Requests\CreateLabelTemplateRequest;
use App\Domain\LabelPrinting\Requests\UpdateLabelTemplateRequest;
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
        $templates = $this->labelService->listTemplates(
            $request->user()->organization_id,
            $request->only(['search', 'type', 'is_default']),
        )->load('createdBy');
        return $this->success(LabelTemplateResource::collection($templates));
    }

    public function presets(Request $request): JsonResponse
    {
        $presets = $this->labelService->getPresets($request->user()->organization_id)
            ->load('createdBy');
        return $this->success(LabelTemplateResource::collection($presets));
    }

    public function store(CreateLabelTemplateRequest $request): JsonResponse
    {
        $template = $this->labelService->create(
            $request->validated(),
            $request->user(),
        );
        $template->load('createdBy');
        return $this->created(new LabelTemplateResource($template));
    }

    public function show(Request $request, string $template): JsonResponse
    {
        $found = $this->labelService->findForOrg($template, $request->user()->organization_id);
        $found->load('createdBy');
        return $this->success(new LabelTemplateResource($found));
    }

    public function update(UpdateLabelTemplateRequest $request, string $template): JsonResponse
    {
        $found = $this->labelService->findForOrg($template, $request->user()->organization_id);

        try {
            $updated = $this->labelService->update($found, $request->validated());
            $updated->load('createdBy');
            return $this->success(new LabelTemplateResource($updated));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, string $template): JsonResponse
    {
        $found = $this->labelService->findForOrg($template, $request->user()->organization_id);
        try {
            $this->labelService->delete($found);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(null, 'Template deleted successfully.');
    }

    public function duplicate(Request $request, string $template): JsonResponse
    {
        $found = $this->labelService->findForOrg($template, $request->user()->organization_id);
        $copy = $this->labelService->duplicate($found, $request->user());
        $copy->load('createdBy');
        return $this->created(new LabelTemplateResource($copy));
    }

    public function setDefault(Request $request, string $template): JsonResponse
    {
        $found = $this->labelService->findForOrg($template, $request->user()->organization_id);
        $updated = $this->labelService->setDefault($found, $request->user()->organization_id);
        $updated->load('createdBy');
        return $this->success(new LabelTemplateResource($updated));
    }

    // ─── Print History ───────────────────────────────────────

    public function printHistory(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;

        $filters = $request->validate([
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date'],
            'template_id' => ['nullable', 'uuid'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $paginator = $this->labelService->getPrintHistory($storeId, $perPage, $filters);
        $paginator->load(['template', 'printedBy']);
        $result = $paginator->toArray();
        $result['data'] = LabelPrintHistoryResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function printHistoryStats(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        return $this->success($this->labelService->printHistoryStats($storeId));
    }

    public function recordPrint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id'      => ['nullable', 'uuid'],
            'product_count'    => ['required', 'integer', 'min:1'],
            'total_labels'     => ['required', 'integer', 'min:1'],
            'printer_name'     => ['nullable', 'string', 'max:255'],
            'printer_language' => ['nullable', 'string', 'in:zpl,tspl,escpos,image'],
            'job_pages'        => ['nullable', 'integer', 'min:1'],
            'duration_ms'      => ['nullable', 'integer', 'min:0'],
        ]);

        $data['store_id'] = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $data['printed_by'] = $request->user()->id;

        $history = $this->labelService->recordPrintHistory($data);
        $history->load(['template', 'printedBy']);
        return $this->created(new LabelPrintHistoryResource($history));
    }
}
