<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ResolvesBranchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

abstract class BaseApiController extends Controller
{
    use ResolvesBranchScope;

    /**
     * Resolve the effective store ID from the request.
     *
     * Uses the BranchScope middleware attributes when available,
     * falls back to X-Store-Id header / authenticated user's store_id.
     * Returns null when org-scoped user selects "all stores".
     */
    protected function resolveStoreId(Request $request): ?string
    {
        // Prefer middleware-resolved value (set by BranchScope)
        if ($request->attributes->has('resolved_store_id')) {
            return $request->attributes->get('resolved_store_id');
        }

        return $request->header('X-Store-Id') ?? $request->user()?->store_id;
    }

    /**
     * Resolve the organization_id for the current request.
     *
     * Always returns a non-null UUID for authenticated users, since every user
     * belongs to an organization. Used by features (Wameed AI, billing) that
     * must work even when the user has no store assignment.
     */
    protected function resolveOrganizationId(Request $request): ?string
    {
        $user = $request->user();
        if (!$user) return null;
        return $user->organization_id
            ?? \App\Domain\Core\Models\Store::where('id', $user->store_id)->value('organization_id');
    }

    /**
     * Returns the list of store IDs the request has access to.
     *
     * Branch users → [their own store_id].
     * Org users    → all stores in their organization.
     * Used to filter list endpoints when the request is org-scoped (no
     * specific store selected).
     */
    protected function resolveAccessibleStoreIds(Request $request): array
    {
        if ($request->attributes->has('accessible_store_ids')) {
            return (array) $request->attributes->get('accessible_store_ids');
        }
        $user = $request->user();
        if (!$user) return [];
        if ($user->store_id) return [$user->store_id];
        return $user->organization_id
            ? \App\Domain\Core\Models\Store::where('organization_id', $user->organization_id)
                ->pluck('id')->toArray()
            : [];
    }

    /**
     * True when the request is organization-scoped (no specific store selected).
     */
    protected function isOrgScoped(Request $request): bool
    {
        return $this->resolveStoreId($request) === null;
    }

    /**
     * Resolve the business_types.id (UUID) for the authenticated store.
     *
     * The Store model stores business_type as an enum slug (grocery, pharmacy, etc.),
     * while the business_types table uses UUIDs. This bridges the two by looking up
     * the matching row by slug.
     */
    protected function resolveStoreBusinessTypeId(Request $request): ?string
    {
        $storeId = $this->resolveStoreId($request);
        if (! $storeId) {
            return null;
        }

        $slug = \App\Domain\Core\Models\Store::where('id', $storeId)->value('business_type');
        if (! $slug) {
            return null;
        }

        $slugValue = $slug instanceof \BackedEnum ? $slug->value : (string) $slug;

        return \App\Domain\ContentOnboarding\Models\BusinessType::where('slug', $slugValue)->value('id');
    }

    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function successPaginated(mixed $collection, LengthAwarePaginator $paginator, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'data'         => $collection,
                'total'        => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
            ],
        ]);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message = 'Error', int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function notFound(string $message = 'Not Found'): JsonResponse
    {
        return $this->error($message, 404);
    }
}
