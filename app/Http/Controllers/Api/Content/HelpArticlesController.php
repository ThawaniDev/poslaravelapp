<?php

namespace App\Http\Controllers\Api\Content;

use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Http\Controllers\Controller;
use App\Http\Resources\Content\KnowledgeBaseArticleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpArticlesController extends Controller
{
    /**
     * GET /api/v2/help-articles
     * List published articles. Supports ?category= and ?delivery_platform_id= filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category'             => ['nullable', 'string', 'max:50'],
            'delivery_platform_id' => ['nullable', 'uuid'],
            'per_page'             => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = KnowledgeBaseArticle::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('created_at');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('delivery_platform_id')) {
            $query->where('delivery_platform_id', $request->input('delivery_platform_id'));
        }

        $articles = $query->paginate($request->integer('per_page', 20));

        return response()->json(KnowledgeBaseArticleResource::collection($articles)->response()->getData(true));
    }

    /**
     * GET /api/v2/help-articles/{slug}
     * Single published article by slug (includes body).
     */
    public function show(string $slug): JsonResponse
    {
        $article = KnowledgeBaseArticle::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return response()->json(['data' => new KnowledgeBaseArticleResource($article)]);
    }
}
