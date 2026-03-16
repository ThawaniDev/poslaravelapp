<?php

namespace Tests\Feature\PosCustomization;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomizationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Schema::hasTable('organizations')) {
            \Schema::create('organizations', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('slug')->unique();
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('stores')) {
            \Schema::create('stores', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('organization_id')->constrained('organizations');
                $t->string('name');
                $t->string('name_ar')->nullable();
                $t->string('slug')->unique();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('users')) {
            \Schema::create('users', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->constrained('stores');
                $t->string('name');
                $t->string('email')->unique();
                $t->string('password_hash');
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('personal_access_tokens')) {
            \Schema::create('personal_access_tokens', function ($t) {
                $t->id();
                $t->uuidMorphs('tokenable');
                $t->string('name');
                $t->string('token', 64)->unique();
                $t->text('abilities')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();
            });
        }

        if (! \Schema::hasTable('pos_customization_settings')) {
            \Schema::create('pos_customization_settings', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->constrained('stores');
                $t->string('theme')->default('light');
                $t->string('primary_color')->default('#FD8209');
                $t->string('secondary_color')->default('#1A1A2E');
                $t->string('accent_color')->default('#16213E');
                $t->decimal('font_scale', 3, 2)->default(1.00);
                $t->string('handedness')->default('right');
                $t->integer('grid_columns')->default(4);
                $t->boolean('show_product_images')->default(true);
                $t->boolean('show_price_on_grid')->default(true);
                $t->string('cart_display_mode')->default('detailed');
                $t->string('layout_direction')->default('auto');
                $t->integer('sync_version')->default(0);
            });
        }

        if (! \Schema::hasTable('receipt_templates')) {
            \Schema::create('receipt_templates', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->constrained('stores');
                $t->string('logo_url')->nullable();
                $t->string('header_line_1')->nullable();
                $t->string('header_line_2')->nullable();
                $t->text('footer_text')->nullable();
                $t->boolean('show_vat_number')->default(true);
                $t->boolean('show_loyalty_points')->default(false);
                $t->boolean('show_barcode')->default(true);
                $t->integer('paper_width_mm')->default(80);
                $t->integer('sync_version')->default(0);
            });
        }

