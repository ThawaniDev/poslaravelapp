<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function tree(string $organizationId, bool $activeOnly = true): Collection
    {
        $query = Category::where('organization_id', $organizationId)
            ->whereNull('parent_id')
            ->with(['categories' => function ($q) use ($activeOnly) {
                if ($activeOnly) {
                    $q->where('is_active', true);
                }
                $q->orderBy('sort_order')->orderBy('name');
                $q->with('categories');
            }])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function find(string $organizationId, string $categoryId): Category
    {
        return Category::with(['categories', 'products'])
            ->where('organization_id', $organizationId)
            ->findOrFail($categoryId);
    }

    public function create(array $data, User $actor): Category
    {
        $data['organization_id'] = $actor->organization_id;
        $data['sync_version'] = 1;

        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $data['sync_version'] = ($category->sync_version ?? 0) + 1;
        $category->update($data);

        return $category->fresh(['categories', 'products']);
    }

    public function delete(Category $category): void
    {
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            throw new \RuntimeException(
                "Cannot delete category '{$category->name}': {$productCount} product(s) assigned. Move or delete them first."
            );
        }

        $childCount = $category->categories()->count();
        if ($childCount > 0) {
            throw new \RuntimeException(
                "Cannot delete category '{$category->name}': {$childCount} sub-categories exist. Delete them first."
            );
        }

        $category->delete();
    }
}
