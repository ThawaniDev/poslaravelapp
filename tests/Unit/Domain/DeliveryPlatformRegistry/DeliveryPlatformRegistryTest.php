<?php

namespace Tests\Unit\Domain\DeliveryPlatformRegistry;

use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatformEndpoint;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatformField;
use App\Domain\DeliveryPlatformRegistry\Enums\DeliveryAuthMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for the DeliveryPlatform registry model and its relations.
 *
 * These tests verify:
 *  - Model fillable / casts
 *  - Scopes: active(), ordered()
 *  - Relations: fields(), endpoints()
 *  - JSON casting for supported_countries
 *  - Slug uniqueness constraint
 */
class DeliveryPlatformRegistryTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function makePlatform(array $overrides = []): DeliveryPlatform
    {
        return DeliveryPlatform::create(array_merge([
            'name' => 'Test Platform ' . Str::random(4),
            'slug' => 'test-platform-' . Str::random(4),
            'auth_method' => 'api_key',
            'is_active' => true,
            'sort_order' => 1,
            'default_commission_percent' => 15.00,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. Create and persist a platform
    // ─────────────────────────────────────────────────────────────────────

    public function test_can_create_a_delivery_platform(): void
    {
        $platform = $this->makePlatform(['name' => 'Jahez', 'slug' => 'jahez']);

        $this->assertDatabaseHas('delivery_platforms', ['slug' => 'jahez']);
        $this->assertEquals('Jahez', $platform->name);
        $this->assertTrue($platform->is_active);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Casts — auth_method is enum, supported_countries is array
    // ─────────────────────────────────────────────────────────────────────

    public function test_auth_method_is_cast_to_enum(): void
    {
        $platform = $this->makePlatform(['auth_method' => 'api_key']);

        $this->assertInstanceOf(DeliveryAuthMethod::class, $platform->auth_method);
        $this->assertEquals('api_key', $platform->auth_method->value);
    }

    public function test_supported_countries_is_cast_to_array(): void
    {
        $platform = $this->makePlatform(['supported_countries' => ['SA', 'AE', 'KW']]);
        $fresh    = DeliveryPlatform::find($platform->id);

        $this->assertIsArray($fresh->supported_countries);
        $this->assertContains('SA', $fresh->supported_countries);
        $this->assertCount(3, $fresh->supported_countries);
    }

    public function test_null_supported_countries_is_null_not_array(): void
    {
        $platform = $this->makePlatform(['supported_countries' => null]);
        $fresh    = DeliveryPlatform::find($platform->id);

        $this->assertNull($fresh->supported_countries);
    }

    public function test_commission_is_cast_to_decimal(): void
    {
        $platform = $this->makePlatform(['default_commission_percent' => 18.5]);
        $fresh    = DeliveryPlatform::find($platform->id);

        $this->assertEquals('18.50', $fresh->default_commission_percent);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Scope: active() only returns is_active=true platforms
    // ─────────────────────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_platforms(): void
    {
        $this->makePlatform(['is_active' => true, 'slug' => 'active-p']);
        $this->makePlatform(['is_active' => false, 'slug' => 'inactive-p']);

        $active = DeliveryPlatform::where('is_active', true)->get();
        $this->assertCount(1, $active);
        $this->assertEquals('active-p', $active->first()->slug);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Scope: ordered by sort_order
    // ─────────────────────────────────────────────────────────────────────

    public function test_platforms_can_be_ordered_by_sort_order(): void
    {
        $this->makePlatform(['slug' => 'b-platform', 'sort_order' => 2]);
        $this->makePlatform(['slug' => 'a-platform', 'sort_order' => 1]);

        $platforms = DeliveryPlatform::orderBy('sort_order')->pluck('slug');

        $this->assertEquals('a-platform', $platforms[0]);
        $this->assertEquals('b-platform', $platforms[1]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. Relation: fields() returns associated platform fields
    // ─────────────────────────────────────────────────────────────────────

    public function test_fields_relation_returns_platform_fields(): void
    {
        $platform = $this->makePlatform(['slug' => 'with-fields']);

        DeliveryPlatformField::create([
            'delivery_platform_id' => $platform->id,
            'field_key'   => 'restaurant_id',
            'field_label' => 'Restaurant ID',
            'field_type'  => 'text',
            'is_required' => true,
            'sort_order'  => 1,
        ]);

        DeliveryPlatformField::create([
            'delivery_platform_id' => $platform->id,
            'field_key'   => 'api_secret',
            'field_label' => 'API Secret',
            'field_type'  => 'password',
            'is_required' => true,
            'sort_order'  => 2,
        ]);

        $fresh = DeliveryPlatform::with(['fields' => fn($q) => $q->orderBy('sort_order')])->find($platform->id);
        $this->assertCount(2, $fresh->fields);
        $this->assertEquals('restaurant_id', $fresh->fields->first()->field_key);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. Relation: endpoints() returns platform endpoints
    // ─────────────────────────────────────────────────────────────────────

    public function test_endpoints_relation_returns_platform_endpoints(): void
    {
        $platform = $this->makePlatform(['slug' => 'with-endpoints']);

        DeliveryPlatformEndpoint::create([
            'delivery_platform_id' => $platform->id,
            'operation'    => 'bulk_menu_push',
            'http_method'  => 'POST',
            'url_template' => '/restaurants/{restaurant_id}/menu',
        ]);

        $fresh = DeliveryPlatform::with('endpoints')->find($platform->id);
        $this->assertCount(1, $fresh->endpoints);
        $this->assertEquals('bulk_menu_push', $fresh->endpoints->first()->operation->value);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7. Platform with no fields returns empty collection
    // ─────────────────────────────────────────────────────────────────────

    public function test_platform_with_no_fields_returns_empty_collection(): void
    {
        $platform = $this->makePlatform(['slug' => 'no-fields']);

        $fresh = DeliveryPlatform::with('fields')->find($platform->id);
        $this->assertCount(0, $fresh->fields);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 8. Update: deactivating platform works
    // ─────────────────────────────────────────────────────────────────────

    public function test_can_deactivate_a_platform(): void
    {
        $platform = $this->makePlatform(['is_active' => true, 'slug' => 'deactivate-me']);
        $platform->update(['is_active' => false]);

        $this->assertFalse(DeliveryPlatform::find($platform->id)->is_active);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 9. All auth methods are valid enum values
    // ─────────────────────────────────────────────────────────────────────

    public function test_all_auth_method_enum_values_can_be_persisted(): void
    {
        foreach (DeliveryAuthMethod::cases() as $method) {
            $platform = $this->makePlatform([
                'slug' => 'auth-' . $method->value,
                'auth_method' => $method->value,
            ]);
            $fresh = DeliveryPlatform::find($platform->id);
            $this->assertEquals($method, $fresh->auth_method);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // 10. Bilingual fields (name_ar, description_ar)
    // ─────────────────────────────────────────────────────────────────────

    public function test_arabic_name_and_description_are_persisted(): void
    {
        $platform = $this->makePlatform([
            'slug' => 'arabic-test',
            'name_ar' => 'جاهز',
            'description' => 'Fast delivery',
            'description_ar' => 'خدمة توصيل سريعة',
        ]);

        $fresh = DeliveryPlatform::find($platform->id);
        $this->assertEquals('جاهز', $fresh->name_ar);
        $this->assertEquals('خدمة توصيل سريعة', $fresh->description_ar);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 11. Platforms API endpoint returns active registry
    // ─────────────────────────────────────────────────────────────────────

    public function test_api_platforms_endpoint_returns_only_active_platforms(): void
    {
        $org = \App\Domain\Core\Models\Organization::create([
            'name' => 'Test Org', 'business_type' => 'restaurant', 'country' => 'SA',
        ]);
        $store = \App\Domain\Core\Models\Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store', 'business_type' => 'restaurant',
            'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => true,
        ]);
        $user = \App\Domain\Auth\Models\User::create([
            'name' => 'Owner', 'email' => 'registry@test.com',
            'password_hash' => bcrypt('pass'),
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'role' => 'owner', 'is_active' => true,
        ]);
        $token = $user->createToken('t', ['*'])->plainTextToken;

        // Active platform
        $this->makePlatform(['slug' => 'active-reg', 'is_active' => true]);
        // Inactive platform
        $this->makePlatform(['slug' => 'inactive-reg', 'is_active' => false]);

        $r = $this->getJson('/api/v2/delivery/platforms', ['Authorization' => "Bearer {$token}"]);

        $r->assertOk();
        $slugs = collect($r->json('data'))->pluck('slug')->toArray();
        $this->assertContains('active-reg', $slugs);
        $this->assertNotContains('inactive-reg', $slugs);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 12. Platforms API response includes fields array
    // ─────────────────────────────────────────────────────────────────────

    public function test_api_platforms_endpoint_includes_fields(): void
    {
        $org = \App\Domain\Core\Models\Organization::create([
            'name' => 'Fields Org', 'business_type' => 'restaurant', 'country' => 'SA',
        ]);
        $store = \App\Domain\Core\Models\Store::create([
            'organization_id' => $org->id,
            'name' => 'Fields Store', 'business_type' => 'restaurant',
            'currency' => 'SAR', 'is_active' => true, 'is_main_branch' => true,
        ]);
        $user = \App\Domain\Auth\Models\User::create([
            'name' => 'Owner', 'email' => 'fields@test.com',
            'password_hash' => bcrypt('pass'),
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'role' => 'owner', 'is_active' => true,
        ]);
        $token = $user->createToken('t', ['*'])->plainTextToken;

        $platform = $this->makePlatform(['slug' => 'with-api-fields', 'is_active' => true]);
        DeliveryPlatformField::create([
            'delivery_platform_id' => $platform->id,
            'field_key' => 'branch_id', 'field_label' => 'Branch ID',
            'field_type' => 'text', 'is_required' => true, 'sort_order' => 1,
        ]);

        $r = $this->getJson('/api/v2/delivery/platforms', ['Authorization' => "Bearer {$token}"]);
        $r->assertOk();

        $found = collect($r->json('data'))->firstWhere('slug', 'with-api-fields');
        $this->assertNotNull($found);
        $this->assertArrayHasKey('fields', $found);
        $this->assertCount(1, $found['fields']);
        $this->assertEquals('branch_id', $found['fields'][0]['field_key']);
    }
}
