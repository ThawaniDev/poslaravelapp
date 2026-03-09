<?php

namespace App\Domain\DeliveryPlatformRegistry\Enums;

enum DeliveryEndpointOperation: string
{
    case ProductCreate = 'product_create';
    case ProductUpdate = 'product_update';
    case ProductDelete = 'product_delete';
    case CategorySync = 'category_sync';
    case BulkMenuPush = 'bulk_menu_push';
}
