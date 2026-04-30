<?php

namespace Tests\Feature\Localization;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\SystemConfig\Models\MasterTranslationString;
use App\Domain\SystemConfig\Models\SupportedLocale;
use App\Domain\SystemConfig\Models\TranslationOverride;
use App\Domain\SystemConfig\Models\TranslationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalizationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private string $storeId;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tables for SQLite test environment
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function ($t) {
                $t->uuid('id')->primary();
                $t->string('name');
                $t->string('business_type')->default('grocery');
                $t->string('country')->default('SA');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('organization_id')->nullable();
                $t->string('name');
                $t->string('name_ar')->nullable();
                $t->string('slug')->nullable();
                $t->string('branch_code')->nullable();
                $t->text('address')->nullable();
                $t->string('city')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->string('phone')->nullable();
                $t->string('email')->nullable();
                $t->string('timezone')->default('Asia/Riyadh');
                $t->string('currency')->default('SAR');
                $t->string('locale')->default('ar');
                $t->string('business_type')->default('grocery');
                $t->boolean('is_active')->default(true);
                $t->boolean('is_main_branch')->default(false);
                $t->decimal('storage_used_mb', 10, 2)->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('organization_id')->nullable();
                $t->uuid('store_id')->nullable();
                $t->string('name');
                $t->string('email')->unique();
                $t->string('password_hash');
                $t->string('role')->default('cashier');
                $t->boolean('is_active')->default(true);
                $t->timestamp('email_verified_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function ($t) {
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

        if (!Schema::hasTable('supported_locales')) {
            Schema::create('supported_locales', function ($t) {
                $t->uuid('id')->primary();
                $t->string('locale_code', 10)->unique();
                $t->string('language_name', 50);
                $t->string('language_name_native', 50);
                $t->string('direction', 3)->default('ltr');
                $t->string('date_format', 20)->nullable();
                $t->string('number_format', 20)->nullable();
                $t->string('calendar_system', 20)->default('gregorian');
                $t->boolean('is_active')->default(true);
                $t->boolean('is_default')->default(false);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('master_translation_strings')) {
            Schema::create('master_translation_strings', function ($t) {
                $t->uuid('id')->primary();
                $t->string('string_key', 200)->unique();
                $t->string('category', 30);
                $t->text('value_en');
                $t->text('value_ar');
                $t->string('description', 255)->nullable();
                $t->boolean('is_overridable')->default(false);
                $t->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('translation_overrides')) {
            Schema::create('translation_overrides', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->string('string_key', 200);
                $t->string('locale', 5);
                $t->text('custom_value');
                $t->timestamp('updated_at')->nullable();
                $t->unique(['store_id', 'string_key', 'locale']);
            });
        }

        if (!Schema::hasTable('translation_versions')) {
            Schema::create('translation_versions', function ($t) {
                $t->uuid('id')->primary();
                $t->string('version_hash', 64);
                $t->timestamp('published_at')->nullable();
                $t->uuid('published_by')->nullable();
                $t->string('notes', 255)->nullable();
            });
        }

        // Seed test data
        SupportedLocale::query()->delete();

        $org = Organization::create([
            'name' => 'Localization Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $store = Store::create([
            'organization_id' => $org->id,
            'name' => 'Localization Test Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name' => 'Localization Tester',
            'email' => 'l10n@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $store->id,
            'organization_id' => $org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->userId = $user->id;
        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── Locale endpoints ───────────────────────────────────────

    public function test_list_locales_empty()
    {
        $res = $this->getJson('/api/v2/settings/locales', $this->authHeaders());
        $res->assertOk()->assertJsonPath('data', []);
    }

    public function test_save_locale_creates_new()
    {
        $res = $this->postJson('/api/v2/settings/locales', [
            'locale_code' => 'en',
            'language_name' => 'English',
            'language_name_native' => 'English',
            'direction' => 'ltr',
            'calendar_system' => 'gregorian',
            'is_active' => true,
            'is_default' => false,
        ], $this->authHeaders());

        $res->assertOk()->assertJsonPath('data.locale_code', 'en');
    }

    public function test_save_locale_updates_existing()
    {
        SupportedLocale::updateOrCreate(
            ['locale_code' => 'ar'],
            [
                'language_name' => 'Arabic',
                'language_name_native' => 'العربية',
                'direction' => 'rtl',
                'is_active' => true,
                'is_default' => true,
            ],
        );

        $res = $this->postJson('/api/v2/settings/locales', [
            'locale_code' => 'ar',
            'language_name' => 'Arabic Updated',
            'language_name_native' => 'العربية',
            'direction' => 'rtl',
            'is_default' => true,
        ], $this->authHeaders());

        $res->assertOk()->assertJsonPath('data.language_name', 'Arabic Updated');
        $this->assertDatabaseCount('supported_locales', 1);
    }

    public function test_save_locale_sets_default_unsets_others()
    {
        SupportedLocale::create([
            'locale_code' => 'ar',
            'language_name' => 'Arabic',
            'language_name_native' => 'العربية',
            'direction' => 'rtl',
            'is_default' => true,
        ]);

        $this->postJson('/api/v2/settings/locales', [
            'locale_code' => 'en',
            'language_name' => 'English',
            'language_name_native' => 'English',
            'direction' => 'ltr',
            'is_default' => true,
        ], $this->authHeaders());

        $this->assertDatabaseHas('supported_locales', ['locale_code' => 'ar', 'is_default' => false]);
        $this->assertDatabaseHas('supported_locales', ['locale_code' => 'en', 'is_default' => true]);
    }

    public function test_list_locales_active_only_filter()
    {
        SupportedLocale::create([
            'locale_code' => 'ar',
            'language_name' => 'Arabic',
            'language_name_native' => 'العربية',
            'direction' => 'rtl',
            'is_active' => true,
        ]);
        SupportedLocale::create([
            'locale_code' => 'fr',
            'language_name' => 'French',
            'language_name_native' => 'Français',
            'direction' => 'ltr',
            'is_active' => false,
        ]);

        $res = $this->getJson('/api/v2/settings/locales?active_only=true', $this->authHeaders());
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_save_locale_validation_rejects_invalid_direction()
    {
        $res = $this->postJson('/api/v2/settings/locales', [
            'locale_code' => 'en',
            'language_name' => 'English',
            'language_name_native' => 'English',
            'direction' => 'invalid',
        ], $this->authHeaders());

        $res->assertUnprocessable();
    }

    // ─── Translation endpoints ──────────────────────────────────

    public function test_save_translation_creates()
    {
        $res = $this->postJson('/api/v2/settings/translations', [
            'string_key' => 'pos.checkout.title',
            'category' => 'ui',
            'value_en' => 'Checkout',
            'value_ar' => 'الدفع',
            'description' => 'POS checkout screen title',
            'is_overridable' => true,
        ], $this->authHeaders());

        $res->assertOk()->assertJsonPath('data.string_key', 'pos.checkout.title');
    }

    public function test_save_translation_updates_existing()
    {
        MasterTranslationString::create([
            'string_key' => 'pos.receipt.header',
            'category' => 'receipt',
            'value_en' => 'Receipt',
            'value_ar' => 'إيصال',
        ]);

        $res = $this->postJson('/api/v2/settings/translations', [
            'string_key' => 'pos.receipt.header',
            'category' => 'receipt',
            'value_en' => 'Sales Receipt',
            'value_ar' => 'إيصال البيع',
        ], $this->authHeaders());

        $res->assertOk()->assertJsonPath('data.value_en', 'Sales Receipt');
        $this->assertDatabaseCount('master_translation_strings', 1);
    }

    public function test_get_translations_with_category_filter()
    {
        MasterTranslationString::create([
            'string_key' => 'ui.save',
            'category' => 'ui',
            'value_en' => 'Save',
            'value_ar' => 'حفظ',
        ]);
        MasterTranslationString::create([
            'string_key' => 'receipt.total',
            'category' => 'receipt',
            'value_en' => 'Total',
            'value_ar' => 'الإجمالي',
        ]);

        $res = $this->getJson('/api/v2/settings/translations?locale=en&category=ui', $this->authHeaders());
        $res->assertOk();
        $this->assertEquals(1, $res->json('data.total'));
    }

    public function test_get_translations_with_search()
    {
        MasterTranslationString::create([
            'string_key' => 'pos.checkout.title',
            'category' => 'ui',
            'value_en' => 'Checkout',
            'value_ar' => 'الدفع',
        ]);
        MasterTranslationString::create([
            'string_key' => 'pos.product.title',
            'category' => 'ui',
            'value_en' => 'Product',
            'value_ar' => 'منتج',
        ]);

        $res = $this->getJson('/api/v2/settings/translations?locale=en&search=checkout', $this->authHeaders());
        $res->assertOk();
        $this->assertEquals(1, $res->json('data.total'));
    }

    public function test_get_translations_with_store_overrides()
    {
        $str = MasterTranslationString::create([
            'string_key' => 'pos.welcome',
            'category' => 'ui',
            'value_en' => 'Welcome',
            'value_ar' => 'مرحباً',
            'is_overridable' => true,
        ]);
        TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.welcome',
            'locale' => 'en',
            'custom_value' => 'Welcome to Our Store!',
        ]);

        $res = $this->getJson("/api/v2/settings/translations?locale=en&store_id={$this->storeId}", $this->authHeaders());
        $res->assertOk();
        $item = $res->json('data.data.0');
        $this->assertEquals('Welcome to Our Store!', $item['override_value']);
    }

    public function test_save_translation_validation_rejects_invalid_category()
    {
        $res = $this->postJson('/api/v2/settings/translations', [
            'string_key' => 'test.key',
            'category' => 'invalid_cat',
            'value_en' => 'Test',
            'value_ar' => 'اختبار',
        ], $this->authHeaders());

        $res->assertUnprocessable();
    }

    // ─── Bulk import ────────────────────────────────────────────

    public function test_bulk_import_translations()
    {
        $res = $this->postJson('/api/v2/settings/translations/bulk-import', [
            'translations' => [
                [
                    'string_key' => 'bulk.one',
                    'category' => 'ui',
                    'value_en' => 'One',
                    'value_ar' => 'واحد',
                ],
                [
                    'string_key' => 'bulk.two',
                    'category' => 'ui',
                    'value_en' => 'Two',
                    'value_ar' => 'اثنان',
                ],
                [
                    'string_key' => 'bulk.three',
                    'category' => 'notification',
                    'value_en' => 'Three',
                    'value_ar' => 'ثلاثة',
                ],
            ],
        ], $this->authHeaders());

        $res->assertOk()->assertJsonPath('data.imported', 3);
        $this->assertDatabaseCount('master_translation_strings', 3);
    }

    public function test_bulk_import_validation_rejects_empty()
    {
        $res = $this->postJson('/api/v2/settings/translations/bulk-import', [
            'translations' => [],
        ], $this->authHeaders());

        $res->assertUnprocessable();
    }

    // ─── Override endpoints ─────────────────────────────────────

    public function test_save_override_creates()
    {
        $res = $this->postJson('/api/v2/settings/translation-overrides', [
            'store_id' => $this->storeId,
            'string_key' => 'pos.title',
            'locale' => 'en',
            'custom_value' => 'My Custom Title',
        ], $this->authHeaders());

        $res->assertOk()->assertJsonPath('data.custom_value', 'My Custom Title');
    }

    public function test_save_override_updates_existing()
    {
        $override = TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.title',
            'locale' => 'en',
            'custom_value' => 'Old Title',
        ]);

        $res = $this->postJson('/api/v2/settings/translation-overrides', [
            'store_id' => $this->storeId,
            'string_key' => 'pos.title',
            'locale' => 'en',
            'custom_value' => 'New Title',
        ], $this->authHeaders());

        $res->assertOk()->assertJsonPath('data.custom_value', 'New Title');
        $this->assertDatabaseCount('translation_overrides', 1);
    }

    public function test_get_overrides_by_store()
    {
        TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.title',
            'locale' => 'en',
            'custom_value' => 'Custom EN',
        ]);
        TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.title',
            'locale' => 'ar',
            'custom_value' => 'عنوان مخصص',
        ]);

        $res = $this->getJson("/api/v2/settings/translation-overrides?store_id={$this->storeId}", $this->authHeaders());
        $res->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    public function test_get_overrides_filtered_by_locale()
    {
        TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.welcome',
            'locale' => 'en',
            'custom_value' => 'Hi',
        ]);
        TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.welcome',
            'locale' => 'ar',
            'custom_value' => 'أهلاً',
        ]);

        $res = $this->getJson("/api/v2/settings/translation-overrides?store_id={$this->storeId}&locale=ar", $this->authHeaders());
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_remove_override()
    {
        $override = TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.title',
            'locale' => 'en',
            'custom_value' => 'To Remove',
        ]);

        $res = $this->deleteJson("/api/v2/settings/translation-overrides/{$override->id}", [], $this->authHeaders());
        $res->assertOk();
        $this->assertDatabaseCount('translation_overrides', 0);
    }

    public function test_save_override_validation_rejects_missing_store()
    {
        $res = $this->postJson('/api/v2/settings/translation-overrides', [
            'string_key' => 'pos.title',
            'locale' => 'en',
            'custom_value' => 'Test',
        ], $this->authHeaders());

        $res->assertUnprocessable();
    }

    // ─── Version endpoints ──────────────────────────────────────

    public function test_publish_translation_version()
    {
        MasterTranslationString::create([
            'string_key' => 'pos.hello',
            'category' => 'ui',
            'value_en' => 'Hello',
            'value_ar' => 'مرحباً',
        ]);

        $res = $this->postJson('/api/v2/settings/publish-translations', [
            'notes' => 'Initial release',
        ], $this->authHeaders());

        $res->assertOk();
        $this->assertNotNull($res->json('data.version_hash'));
        $this->assertEquals('Initial release', $res->json('data.notes'));
    }

    public function test_list_translation_versions()
    {
        TranslationVersion::create([
            'version_hash' => hash('sha256', 'v1'),
            'published_at' => now()->subDay(),
            'published_by' => $this->userId,
            'notes' => 'v1',
        ]);
        TranslationVersion::create([
            'version_hash' => hash('sha256', 'v2'),
            'published_at' => now(),
            'published_by' => $this->userId,
            'notes' => 'v2',
        ]);

        $res = $this->getJson('/api/v2/settings/translation-versions', $this->authHeaders());
        $res->assertOk();
        $this->assertEquals(2, $res->json('data.total'));
    }

    // ─── Export endpoint ────────────────────────────────────────

    public function test_export_translations_en()
    {
        MasterTranslationString::create([
            'string_key' => 'pos.hello',
            'category' => 'ui',
            'value_en' => 'Hello',
            'value_ar' => 'مرحباً',
        ]);
        MasterTranslationString::create([
            'string_key' => 'pos.bye',
            'category' => 'ui',
            'value_en' => 'Goodbye',
            'value_ar' => 'مع السلامة',
        ]);

        $res = $this->getJson('/api/v2/settings/export-translations?locale=en', $this->authHeaders());
        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $data = $body['data'];
        $this->assertEquals('Hello', $data['pos.hello']);
        $this->assertEquals('Goodbye', $data['pos.bye']);
    }

    public function test_export_translations_ar()
    {
        MasterTranslationString::create([
            'string_key' => 'pos.hello',
            'category' => 'ui',
            'value_en' => 'Hello',
            'value_ar' => 'مرحباً',
        ]);

        $res = $this->getJson('/api/v2/settings/export-translations?locale=ar', $this->authHeaders());
        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('مرحباً', $body['data']['pos.hello']);
    }

    public function test_export_translations_with_store_overrides()
    {
        MasterTranslationString::create([
            'string_key' => 'pos.welcome',
            'category' => 'ui',
            'value_en' => 'Welcome',
            'value_ar' => 'أهلاً',
        ]);
        TranslationOverride::create([
            'store_id' => $this->storeId,
            'string_key' => 'pos.welcome',
            'locale' => 'en',
            'custom_value' => 'Welcome to My Shop!',
        ]);

        $res = $this->getJson("/api/v2/settings/export-translations?locale=en&store_id={$this->storeId}", $this->authHeaders());
        $res->assertOk();
        $body = json_decode($res->getContent(), true);
        $this->assertEquals('Welcome to My Shop!', $body['data']['pos.welcome']);
    }

    // ─── Auth ───────────────────────────────────────────────────

    public function test_unauthenticated_access_denied()
    {
        $res = $this->getJson('/api/v2/settings/locales');
        $res->assertUnauthorized();
    }
}
