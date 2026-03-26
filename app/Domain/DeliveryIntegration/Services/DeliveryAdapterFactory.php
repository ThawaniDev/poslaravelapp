<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\Adapters\GenericDeliveryAdapter;
use App\Domain\DeliveryIntegration\Adapters\HungerStationAdapter;
use App\Domain\DeliveryIntegration\Adapters\JahezAdapter;
use App\Domain\DeliveryIntegration\Adapters\MarsoolAdapter;
use App\Domain\DeliveryIntegration\Contracts\DeliveryPlatformInterface;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;

class DeliveryAdapterFactory
{
    private static array $adapterMap = [
        'hungerstation' => HungerStationAdapter::class,
        'jahez' => JahezAdapter::class,
        'marsool' => MarsoolAdapter::class,
    ];

    public static function make(DeliveryPlatformConfig $platformConfig): DeliveryPlatformInterface
    {
        $slug = $platformConfig->platform->value;
        $config = $platformConfig->toArray();

        $adapterClass = self::$adapterMap[$slug] ?? null;

        if ($adapterClass) {
            return new $adapterClass($config);
        }

        return new GenericDeliveryAdapter($config, $slug);
    }

    public static function makeFromSlug(string $slug, array $config): DeliveryPlatformInterface
    {
        $adapterClass = self::$adapterMap[$slug] ?? null;

        if ($adapterClass) {
            return new $adapterClass($config);
        }

        return new GenericDeliveryAdapter($config, $slug);
    }

    public static function registerAdapter(string $slug, string $adapterClass): void
    {
        self::$adapterMap[$slug] = $adapterClass;
    }

    public static function availableAdapters(): array
    {
        return array_keys(self::$adapterMap);
    }
}
