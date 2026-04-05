<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    private string $url;
    private string $key;
    private string $bucket;

    public function __construct()
    {
        $this->url = rtrim(config('supabase.url'), '/');
        $this->key = config('supabase.service_role_key') ?? config('supabase.key');
        $this->bucket = config('supabase.storage_bucket');
    }

    /**
     * Upload a file to Supabase Storage.
     *
     * @return string The stored path (e.g. "CategoriesImages/uuid.jpg")
     */
    public function upload(UploadedFile $file, string $folder): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = $folder . '/' . $filename;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type' => $file->getMimeType(),
        ])->withBody(
            file_get_contents($file->getRealPath()),
            $file->getMimeType(),
        )->post("{$this->url}/storage/v1/object/{$this->bucket}/{$path}");

        $response->throw();

        return $path;
    }

    /**
     * Upload raw file contents to Supabase Storage.
     *
     * @return string The stored path
     */
    public function uploadContents(string $contents, string $folder, string $filename, string $mimeType = 'image/jpeg'): string
    {
        $path = $folder . '/' . $filename;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type' => $mimeType,
        ])->withBody($contents, $mimeType)
          ->post("{$this->url}/storage/v1/object/{$this->bucket}/{$path}");

        $response->throw();

        return $path;
    }

    /**
     * Delete a file from Supabase Storage.
     */
    public function delete(string $path): bool
    {
        if (!$path) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->key,
        ])->delete("{$this->url}/storage/v1/object/{$this->bucket}/{$path}");

        return $response->successful();
    }

    /**
     * Get the public URL for a stored file path.
     */
    public function publicUrl(string $path): string
    {
        return "{$this->url}/storage/v1/object/public/{$this->bucket}/{$path}";
    }

    /**
     * Resolve a value to a full public URL.
     * If already a full URL, returns as-is. If a path, generates the public URL.
     */
    public static function resolveUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return app(self::class)->publicUrl($value);
    }
}
