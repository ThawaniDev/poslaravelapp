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
