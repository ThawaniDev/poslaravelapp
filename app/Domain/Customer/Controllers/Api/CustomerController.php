<?php

namespace App\Domain\Customer\Controllers\Api;

use App\Domain\Customer\Requests\CreateCustomerGroupRequest;
use App\Domain\Customer\Requests\CreateCustomerRequest;
use App\Domain\Customer\Requests\UpdateCustomerRequest;
use App\Domain\Customer\Resources\CustomerGroupResource;
use App\Domain\Customer\Resources\CustomerResource;
use App\Domain\Customer\Services\CustomerService;
use App\Domain\Customer\Services\DigitalReceiptService;
use App\Domain\Customer\Enums\DigitalReceiptChannel;
use App\Domain\Order\Models\Order;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseApiController
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly DigitalReceiptService $digitalReceiptService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->customerService->list(
            $request->user()->organization_id,
            $request->only(['search', 'group_id', 'has_loyalty', 'last_visit_from', 'last_visit_to']),
            (int) $request->get('per_page', 20),
        );

        $result = $paginator->toArray();
        $result['data'] = CustomerResource::collection($paginator->items())->resolve();
        return $this->success($result);
    }

    /**
     * Spec §4.1 — bulk action: assign many customers to a group.
     * POST /api/customers/bulk/assign-group  { customer_ids: [...], group_id: "" }
     */
    public function bulkAssignGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'string',
            'group_id' => 'nullable|string',
        ]);
        try {
            $count = $this->customerService->bulkAssignGroup(
                $request->user()->organization_id,
                $data['customer_ids'],
                $data['group_id'] ?? null,
            );
            return $this->success(['updated' => $count]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
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

    /**
     * Quick search for POS lookup (matches phone, name, email or loyalty code).
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->get('q', '');
        $limit = min(50, max(1, (int) $request->get('limit', 10)));
        $rows = $this->customerService->quickSearch(
            $request->user()->organization_id,
            $query,
            $limit,
        );
        return $this->success(CustomerResource::collection($rows));
    }

    /**
     * Delta sync for the desktop POS.
     */
    public function sync(Request $request): JsonResponse
    {
        $result = $this->customerService->delta(
            $request->user()->organization_id,
            $request->get('since'),
            min(2000, max(50, (int) $request->get('limit', 500))),
        );
        return $this->success([
            'data' => CustomerResource::collection($result['data'])->resolve(),
            'server_time' => $result['server_time'],
            'count' => $result['count'],
        ]);
    }

    /**
     * Customer purchase history.
     */
    public function orders(Request $request, string $customer): JsonResponse
    {
        $found = $this->customerService->find($request->user()->organization_id, $customer);
        $paginator = $this->customerService->customerOrders(
            $found->id,
            (int) $request->get('per_page', 20),
        );
        return $this->success([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * Send a digital receipt for an order to the customer (rule #7).
     */
    public function sendReceipt(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'uuid'],
            'channel' => ['required', 'string', 'in:email,whatsapp,sms'],
            'destination' => ['nullable', 'string', 'max:255'],
        ]);

        $found = $this->customerService->find($request->user()->organization_id, $customer);
        $order = Order::findOrFail($data['order_id']);
        if ($order->customer_id !== $found->id) {
            return $this->error(__('customers.receipt_order_mismatch'), 422);
        }

        try {
            $log = $this->digitalReceiptService->send(
                $order,
                $found,
                DigitalReceiptChannel::from($data['channel']),
                $data['destination'] ?? null,
                $request->user(),
            );
            return $this->created([
                'id' => $log->id,
                'order_id' => $log->order_id,
                'customer_id' => $log->customer_id,
                'channel' => $log->channel instanceof \BackedEnum ? $log->channel->value : $log->channel,
                'destination' => $log->destination,
                'status' => $log->status instanceof \BackedEnum ? $log->status->value : $log->status,
                'sent_at' => $log->sent_at?->toIso8601String(),
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
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
