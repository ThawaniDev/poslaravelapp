<?php

namespace App\Domain\Core\Controllers\Api;

use App\Domain\Core\Models\Register;
use App\Domain\Core\Requests\StoreRegisterRequest;
use App\Domain\Core\Requests\UpdateRegisterRequest;
use App\Domain\Core\Resources\RegisterResource;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends BaseApiController
{
    /**
     * List all registers (terminals) for the authenticated user's store.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $search  = $request->get('search');

        $query = Register::where('store_id', $request->user()->store_id)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name');

        $paginator = $query->paginate($perPage);

        $result                  = $paginator->toArray();
        $result['data']          = RegisterResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    /**
     * Create a new register (terminal).
     */
    public function store(StoreRegisterRequest $request): JsonResponse
    {
        $register = Register::create(array_merge(
            $request->validated(),
            ['store_id' => $request->user()->store_id],
        ));

        return $this->created(new RegisterResource($register), 'Terminal created successfully.');
    }

    /**
     * Show a single register (terminal).
     */
    public function show(Request $request, string $register): JsonResponse
    {
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

        return $this->success(new RegisterResource($found));
    }

    /**
     * Update an existing register (terminal).
     */
    public function update(UpdateRegisterRequest $request, string $register): JsonResponse
    {
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

        $found->update($request->validated());

        return $this->success(new RegisterResource($found->fresh()), 'Terminal updated successfully.');
    }

    /**
     * Delete a register (terminal).
     */
    public function destroy(Request $request, string $register): JsonResponse
    {
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

        $found->delete();

        return $this->success(null, 'Terminal deleted successfully.');
    }

    /**
     * Toggle the is_active status of a register.
     */
    public function toggleStatus(Request $request, string $register): JsonResponse
    {
        $found = Register::where('store_id', $request->user()->store_id)->findOrFail($register);

        $found->update(['is_active' => !$found->is_active]);

        return $this->success(
            new RegisterResource($found->fresh()),
            $found->is_active ? 'Terminal activated.' : 'Terminal deactivated.',
        );
    }
}
