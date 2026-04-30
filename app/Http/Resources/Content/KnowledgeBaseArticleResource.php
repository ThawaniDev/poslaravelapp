<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeBaseArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'title'                => $this->title,
            'title_ar'             => $this->title_ar,
            'category'             => $this->category instanceof \BackedEnum
                ? $this->category->value
                : $this->category,
            'delivery_platform_id' => $this->delivery_platform_id,
            'sort_order'           => (int) $this->sort_order,
            'body'                 => $this->when(
                $request->routeIs('api.help-articles.show'),
                $this->body
            ),
            'body_ar'              => $this->when(
                $request->routeIs('api.help-articles.show'),
                $this->body_ar
            ),
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
