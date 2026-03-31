<?php

namespace Tests\Feature\Core;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Core\Models\StoreWorkingHour;
use App\Domain\ProviderRegistration\Models\BusinessTypeTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private Store $mainBranch;
    private Store $secondBranch;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'          => 'Thawani Group',
            'name_ar'       => 'مجموعة ثواني',
            'business_type' => 'grocery',
            'country'       => 'SA',
            'city'          => 'Riyadh',
        ]);

        $this->mainBranch = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Branch',
            'name_ar'         => 'الفرع الرئيسي',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'city'            => 'Riyadh',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->secondBranch = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Second Branch',
            'name_ar'         => 'الفرع الثاني',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'city'            => 'Jeddah',
            'is_active'       => true,
            'is_main_branch'  => false,
        ]);

        $this->owner = User::create([
            'name'            => 'Test Owner',
            'email'           => 'owner@thawani.test',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->mainBranch->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);

        $this->token = $this->owner->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════════
    // LIST BRANCHES
    // ═══════════════════════════════════════════════════════════════

    public function test_can_list_all_branches(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'organization_id', 'name', 'name_ar', 'slug',
                        'branch_code', 'address', 'city', 'is_active',
                        'is_main_branch', 'is_warehouse', 'business_type',
                        'created_at', 'updated_at',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_branches_with_search_filter(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores?search=Main');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Main Branch', $response->json('data.0.name'));
    }

    public function test_list_branches_with_city_filter(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores?city=Jeddah');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Second Branch', $response->json('data.0.name'));
    }

    public function test_list_branches_with_active_filter(): void
    {
        $this->secondBranch->update(['is_active' => false]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores?is_active=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_list_branches_with_main_branch_filter(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores?is_main_branch=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_main_branch'));
    }

    public function test_list_branches_with_sorting(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores?sort_by=name&sort_dir=desc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_list_branches_with_pagination(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores?per_page=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ═══════════════════════════════════════════════════════════════
    // GET SINGLE BRANCH
    // ═══════════════════════════════════════════════════════════════

    public function test_can_get_branch_detail(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$this->mainBranch->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'organization_id', 'name', 'name_ar',
                    'description', 'description_ar', 'slug',
                    'branch_code', 'logo_url', 'cover_image_url',
                    'address', 'city', 'region', 'postal_code', 'country',
                    'google_maps_url', 'latitude', 'longitude',
                    'phone', 'secondary_phone', 'email', 'contact_person',
                    'timezone', 'currency', 'locale', 'business_type',
                    'is_active', 'is_main_branch', 'is_warehouse',
                    'accepts_online_orders', 'accepts_reservations',
                    'has_delivery', 'has_pickup',
                    'opening_date', 'closing_date', 'max_registers', 'max_staff',
                    'area_sqm', 'seating_capacity',
                    'cr_number', 'vat_number', 'municipal_license', 'license_expiry_date',
                    'social_links', 'extra_metadata', 'internal_notes', 'sort_order',
                    'storage_used_mb', 'staff_count', 'register_count',
                    'settings', 'working_hours', 'organization',
                    'created_at', 'updated_at',
                ],
            ]);

        $this->assertEquals($this->mainBranch->id, $response->json('data.id'));
    }

    public function test_can_get_my_store(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores/mine');

        $response->assertOk()
            ->assertJsonPath('data.id', $this->mainBranch->id);
    }

    public function test_returns_404_for_nonexistent_branch(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000099';
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$fakeId}");

        $response->assertNotFound();
    }

    public function test_cannot_access_branch_from_other_organization(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'country' => 'SA']);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Store',
            'currency' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$otherStore->id}");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // CREATE BRANCH
    // ═══════════════════════════════════════════════════════════════

    public function test_can_create_branch(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/stores', [
                'name'          => 'New Branch',
                'name_ar'       => 'فرع جديد',
                'city'          => 'Dammam',
                'region'        => 'Eastern',
                'address'       => '123 Main St',
                'phone'         => '+966501234567',
                'email'         => 'new@branch.test',
                'business_type' => 'grocery',
                'timezone'      => 'Asia/Riyadh',
                'currency'      => 'SAR',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Branch')
            ->assertJsonPath('data.name_ar', 'فرع جديد')
            ->assertJsonPath('data.city', 'Dammam')
            ->assertJsonPath('data.region', 'Eastern');

        $this->assertDatabaseHas('stores', [
            'name'            => 'New Branch',
            'organization_id' => $this->org->id,
            'city'            => 'Dammam',
        ]);

        // Verify default settings were created
        $storeId = $response->json('data.id');
        $this->assertDatabaseHas('store_settings', ['store_id' => $storeId]);

        // Verify default working hours were created
        $this->assertEquals(7, StoreWorkingHour::where('store_id', $storeId)->count());
    }

    public function test_create_branch_with_all_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/stores', [
                'name'                  => 'Full Branch',
                'name_ar'               => 'فرع كامل',
                'description'           => 'Full description',
                'description_ar'        => 'وصف كامل',
                'branch_code'           => 'BR-003',
                'business_type'         => 'restaurant',
                'address'               => '456 King Fahd Rd',
                'city'                  => 'Riyadh',
                'region'                => 'Central',
                'postal_code'           => '12345',
                'country'               => 'SA',
                'latitude'              => 24.7136,
                'longitude'             => 46.6753,
                'google_maps_url'       => 'https://maps.google.com/abcdef',
                'phone'                 => '+966501234567',
                'secondary_phone'       => '+966509876543',
                'email'                 => 'full@branch.test',
                'contact_person'        => 'Ahmed Ali',
                'timezone'              => 'Asia/Riyadh',
                'currency'              => 'SAR',
                'locale'                => 'ar',
                'is_warehouse'          => false,
                'accepts_online_orders' => true,
                'accepts_reservations'  => true,
                'has_delivery'          => true,
                'has_pickup'            => true,
                'opening_date'          => '2025-01-01',
                'max_registers'         => 10,
                'max_staff'             => 50,
                'area_sqm'              => 250.5,
                'seating_capacity'      => 80,
                'cr_number'             => 'CR123456',
                'vat_number'            => 'VAT789',
                'municipal_license'     => 'ML-2025-001',
                'license_expiry_date'   => '2026-12-31',
                'social_links'          => [
                    'instagram' => '@fullbranch',
                    'twitter'   => '@fullbranch',
                    'website'   => 'https://fullbranch.test',
                ],
                'internal_notes'        => 'Grand opening planned',
                'sort_order'            => 3,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Full Branch')
            ->assertJsonPath('data.accepts_online_orders', true)
            ->assertJsonPath('data.has_delivery', true)
            ->assertJsonPath('data.seating_capacity', 80)
            ->assertJsonPath('data.cr_number', 'CR123456');
    }

    public function test_create_branch_requires_name(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/stores', [
                'city' => 'Riyadh',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_branch_validates_email(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/stores', [
                'name'  => 'Test',
                'email' => 'not-an-email',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_branch_validates_coordinates(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/stores', [
                'name'      => 'Test',
                'latitude'  => 999, // out of range
                'longitude' => -200,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_setting_new_main_branch_unsets_previous(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/core/stores', [
                'name'           => 'New Main',
                'is_main_branch' => true,
            ]);

        $response->assertCreated();

        // Previous main should be un-set
        $this->mainBranch->refresh();
        $this->assertFalse($this->mainBranch->is_main_branch);

        // New branch should be main
        $this->assertTrue($response->json('data.is_main_branch'));
    }

    // ═══════════════════════════════════════════════════════════════
    // UPDATE BRANCH
    // ═══════════════════════════════════════════════════════════════

    public function test_can_update_branch(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->secondBranch->id}", [
                'name'            => 'Updated Branch',
                'name_ar'         => 'الفرع المحدث',
                'city'            => 'Riyadh',
                'phone'           => '+966507654321',
                'description'     => 'Updated desc',
                'description_ar'  => 'الوصف المعدل',
                'contact_person'  => 'Fatima',
                'region'          => 'Central',
                'postal_code'     => '11411',
                'is_warehouse'    => true,
                'has_delivery'    => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Branch')
            ->assertJsonPath('data.city', 'Riyadh')
            ->assertJsonPath('data.contact_person', 'Fatima')
            ->assertJsonPath('data.is_warehouse', true)
            ->assertJsonPath('data.has_delivery', true);

        $this->assertDatabaseHas('stores', [
            'id'            => $this->secondBranch->id,
            'name'          => 'Updated Branch',
            'is_warehouse'  => true,
        ]);
    }

    public function test_update_validates_closing_after_opening(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->secondBranch->id}", [
                'opening_date' => '2026-06-01',
                'closing_date' => '2025-01-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_date']);
    }

    public function test_cannot_update_branch_from_other_org(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'country' => 'SA']);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'currency'        => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$otherStore->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // DELETE BRANCH
    // ═══════════════════════════════════════════════════════════════

    public function test_can_delete_branch(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/core/stores/{$this->secondBranch->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('stores', ['id' => $this->secondBranch->id]);
    }

    public function test_cannot_delete_main_branch(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/core/stores/{$this->mainBranch->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('stores', ['id' => $this->mainBranch->id]);
    }

    public function test_cannot_delete_branch_from_other_org(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'country' => 'SA']);
        $otherStore = Store::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Store',
            'currency'        => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/core/stores/{$otherStore->id}");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════
    // TOGGLE ACTIVE
    // ═══════════════════════════════════════════════════════════════

    public function test_can_toggle_branch_active(): void
    {
        $this->assertTrue($this->secondBranch->is_active);

        // Deactivate
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->secondBranch->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Activate again
        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->secondBranch->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    // ═══════════════════════════════════════════════════════════════
    // STATS
    // ═══════════════════════════════════════════════════════════════

    public function test_can_get_branch_stats(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_branches', 'active_branches', 'inactive_branches',
                    'warehouses', 'total_staff', 'total_registers',
                    'cities', 'regions',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.total_branches'));
        $this->assertEquals(2, $response->json('data.active_branches'));
    }

    // ═══════════════════════════════════════════════════════════════
    // SORT ORDER
    // ═══════════════════════════════════════════════════════════════

    public function test_can_update_sort_order(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v2/core/stores/sort-order', [
                'items' => [
                    ['id' => $this->mainBranch->id, 'sort_order' => 2],
                    ['id' => $this->secondBranch->id, 'sort_order' => 1],
                ],
            ]);

        $response->assertOk();

        $this->mainBranch->refresh();
        $this->secondBranch->refresh();
        $this->assertEquals(2, $this->mainBranch->sort_order);
        $this->assertEquals(1, $this->secondBranch->sort_order);
    }

    // ═══════════════════════════════════════════════════════════════
    // MANAGERS
    // ═══════════════════════════════════════════════════════════════

    public function test_can_list_available_managers(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/stores/managers');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // ═══════════════════════════════════════════════════════════════
    // SETTINGS
    // ═══════════════════════════════════════════════════════════════

    public function test_can_get_store_settings(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$this->mainBranch->id}/settings");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'tax_label', 'tax_rate', 'prices_include_tax',
                    'currency_code', 'currency_symbol',
                    'allow_negative_stock', 'auto_print_receipt',
                ],
            ]);
    }

    public function test_can_update_store_settings(): void
    {
        StoreSettings::create([
            'store_id'      => $this->mainBranch->id,
            'tax_rate'      => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->mainBranch->id}/settings", [
                'tax_rate'    => 5.00,
                'tax_label'   => 'GST',
                'enable_tips' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('store_settings', [
            'store_id'  => $this->mainBranch->id,
            'tax_label' => 'GST',
        ]);
    }

    public function test_can_copy_settings_between_stores(): void
    {
        $sourceSettings = StoreSettings::create([
            'store_id'           => $this->mainBranch->id,
            'tax_rate'           => 10.00,
            'tax_label'          => 'GST',
            'currency_code'      => 'SAR',
            'enable_tips'        => true,
            'enable_kitchen_display' => true,
        ]);

        StoreSettings::create([
            'store_id'      => $this->secondBranch->id,
            'tax_rate'      => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->secondBranch->id}/copy-settings", [
                'source_store_id' => $this->mainBranch->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('store_settings', [
            'store_id'    => $this->secondBranch->id,
            'tax_label'   => 'GST',
            'enable_tips' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // WORKING HOURS
    // ═══════════════════════════════════════════════════════════════

    public function test_can_get_working_hours(): void
    {
        for ($d = 0; $d <= 6; $d++) {
            StoreWorkingHour::create([
                'store_id'    => $this->mainBranch->id,
                'day_of_week' => $d,
                'is_open'     => $d !== 5,
                'open_time'   => $d !== 5 ? '09:00' : null,
                'close_time'  => $d !== 5 ? '22:00' : null,
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/core/stores/{$this->mainBranch->id}/working-hours");

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
    }

    public function test_can_update_working_hours(): void
    {
        $response = $this->withToken($this->token)
            ->putJson("/api/v2/core/stores/{$this->mainBranch->id}/working-hours", [
                'store_id' => $this->mainBranch->id,
                'days' => [
                    ['day_of_week' => 0, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 1, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 2, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 3, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 4, 'is_open' => true,  'open_time' => '08:00', 'close_time' => '20:00'],
                    ['day_of_week' => 5, 'is_open' => false, 'open_time' => null,    'close_time' => null],
                    ['day_of_week' => 6, 'is_open' => true,  'open_time' => '10:00', 'close_time' => '18:00'],
                ],
            ]);

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
    }

    public function test_can_copy_working_hours_between_stores(): void
    {
        // Set up source hours
        for ($d = 0; $d <= 6; $d++) {
            StoreWorkingHour::create([
                'store_id'    => $this->mainBranch->id,
                'day_of_week' => $d,
                'is_open'     => true,
                'open_time'   => '07:00',
                'close_time'  => '23:00',
            ]);
        }

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->secondBranch->id}/copy-working-hours", [
                'source_store_id' => $this->mainBranch->id,
            ]);

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
        $this->assertDatabaseHas('store_working_hours', [
            'store_id'  => $this->secondBranch->id,
            'open_time' => '07:00',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // BUSINESS TYPES
    // ═══════════════════════════════════════════════════════════════

    public function test_can_list_business_types(): void
    {
        BusinessTypeTemplate::create([
            'code'          => 'grocery',
            'name_en'       => 'Retail Store',
            'name_ar'       => 'متجر تجزئة',
            'icon'          => 'store',
            'template_json' => ['tax_rate' => 15.0],
            'is_active'     => true,
            'display_order' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/core/business-types');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_can_apply_business_type(): void
    {
        BusinessTypeTemplate::create([
            'code'          => 'restaurant',
            'name_en'       => 'Restaurant',
            'name_ar'       => 'مطعم',
            'icon'          => 'restaurant',
            'template_json' => [
                'tax_rate'               => 15.0,
                'enable_kitchen_display' => true,
                'enable_tips'            => true,
            ],
            'is_active'     => true,
            'display_order' => 2,
        ]);

        StoreSettings::create([
            'store_id'      => $this->secondBranch->id,
            'tax_rate'      => 15.00,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/core/stores/{$this->secondBranch->id}/business-type", [
                'business_type' => 'restaurant',
            ]);

        $response->assertOk();
        $this->secondBranch->refresh();
        $this->assertEquals('restaurant', $this->secondBranch->business_type->value);
    }

    // ═══════════════════════════════════════════════════════════════
    // AUTH GUARDS
    // ═══════════════════════════════════════════════════════════════

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v2/core/stores');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_create_returns_401(): void
    {
        $response = $this->postJson('/api/v2/core/stores', ['name' => 'Test']);
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        $response = $this->deleteJson("/api/v2/core/stores/{$this->secondBranch->id}");
        $response->assertUnauthorized();
    }
}
