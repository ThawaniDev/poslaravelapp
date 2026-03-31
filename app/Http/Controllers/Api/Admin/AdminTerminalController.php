<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Core\Models\Register;
use App\Domain\Core\Requests\AdminStoreRegisterRequest;
use App\Domain\Core\Requests\AdminUpdateRegisterRequest;
use App\Domain\Core\Resources\AdminRegisterResource;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTerminalController extends BaseApiController
{
    // ─── List ────────────────────────────────────────────────────

    /**
     * GET /admin/terminals
     *
     * List all terminals across all stores with rich filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $search  = $request->get('search');

        $query = Register::with('store:id,name')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('device_id', 'like', "%{$search}%")
                        ->orWhere('nearpay_tid', 'like', "%{$search}%")
                        ->orWhere('nearpay_mid', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->get('store_id')))
            ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->get('platform')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('softpos_enabled'), fn ($q) => $q->where('softpos_enabled', filter_var($request->get('softpos_enabled'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('softpos_status'), fn ($q) => $q->where('softpos_status', $request->get('softpos_status')))
            ->when($request->filled('acquirer_source'), fn ($q) => $q->where('acquirer_source', $request->get('acquirer_source')))
            ->when($request->filled('fee_profile'), fn ($q) => $q->where('fee_profile', $request->get('fee_profile')))
            ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_dir', 'desc'));

        $paginator = $query->paginate($perPage);

        $result         = $paginator->toArray();
        $result['data'] = AdminRegisterResource::collection($paginator->items())->resolve();

        return $this->success($result);
    }

    // ─── Create ──────────────────────────────────────────────────

    /**
     * POST /admin/terminals
     */
    public function store(AdminStoreRegisterRequest $request): JsonResponse
    {
        $register = Register::create($request->validated());

        return $this->created(
            new AdminRegisterResource($register),
            __('terminals.created'),
        );
    }

    // ─── Show ────────────────────────────────────────────────────

    /**
     * GET /admin/terminals/{register}
     */
    public function show(string $register): JsonResponse
    {
        $found = Register::with('store:id,name')->findOrFail($register);

        return $this->success(new AdminRegisterResource($found));
    }

    // ─── Update ──────────────────────────────────────────────────

    /**
     * PUT /admin/terminals/{register}
     */
    public function update(AdminUpdateRegisterRequest $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        $found->update($request->validated());

        return $this->success(
            new AdminRegisterResource($found->fresh()),
            __('terminals.updated'),
        );
    }

    // ─── Delete ──────────────────────────────────────────────────

    /**
     * DELETE /admin/terminals/{register}
     */
    public function destroy(string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        $found->delete();

        return $this->success(null, __('terminals.deleted'));
    }

    // ─── Toggle Active ───────────────────────────────────────────

    /**
     * POST /admin/terminals/{register}/toggle-status
     */
    public function toggleStatus(string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        $found->update(['is_active' => !$found->is_active]);
        $found->refresh();

        return $this->success(
            new AdminRegisterResource($found),
            $found->is_active ? __('terminals.activated') : __('terminals.deactivated'),
        );
    }

    // ─── SoftPOS Activation ──────────────────────────────────────

    /**
     * POST /admin/terminals/{register}/activate-softpos
     *
     * Activate SoftPOS on a terminal — sets status to active and timestamp.
     */
    public function activateSoftpos(Request $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        if (!$found->nearpay_tid) {
            return $this->error(__('terminals.softpos_no_tid'), 422);
        }

        if (!$found->acquirer_source) {
            return $this->error(__('terminals.softpos_no_acquirer'), 422);
        }

        $found->update([
            'softpos_enabled'      => true,
            'softpos_status'       => 'active',
            'softpos_activated_at' => now(),
        ]);

        return $this->success(
            new AdminRegisterResource($found->fresh()),
            __('terminals.softpos_activated'),
        );
    }

    // ─── SoftPOS Suspend ─────────────────────────────────────────

    /**
     * POST /admin/terminals/{register}/suspend-softpos
     */
    public function suspendSoftpos(string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        $found->update([
            'softpos_status' => 'suspended',
        ]);

        return $this->success(
            new AdminRegisterResource($found->fresh()),
            __('terminals.softpos_suspended'),
        );
    }

    // ─── SoftPOS Deactivate ──────────────────────────────────────

    /**
     * POST /admin/terminals/{register}/deactivate-softpos
     */
    public function deactivateSoftpos(string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        $found->update([
            'softpos_enabled' => false,
            'softpos_status'  => 'deactivated',
        ]);

        return $this->success(
            new AdminRegisterResource($found->fresh()),
            __('terminals.softpos_deactivated'),
        );
    }

    // ─── Update Fees ─────────────────────────────────────────────

    /**
     * PUT /admin/terminals/{register}/fees
     *
     * Update fee configuration for a specific terminal.
     */
    public function updateFees(Request $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        $validated = $request->validate([
            'fee_profile'              => ['sometimes', 'string', 'in:standard,custom,promotional'],
            'fee_mada_percentage'      => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'fee_visa_mc_percentage'   => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'fee_flat_per_txn'         => ['sometimes', 'numeric', 'min:0', 'max:999'],
            'wameed_margin_percentage' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ]);

        $found->update($validated);

        return $this->success(
            new AdminRegisterResource($found->fresh()),
            __('terminals.fees_updated'),
        );
    }

    // ─── Stats / Summary ─────────────────────────────────────────

    /**
     * GET /admin/terminals/stats
     *
     * Platform-wide terminal statistics.
     */
    public function stats(): JsonResponse
    {
        $total            = Register::count();
        $active           = Register::where('is_active', true)->count();
        $softposEnabled   = Register::where('softpos_enabled', true)->count();
        $softposActive    = Register::where('softpos_status', 'active')->count();
        $online           = Register::where('is_online', true)->count();

        $byAcquirer = Register::where('softpos_enabled', true)
            ->selectRaw('acquirer_source, COUNT(*) as count')
            ->groupBy('acquirer_source')
            ->pluck('count', 'acquirer_source');

        $byPlatform = Register::selectRaw('platform, COUNT(*) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform');

        return $this->success([
            'total'            => $total,
            'active'           => $active,
            'inactive'         => $total - $active,
            'online'           => $online,
            'offline'          => $total - $online,
            'softpos_enabled'  => $softposEnabled,
            'softpos_active'   => $softposActive,
            'by_acquirer'      => $byAcquirer,
            'by_platform'      => $byPlatform,
        ]);
    }
}
