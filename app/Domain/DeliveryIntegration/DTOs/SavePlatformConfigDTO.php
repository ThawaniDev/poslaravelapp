<?php

namespace App\Domain\DeliveryIntegration\DTOs;

readonly class SavePlatformConfigDTO
{
    public function __construct(
        public string $storeId,
        public string $platform,
        public ?string $apiKey = null,
        public ?string $merchantId = null,
        public ?string $webhookSecret = null,
        public ?string $branchIdOnPlatform = null,
        public bool $isEnabled = false,
        public bool $autoAccept = true,
        public ?int $throttleLimit = null,
        public ?int $maxDailyOrders = null,
        public bool $syncMenuOnProductChange = true,
        public int $menuSyncIntervalHours = 6,
    ) {}

    public static function fromRequest(array $validated, string $storeId): self
    {
        return new self(
            storeId: $storeId,
            platform: $validated['platform'],
            apiKey: $validated['api_key'] ?? null,
            merchantId: $validated['merchant_id'] ?? null,
            webhookSecret: $validated['webhook_secret'] ?? null,
            branchIdOnPlatform: $validated['branch_id_on_platform'] ?? null,
            isEnabled: $validated['is_enabled'] ?? false,
            autoAccept: $validated['auto_accept'] ?? true,
            throttleLimit: $validated['throttle_limit'] ?? null,
            maxDailyOrders: $validated['max_daily_orders'] ?? null,
            syncMenuOnProductChange: $validated['sync_menu_on_product_change'] ?? true,
            menuSyncIntervalHours: $validated['menu_sync_interval_hours'] ?? 6,
        );
    }

    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'platform' => $this->platform,
            'api_key' => $this->apiKey,
            'merchant_id' => $this->merchantId,
            'webhook_secret' => $this->webhookSecret,
            'branch_id_on_platform' => $this->branchIdOnPlatform,
            'is_enabled' => $this->isEnabled,
            'auto_accept' => $this->autoAccept,
            'throttle_limit' => $this->throttleLimit,
            'max_daily_orders' => $this->maxDailyOrders,
            'sync_menu_on_product_change' => $this->syncMenuOnProductChange,
            'menu_sync_interval_hours' => $this->menuSyncIntervalHours,
        ];
    }
}
