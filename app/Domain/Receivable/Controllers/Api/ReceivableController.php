<?php

namespace App\Domain\Receivable\Controllers\Api;

use App\Domain\Receivable\Requests\CreateReceivableRequest;
use App\Domain\Receivable\Requests\RecordPaymentRequest;
use App\Domain\Receivable\Requests\UpdateReceivableRequest;
use App\Domain\Receivable\Resources\ReceivableLogResource;
use App\Domain\Receivable\Resources\ReceivablePaymentResource;
use App\Domain\Receivable\Resources\ReceivableResource;
use App\Domain\Receivable\Services\ReceivableService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceivableController extends BaseApiController
{
    public function __construct(
        private readonly ReceivableService $receivableService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|uuid',
            'store_id' => 'nullable|uuid',
            'status' => 'nullable|string',
            'receivable_type' => 'nullable|string',
            'source' => 'nullable|string',
            'search' => 'nullable|string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'overdue' => 'nullable|boolean',
            'sort_by' => 'nullable|string|in:created_at,amount,status,due_date',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->receivableService->list(
            organizationId: $request->user()->organization_id,
            filters: $request->only([
                'customer_id', 'store_id', 'status', 'receivable_type',
                'source', 'search', 'date_from', 'date_to', 'overdue',
                'sort_by', 'sort_dir',
            ]),
            perPage: $request->integer('per_page', 25),
        );

        $result = $paginator->toArray();
        $result['data'] = ReceivableResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    public function store(CreateReceivableRequest $request): JsonResponse
    {
        $receivable = $this->receivableService->create(
            $request->validated(),
            $request->user(),
        );

        return $this->created(new ReceivableResource($receivable->load(['customer', 'createdBy', 'logs.actor'])));
    }

    public function show(Request $request, string $receivable): JsonResponse
    {
        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        return $this->success(new ReceivableResource($found));
    }

    public function update(UpdateReceivableRequest $request, string $receivable): JsonResponse
    {
        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        if ($found->status?->value === 'fully_paid' || $found->status?->value === 'reversed') {
            return $this->error('Cannot update a fully paid or reversed receivable.', 422);
        }

        $updated = $this->receivableService->update($found, $request->validated(), $request->user());

        return $this->success(new ReceivableResource($updated));
    }

    public function destroy(Request $request, string $receivable): JsonResponse
    {
        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        try {
            $this->receivableService->delete($found);

            return $this->success(null, 'Receivable deleted successfully.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function recordPayment(RecordPaymentRequest $request, string $receivable): JsonResponse
    {
        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        try {
            $payment = $this->receivableService->recordPayment(
                $found,
                $request->validated(),
                $request->user(),
            );

            return $this->success(
                new ReceivablePaymentResource($payment),
                'Payment recorded successfully.',
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function payments(Request $request, string $receivable): JsonResponse
    {
        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        return $this->success(
            ReceivablePaymentResource::collection($found->payments->load(['order', 'settledBy'])),
        );
    }

    public function logs(Request $request, string $receivable): JsonResponse
    {
        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        return $this->success(
            ReceivableLogResource::collection($this->receivableService->listLogs($found)),
        );
    }

    public function addNote(Request $request, string $receivable): JsonResponse
    {
        $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        $updated = $this->receivableService->addNote($found, (string) $request->input('note'), $request->user());

        return $this->success(new ReceivableResource($updated), 'Note added.');
    }

    public function reverse(Request $request, string $receivable): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $found = $this->receivableService->find($receivable);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Receivable not found.');
        }

        if ($found->status?->value === 'reversed') {
            return $this->error('Receivable is already reversed.', 422);
        }

        $reversed = $this->receivableService->reverse(
            $found,
            $request->user(),
            $request->input('reason', ''),
        );

        return $this->success(new ReceivableResource($reversed), 'Receivable reversed successfully.');
    }

    public function customerBalance(Request $request, string $customerId): JsonResponse
    {
        $balance = $this->receivableService->getCustomerReceivableBalance(
            $request->user()->organization_id,
            $customerId,
        );

        return $this->success([
            'customer_id' => $customerId,
            'receivable_balance' => (float) $balance,
        ]);
    }

    public function customerReceivables(Request $request, string $customerId): JsonResponse
    {
        $receivables = $this->receivableService->findByCustomer(
            $request->user()->organization_id,
            $customerId,
        );

        return $this->success(ReceivableResource::collection($receivables));
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->receivableService->getSummary(
            $request->user()->organization_id,
        );

        return $this->success($summary);
    }
}
