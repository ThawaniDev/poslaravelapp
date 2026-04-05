<?php

namespace App\Domain\PredefinedCatalog\Services;

use App\Services\SupabaseStorageService;
use Illuminate\Http\UploadedFile;

class PredefinedImageUploadService
{
    private string $productFolder = 'ProductsImages';
    private string $categoryFolder = 'CategoriesImages';

    public function __construct(
        private readonly SupabaseStorageService $storage,
    ) {}

    public function uploadProductImage(UploadedFile $file): string
    {
        return $this->storage->upload($file, $this->productFolder);
    }

    public function uploadCategoryImage(UploadedFile $file): string
    {
        return $this->storage->upload($file, $this->categoryFolder);
    }

    public function deleteProductImage(string $path): bool
    {
        return $this->storage->delete($path);
    }

    public function deleteCategoryImage(string $path): bool
    {
        return $this->storage->delete($path);
    }

    public function url(?string $path): ?string
    {
        return SupabaseStorageService::resolveUrl($path);
    }
}
