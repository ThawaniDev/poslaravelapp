<?php

namespace App\Domain\ThawaniIntegration\Services;

use App\Domain\ThawaniIntegration\Models\ThawaniSyncLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThawaniApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private ?string $storeId;

    public function __construct(?string $storeId = null)
    {
        $this->storeId = $storeId;

        // Load credentials from per-store config (DB) first, then fall back to global config (env)
        $storeConfig = null;
        if ($storeId) {
            $storeConfig = \App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig::where('store_id', $storeId)->first();
        }

        $this->baseUrl = rtrim(
            $storeConfig?->marketplace_url ?: config('thawani.marketplace_url', ''),
            '/'
        );
        $this->apiKey = $storeConfig?->api_key ?: config('thawani.api_key') ?: '';
        $this->apiSecret = $storeConfig?->api_secret ?: config('thawani.api_secret') ?: '';
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . '/api/wameed/' . ltrim($path, '/');
        $timestamp = (string) time();

        // For POST, pre-encode body to ensure HMAC matches what's actually sent
        $body = $method === 'GET' ? '' : json_encode($data);
        // Laravel's $request->path() returns WITHOUT leading slash (e.g. "api/wameed/connect")
        $parsedPath = 'api/wameed/' . ltrim($path, '/');
        $signaturePayload = $timestamp . $method . $parsedPath . $body;
        $signature = hash_hmac('sha256', $signaturePayload, $this->apiSecret);

        $headers = [
            'X-Wameed-Api-Key' => $this->apiKey,
            'X-Wameed-Signature' => $signature,
            'X-Wameed-Timestamp' => $timestamp,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $requestData = [
            'url' => $url,
            'method' => $method,
            'headers' => array_diff_key($headers, ['X-Wameed-Signature' => '']),
            'body' => $method === 'GET' ? $data : $data,
        ];

        try {
            $http = Http::withHeaders($headers)->timeout(config('thawani.api_timeout', 30));

            if ($method === 'GET') {
                $response = $http->get($url, $data);
            } else {
                // Send raw JSON body to ensure HMAC signature matches exactly
                $response = $http->withBody($body, 'application/json')->post($url);
            }

            $result = $this->handleResponse($response, $parsedPath, $requestData);

            return $result;
        } catch (\Exception $e) {
            $this->logSync($parsedPath, $method, $requestData, null, 'failed', $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'http_status' => 0,
            ];
        }
    }

    private function handleResponse(Response $response, string $path, array $requestData): array
    {
        $body = $response->json() ?? [];
        $status = $response->status();

        // Thawani API returns { "key": "success"|"fail", "msg": "...", "data": ... }
        $isSuccess = $response->successful() && (
            ($body['key'] ?? '') === 'success' ||
            ($body['success'] ?? false)
        );

        $result = [
            'success' => $isSuccess,
            'message' => $body['msg'] ?? $body['message'] ?? ($response->successful() ? 'OK' : 'Request failed'),
            'data' => $body['data'] ?? null,
            'http_status' => $status,
        ];

        $this->logSync(
            $path,
            $requestData['method'] ?? 'UNKNOWN',
            $requestData,
            $body,
            $result['success'] ? 'success' : 'failed',
            $result['success'] ? null : ($result['message'] ?? null),
            $status
        );

        return $result;
    }

    private function logSync(
        string $path,
        string $method,
        ?array $requestData,
        ?array $responseData,
        string $status,
        ?string $error = null,
        ?int $httpCode = null
    ): void {
        try {
            ThawaniSyncLog::create([
                'store_id' => $this->storeId,
                'entity_type' => $this->extractEntityType($path),
                'entity_id' => null,
                'action' => strtolower($method) . ':' . $path,
                'direction' => 'outgoing',
                'status' => $status,
                'request_data' => $requestData,
                'response_data' => $responseData,
                'error_message' => $error,
                'http_status_code' => $httpCode,
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('ThawaniApiClient: Failed to log sync', ['error' => $e->getMessage()]);
        }
    }

    private function extractEntityType(string $path): string
    {
        if (str_contains($path, 'product')) return 'product';
        if (str_contains($path, 'categor')) return 'category';
        if (str_contains($path, 'store')) return 'store';
        if (str_contains($path, 'connect')) return 'connection';
        if (str_contains($path, 'column')) return 'column_mapping';
        return 'general';
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey) && !empty($this->apiSecret);
    }
}