        if (! \Schema::hasTable('quick_access_configs')) {
            \Schema::create('quick_access_configs', function ($t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('store_id')->constrained('stores');
                $t->integer('grid_rows')->default(2);
                $t->integer('grid_cols')->default(4);
                $t->json('buttons_json')->nullable();
                $t->integer('sync_version')->default(0);
            });
        }

        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Test Store',
            'slug' => 'test-store',
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Test User',
            'email' => 'customization@test.com',
            'store_id' => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v2/{$uri}", ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authPut(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/api/v2/{$uri}", $data, ['Authorization' => "Bearer {$this->token}"]);
    }

    private function authDelete(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->deleteJson("/api/v2/{$uri}", [], ['Authorization' => "Bearer {$this->token}"]);
    }

    // ═══════════════ Settings ═══════════════

    public function test_get_default_settings(): void
    {
        $res = $this->authGet('customization/settings');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('light', $data['theme']);
        $this->assertEquals('#FD8209', $data['primary_color']);
        $this->assertEquals('right', $data['handedness']);
        $this->assertEquals(4, $data['grid_columns']);
    }

    public function test_update_settings(): void
    {
        $res = $this->authPut('customization/settings', [
            'theme' => 'dark',
            'primary_color' => '#FF0000',
            'font_scale' => 1.25,
            'grid_columns' => 3,
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('dark', $data['theme']);
        $this->assertEquals('#FF0000', $data['primary_color']);
        $this->assertEquals(3, $data['grid_columns']);
    }

    public function test_update_settings_validation(): void
    {
        $res = $this->authPut('customization/settings', [
            'theme' => 'invalid',
        ]);
        $res->assertStatus(422);
    }

    public function test_reset_settings(): void
    {
        // First create settings
        $this->authPut('customization/settings', ['theme' => 'dark']);

        $res = $this->authDelete('customization/settings');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('light', $data['theme']);
    }

    public function test_get_persisted_settings(): void
    {
        $this->authPut('customization/settings', ['handedness' => 'left']);

        $res = $this->authGet('customization/settings');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('left', $data['handedness']);
    }

    // ═══════════════ Receipt Template ═══════════════

    public function test_get_default_receipt(): void
    {
        $res = $this->authGet('customization/receipt');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertTrue($data['show_vat_number']);
        $this->assertTrue($data['show_barcode']);
        $this->assertEquals(80, $data['paper_width_mm']);
    }

    public function test_update_receipt(): void
    {
        $res = $this->authPut('customization/receipt', [
            'header_line_1' => 'Store Name',
            'footer_text' => 'Thank you!',
            'show_loyalty_points' => true,
            'paper_width_mm' => 58,
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('Store Name', $data['header_line_1']);
        $this->assertEquals('Thank you!', $data['footer_text']);
        $this->assertTrue($data['show_loyalty_points']);
        $this->assertEquals(58, $data['paper_width_mm']);
    }

    public function test_update_receipt_validation(): void
    {
        $res = $this->authPut('customization/receipt', [
            'paper_width_mm' => 40,
        ]);
        $res->assertStatus(422);
    }

    public function test_reset_receipt(): void
    {
        $this->authPut('customization/receipt', ['header_line_1' => 'Custom']);

        $res = $this->authDelete('customization/receipt');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertNull($data['header_line_1']);
    }

    // ═══════════════ Quick Access ═══════════════

    public function test_get_default_quick_access(): void
    {
        $res = $this->authGet('customization/quick-access');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(2, $data['grid_rows']);
        $this->assertEquals(4, $data['grid_cols']);
    }

    public function test_update_quick_access(): void
    {
        $res = $this->authPut('customization/quick-access', [
            'grid_rows' => 3,
            'grid_cols' => 6,
            'buttons_json' => [
                ['id' => 'btn1', 'label' => 'Water', 'color' => '#0000FF'],
            ],
        ]);
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(3, $data['grid_rows']);
        $this->assertEquals(6, $data['grid_cols']);
        $this->assertCount(1, $data['buttons_json']);
    }

    public function test_update_quick_access_validation(): void
    {
        $res = $this->authPut('customization/quick-access', [
            'grid_rows' => 10,
        ]);
        $res->assertStatus(422);
    }

    public function test_reset_quick_access(): void
    {
        $this->authPut('customization/quick-access', ['grid_rows' => 5]);

        $res = $this->authDelete('customization/quick-access');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals(2, $data['grid_rows']);
    }

    // ═══════════════ Export ═══════════════

    public function test_export_all(): void
    {
        $this->authPut('customization/settings', ['theme' => 'dark']);
        $this->authPut('customization/receipt', ['header_line_1' => 'My Store']);

        $res = $this->authGet('customization/export');
        $res->assertOk();
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('receipt_template', $data);
        $this->assertArrayHasKey('quick_access', $data);
        $this->assertEquals('dark', $data['settings']['theme']);
        $this->assertEquals('My Store', $data['receipt_template']['header_line_1']);
    }

    // ═══════════════ Auth ═══════════════

    public function test_unauthenticated_access(): void
    {
        $this->getJson('/api/v2/customization/settings')->assertUnauthorized();
    }

    // ═══════════════ Sync Version ═══════════════

    public function test_sync_version_increments(): void
    {
        $res = $this->authPut('customization/settings', ['theme' => 'dark']);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertGreaterThan(0, $data['sync_version']);
    }

    // ═══════════════ Edge Cases ═══════════════

    public function test_update_settings_partial(): void
    {
        $this->authPut('customization/settings', ['theme' => 'dark', 'grid_columns' => 3]);
        $res = $this->authPut('customization/settings', ['theme' => 'custom']);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertEquals('custom', $data['theme']);
    }

    public function test_boolean_fields_receipt(): void
    {
        $res = $this->authPut('customization/receipt', [
            'show_vat_number' => false,
            'show_barcode' => false,
        ]);
        $data = json_decode($res->getContent(), true)['data'];
        $this->assertFalse($data['show_vat_number']);
        $this->assertFalse($data['show_barcode']);
    }

    public function test_font_scale_boundaries(): void
    {
        $res = $this->authPut('customization/settings', ['font_scale' => 0.5]);
        $res->assertOk();

        $res = $this->authPut('customization/settings', ['font_scale' => 2.0]);
        $res->assertOk();

        $res = $this->authPut('customization/settings', ['font_scale' => 0.3]);
        $res->assertStatus(422);

        $res = $this->authPut('customization/settings', ['font_scale' => 2.5]);
        $res->assertStatus(422);
    }
}
