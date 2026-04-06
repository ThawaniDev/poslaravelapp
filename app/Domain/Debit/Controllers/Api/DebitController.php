<?php

namespace App\Domain\Debit\Controllers\Api;

use App\Domain\Debit\Requests\AllocateDebitRequest;
use App\Domain\Debit\Requests\CreateDebitRequest;
use App\Domain\Debit\Requests\UpdateDebitRequest;
use App\Domain\Debit\Resources\DebitAllocationResource;
use App\Domain\Debit\Resources\DebitResource;
use App\Domain\Debit\Services\DebitService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebitController extends BaseApiController
{
    public function __construct(
        private readonly DebitService $debitService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|uuid',
            'store_id' => 'nullable|uuid',
            'status' => 'nullable|string',
            'debit_type' => 'nullable|string',
            'source' => 'nullable|string',
            'search' => 'nullable|string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort_by' => 'nullable|string|in:created_at,amount,status',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->debitService->list(
            organizationId: $request->user()->organization_id,
            filters: $request->only([
                'customer_id', 'store_id', 'status', 'debit_type',
                'source', 'search', 'date_from', 'date_to',
                'sort_by', 'sort_dir',
            ]),
            perPage: $request->integer('per_page', 25),
        );

        $result = $paginator->toArray();
        $result['data'] = DebitResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    public function store(CreateDebitRequest $request): JsonResponse
    {
        $debit = $this->debitService->create(
            $request->validated(),
            $request->user(),
        );

        return $this->created(new DebitResource($debit->load(['customer', 'createdBy'])));
    }

    public function show(Request $request, string $debit): JsonResponse
    {
        $found = $this->debitService->find($debit);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Debit not found.');
        }

        return $this->success(new DebitResource($found));
    }

    public function update(UpdateDebitRequest $request, string $debit): JsonResponse
    {
        $found = $this->debitService->find($debit);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Debit not found.');
        }

        if ($found->status?->value === 'fully_allocated' || $found->status?->value === 'reversed') {
            return $this->error('Cannot update a fully allocated or reversed debit.', 422);
        }

        $updated = $this->debitService->update($found, $request->validated());

        return $this->success(new DebitResource($updated));
    }

    public function destroy(Request $request, string $debit): JsonResponse
    {
        $found = $this->debitService->find($debit);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Debit not found.');
        }

        try {
            $this->debitService->delete($found);

            return $this->success(null, 'Debit deleted successfully.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function allocate(AllocateDebitRequest $request, string $debit): JsonResponse
    {
        $found = $this->debitService->find($debit);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Debit not found.');
        }

        try {
            $allocation = $this->debitService->allocate(
                $found,
                $request->validated(),
                $request->user(),
            );

            return $this->success(
                new DebitAllocationResource($allocation),
                'Debit allocated successfully.',
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function allocations(Request $request, string $debit): JsonResponse
    {
        $found = $this->debitService->find($debit);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Debit not found.');
        }

        return $this->success(
            DebitAllocationResource::collection($found->allocations->load(['order', 'allocatedBy'])),
        );
    }

    public function reverse(Request $request, string $debit): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $found = $this->debitService->find($debit);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Debit not found.');
        }

        if ($found->status?->value === 'reversed') {
            return $this->error('Debit is already reversed.', 422);
        }

        $reversed = $this->debitService->reverse(
            $found,
            $request->user(),
            $request->input('reason', ''),
        );

        return $this->success(new DebitResource($reversed), 'Debit reversed successfully.');
    }

    public function customerBalance(Request $request, string $customerId): JsonResponse
    {
        $balance = $this->debitService->getCustomerDebitBalance(
            $request->user()->organization_id,
            $customerId,
        );

        return $this->success([
            'customer_id' => $customerId,
            'debit_balance' => (float) $balance,
        ]);
    }

    public function customerDebits(Request $request, string $customerId): JsonResponse
    {
        $debits = $this->debitService->findByCustomer(
            $request->user()->organization_id,
            $customerId,
        );

        return $this->success(DebitResource::collection($debits));
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->debitService->getSummary(
            $request->user()->organization_id,
        );

        return $this->success($summary);
    }
}
