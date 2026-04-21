<?php

namespace App\Domain\Customer\Controllers\Api;

use App\Domain\Customer\Requests\CreateCustomerGroupRequest;
use App\Domain\Customer\Requests\CreateCustomerRequest;
use App\Domain\Customer\Requests\UpdateCustomerRequest;
use App\Domain\Customer\Resources\CustomerGroupResource;
use App\Domain\Customer\Resources\CustomerResource;
use App\Domain\Customer\Services\CustomerService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseApiController
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->customerService->list(
            $request->user()->organization_id,
            $request->only(['search', 'group_id']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CustomerResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    public function store(CreateCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->create(
                $request->validated(),
                $request->user(),
            );
            return $this->created(new CustomerResource($customer));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(Request $request, string $customer): JsonResponse
    {
        $found = $this->customerService->find($request->user()->organization_id, $customer);
        return $this->success(new CustomerResource($found));
    }

    public function update(UpdateCustomerRequest $request, string $customer): JsonResponse
    {
        $found = $this->customerService->find($request->user()->organization_id, $customer);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Customer not found.');
        }
        try {
            $updated = $this->customerService->update($found, $request->validated());
            return $this->success(new CustomerResource($updated));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, string $customer): JsonResponse
    {
        $found = $this->customerService->find($request->user()->organization_id, $customer);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Customer not found.');
        }
        $this->customerService->delete($found);
        return $this->success(null, 'Customer deleted successfully.');
    }

    // ─── Groups ──────────────────────────────────────────────

    public function groups(Request $request): JsonResponse
    {
        $groups = $this->customerService->listGroups($request->user()->organization_id);
        return $this->success(CustomerGroupResource::collection($groups));
    }

    public function storeGroup(CreateCustomerGroupRequest $request): JsonResponse
    {
        $group = $this->customerService->createGroup(
            $request->validated(),
            $request->user(),
        );
        return $this->created(new CustomerGroupResource($group));
    }

    public function updateGroup(Request $request, string $group): JsonResponse
    {
        $found = \App\Domain\Customer\Models\CustomerGroup::findOrFail($group);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Group not found.');
        }
        $updated = $this->customerService->updateGroup($found, $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]));
        return $this->success(new CustomerGroupResource($updated));
    }

    public function destroyGroup(Request $request, string $group): JsonResponse
    {
        $found = \App\Domain\Customer\Models\CustomerGroup::findOrFail($group);
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Group not found.');
        }
        try {
            $this->customerService->deleteGroup($found);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(null, 'Group deleted successfully.');
    }
}
