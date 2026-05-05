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
     * Activate SoftPOS on a terminal — sets status to active and stores the
     * EdfaPay terminal token atomically.
     *
     * Body (all optional unless noted):
     *   edfapay_token  — The EdfaPay SDK terminal token to provision. When
     *                    provided it is stored encrypted and the token
     *                    timestamp is updated. If omitted, the existing token
     *                    (if any) is preserved.
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

        $request->validate([
            'edfapay_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $payload = [
            'softpos_enabled'      => true,
            'softpos_status'       => 'active',
            'softpos_activated_at' => now(),
        ];

        if ($request->filled('edfapay_token')) {
            $payload['edfapay_token']            = $request->input('edfapay_token');
            $payload['edfapay_token_updated_at'] = now();
        }

        $found->update($payload);

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

    // ─── SoftPOS Bilateral Fee Config ────────────────────────────

    /**
     * PATCH /admin/terminals/{register}/softpos-billing
     *
     * Update the bilateral SoftPOS billing rates for a terminal.
     *
     * Body (all optional — only supplied fields are updated):
     *   mada_merchant_rate  — Percentage rate charged to merchant for Mada  (e.g. 0.006 = 0.6%)
     *   mada_gateway_rate   — Percentage rate paid to EdfaPay for Mada      (e.g. 0.004 = 0.4%)
     *   card_merchant_rate  — Percentage rate charged to merchant for Visa/MC (e.g. 0.025 = 2.5%)
     *   card_gateway_rate   — Percentage rate paid to gateway for Visa/MC    (e.g. 0.020 = 2.0%)
     *   card_merchant_fee   — Fixed SAR fee charged to merchant per Visa/MC  (e.g. 1.000)
     *   card_gateway_fee    — Fixed SAR fee paid to gateway per Visa/MC       (e.g. 0.500)
     *
     * Rule: mada_merchant_rate MUST be >= mada_gateway_rate (otherwise we make a loss).
     *       card_merchant_rate MUST be >= card_gateway_rate.
     *       card_merchant_fee  MUST be >= card_gateway_fee.
     */
    public function updateSoftposBilling(Request $request, string $register): JsonResponse
    {
        $found = Register::findOrFail($register);

        $validated = $request->validate([
            'mada_merchant_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'mada_gateway_rate'  => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'card_merchant_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'card_gateway_rate'  => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'card_merchant_fee'  => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'card_gateway_fee'   => ['sometimes', 'numeric', 'min:0', 'max:1000'],
        ]);

        // Business rules: merchant rate/fee must be >= gateway rate/fee (no loss)
        $madaMerchant    = $validated['mada_merchant_rate'] ?? (float) ($found->softpos_mada_merchant_rate  ?? 0.006);
        $madaGateway     = $validated['mada_gateway_rate']  ?? (float) ($found->softpos_mada_gateway_rate   ?? 0.004);
        $cardMerchantPct = $validated['card_merchant_rate'] ?? (float) ($found->softpos_card_merchant_rate  ?? 0.0);
        $cardGatewayPct  = $validated['card_gateway_rate']  ?? (float) ($found->softpos_card_gateway_rate   ?? 0.0);
        $cardMerchant    = $validated['card_merchant_fee']  ?? (float) ($found->softpos_card_merchant_fee   ?? 1.000);
        $cardGateway     = $validated['card_gateway_fee']   ?? (float) ($found->softpos_card_gateway_fee    ?? 0.500);

        if ($madaMerchant < $madaGateway) {
            return $this->error('mada_merchant_rate cannot be less than mada_gateway_rate (platform would make a loss).', 422);
        }
        if ($cardMerchantPct < $cardGatewayPct) {
            return $this->error('card_merchant_rate cannot be less than card_gateway_rate (platform would make a loss).', 422);
        }
        if ($cardMerchant < $cardGateway) {
            return $this->error('card_merchant_fee cannot be less than card_gateway_fee (platform would make a loss).', 422);
        }

        $payload = [];
        if (array_key_exists('mada_merchant_rate', $validated)) {
            $payload['softpos_mada_merchant_rate'] = $validated['mada_merchant_rate'];
        }
        if (array_key_exists('mada_gateway_rate', $validated)) {
            $payload['softpos_mada_gateway_rate'] = $validated['mada_gateway_rate'];
        }
        if (array_key_exists('card_merchant_rate', $validated)) {
            $payload['softpos_card_merchant_rate'] = $validated['card_merchant_rate'];
        }
        if (array_key_exists('card_gateway_rate', $validated)) {
            $payload['softpos_card_gateway_rate'] = $validated['card_gateway_rate'];
        }
        if (array_key_exists('card_merchant_fee', $validated)) {
            $payload['softpos_card_merchant_fee'] = $validated['card_merchant_fee'];
        }
        if (array_key_exists('card_gateway_fee', $validated)) {
            $payload['softpos_card_gateway_fee'] = $validated['card_gateway_fee'];
        }

        if (!empty($payload)) {
            $found->update($payload);
        }

        return $this->success(
            new AdminRegisterResource($found->fresh()),
            'SoftPOS billing rates updated successfully.',
        );
    }

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

    // ─── SoftPOS Health ──────────────────────────────────────────

    /**
     * GET /admin/terminals/softpos-health
     *
     * Returns health status for every SoftPOS-enabled terminal:
     *   - has_token:   whether the EdfaPay token has been provisioned
     *   - token_age:   days since token was last updated (null = never set)
     *   - status:      softpos_status value (active / suspended / inactive)
     *   - store_name:  parent store name
     */
    public function softposHealth(): JsonResponse
    {
        $terminals = Register::with('store:id,name')
            ->where('softpos_enabled', true)
            ->orderBy('softpos_status')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'store_id', 'softpos_status', 'softpos_activated_at',
                   'edfapay_token', 'edfapay_token_updated_at', 'last_transaction_at', 'is_active']);

        $now = now();

        $data = $terminals->map(function (Register $r) use ($now) {
            $tokenUpdated = $r->edfapay_token_updated_at;
            $tokenAgeDays = $tokenUpdated ? (int) $now->diffInDays($tokenUpdated) : null;
            return [
                'id'                        => $r->id,
                'name'                      => $r->name,
                'code'                      => $r->code,
                'store_id'                  => $r->store_id,
                'store_name'                => $r->store?->name,
                'is_active'                 => (bool) $r->is_active,
                'softpos_status'            => $r->softpos_status,
                'softpos_activated_at'      => $r->softpos_activated_at?->toISOString(),
                'has_token'                 => $r->edfapay_token !== null,
                'token_age_days'            => $tokenAgeDays,
                'edfapay_token_updated_at'  => $tokenUpdated?->toISOString(),
                'last_transaction_at'       => $r->last_transaction_at?->toISOString(),
            ];
        });

        $summary = [
            'total'             => $data->count(),
            'has_token'         => $data->where('has_token', true)->count(),
            'missing_token'     => $data->where('has_token', false)->count(),
            'status_active'     => $data->where('softpos_status', 'active')->count(),
            'status_suspended'  => $data->where('softpos_status', 'suspended')->count(),
            'status_inactive'   => $data->whereNotIn('softpos_status', ['active', 'suspended'])->count(),
        ];

        return $this->success([
            'summary'   => $summary,
            'terminals' => $data->values(),
        ]);
    }
}
