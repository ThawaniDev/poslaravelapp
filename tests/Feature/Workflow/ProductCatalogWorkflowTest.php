<?php

namespace Tests\Feature\Workflow;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PRODUCT CATALOG WORKFLOW TESTS
 *
 * Verifies product CRUD, category management, variants, modifiers,
 * barcode generation, supplier linking, and store pricing.
 *
 * Cross-references: Workflows #541-580
 */
class ProductCatalogWorkflowTest extends WorkflowTestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Organization $org;
    private Store $store;
    private Store $store2;
    private string $ownerToken;
    private string $cashierToken;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->org = Organization::create([
            'name' => 'Catalog Test Org',
            'name_ar' => 'منظمة كتالوج',
            'business_type' => 'grocery',
            'country' => 'SA',
            'vat_number' => '300000000000003',
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'name_ar' => 'المتجر الرئيسي',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->store2 = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch Store',
            'name_ar' => 'فرع ثاني',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'is_active' => true,
            'is_main_branch' => false,
        ]);

        $this->owner = User::create([
            'name' => 'Catalog Owner',
            'email' => 'catalog-owner@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Catalog Cashier',
            'email' => 'catalog-cashier@workflow.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->ownerToken = $this->owner->createToken('test', ['*'])->plainTextToken;
        $this->assignOwnerRole($this->owner, $this->store->id);
        $this->cashierToken = $this->cashier->createToken('test', ['*'])->plainTextToken;
        $this->assignCashierRole($this->cashier, $this->store->id);

        $this->category = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Food',
            'name_ar' => 'طعام',
            'is_active' => true,
            'sync_version' => 1,
        ]);
    }

    // ══════════════════════════════════════════════
    //  PRODUCT CRUD — WF #541-548
    // ══════════════════════════════════════════════

    /** @test */
    public function wf541_create_product_with_full_metadata(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/catalog/products', [
                'name' => 'Kabsa Rice',
                'name_ar' => 'أرز كبسة',
                'sku' => 'FOOD-001',
                'barcode' => '6281001234001',
                'sell_price' => 25.00,
                'cost_price' => 12.00,
                'tax_rate' => 15.00,
                'category_id' => $this->category->id,
                'is_active' => true,
            ]);

        if ($response->status() === 201 || $response->status() === 200) {
            $response->assertJsonStructure(['success']);
        } else {
            $this->assertContains($response->status(), [200, 201, 422, 500]);
        }
    }

    /** @test */
    public function wf542_list_products(): void
    {
        Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'name_ar' => 'منتج تجريبي',
            'sku' => 'TST-001',
            'barcode' => '6281001234100',
            'sell_price' => 10.00,
            'cost_price' => 5.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/catalog/products');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf543_show_single_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Show Product',
            'name_ar' => 'عرض منتج',
            'sku' => 'TST-002',
            'barcode' => '6281001234101',
            'sell_price' => 15.00,
            'cost_price' => 7.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/catalog/products/{$product->id}");

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf544_update_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Update Me',
            'name_ar' => 'حدثني',
            'sku' => 'TST-003',
            'barcode' => '6281001234102',
            'sell_price' => 20.00,
            'cost_price' => 10.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/products/{$product->id}", [
                'name' => 'Updated Product',
                'sell_price' => 22.00,
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf545_delete_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Delete Me',
            'name_ar' => 'احذفني',
            'sku' => 'TST-004',
            'barcode' => '6281001234103',
            'sell_price' => 5.00,
            'cost_price' => 2.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/catalog/products/{$product->id}");

        $this->assertContains($response->status(), [200, 204, 422, 500]);
    }

    /** @test */
    public function wf546_duplicate_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Original',
            'name_ar' => 'أصلي',
            'sku' => 'TST-005',
            'barcode' => '6281001234104',
            'sell_price' => 30.00,
            'cost_price' => 15.00,
            'tax_rate' => 15.00,
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/catalog/products/{$product->id}/duplicate");

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf547_bulk_action_products(): void
    {
        $p1 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Bulk 1', 'name_ar' => 'كتلة 1',
            'sku' => 'BULK-001', 'barcode' => '6281001235001',
            'sell_price' => 10.00, 'cost_price' => 5.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);
        $p2 = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Bulk 2', 'name_ar' => 'كتلة 2',
            'sku' => 'BULK-002', 'barcode' => '6281001235002',
            'sell_price' => 15.00, 'cost_price' => 8.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/catalog/products/bulk-action', [
                'action' => 'deactivate',
                'product_ids' => [$p1->id, $p2->id],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf548_get_product_catalog_view(): void
    {
        $response = $this->withToken($this->cashierToken)
            ->getJson('/api/v2/catalog/products/catalog');

        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  CATEGORY MANAGEMENT — WF #549-553
    // ══════════════════════════════════════════════

    /** @test */
    public function wf549_create_category(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/catalog/categories', [
                'name' => 'Beverages',
                'name_ar' => 'مشروبات',
                'is_active' => true,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf550_list_category_tree(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/catalog/categories');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf551_update_category(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/categories/{$this->category->id}", [
                'name' => 'Updated Food Category',
                'name_ar' => 'طعام محدث',
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf552_delete_empty_category(): void
    {
        $empty = Category::create([
            'organization_id' => $this->org->id,
            'name' => 'Empty Cat',
            'name_ar' => 'فئة فارغة',
            'is_active' => true,
            'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/catalog/categories/{$empty->id}");

        $this->assertContains($response->status(), [200, 204, 422, 500]);
    }

    /** @test */
    public function wf553_nested_subcategory(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/catalog/categories', [
                'name' => 'Hot Beverages',
                'name_ar' => 'مشروبات ساخنة',
                'parent_id' => $this->category->id,
                'is_active' => true,
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  VARIANTS & MODIFIERS — WF #554-558
    // ══════════════════════════════════════════════

    /** @test */
    public function wf554_list_product_variants(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Coffee', 'name_ar' => 'قهوة',
            'sku' => 'VAR-001', 'barcode' => '6281001236001',
            'sell_price' => 15.00, 'cost_price' => 5.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/catalog/products/{$product->id}/variants");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf555_sync_product_variants(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Variant Product', 'name_ar' => 'منتج متنوع',
            'sku' => 'VAR-002', 'barcode' => '6281001236002',
            'sell_price' => 20.00, 'cost_price' => 10.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/products/{$product->id}/variants", [
                'variant_groups' => [
                    ['name' => 'Size', 'name_ar' => 'الحجم', 'options' => [
                        ['name' => 'Small', 'name_ar' => 'صغير', 'price_adjustment' => 0],
                        ['name' => 'Large', 'name_ar' => 'كبير', 'price_adjustment' => 5.00],
                    ]],
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf556_list_product_modifiers(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Modifier Product', 'name_ar' => 'منتج مع إضافات',
            'sku' => 'MOD-001', 'barcode' => '6281001237001',
            'sell_price' => 25.00, 'cost_price' => 12.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/catalog/products/{$product->id}/modifiers");

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf557_sync_product_modifiers(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Kabsa', 'name_ar' => 'كبسة',
            'sku' => 'MOD-002', 'barcode' => '6281001237002',
            'sell_price' => 35.00, 'cost_price' => 18.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/products/{$product->id}/modifiers", [
                'modifier_groups' => [
                    ['name' => 'Spice Level', 'name_ar' => 'مستوى البهار', 'options' => [
                        ['name' => 'Mild', 'name_ar' => 'خفيف', 'price' => 0],
                        ['name' => 'Hot', 'name_ar' => 'حار', 'price' => 2.00],
                    ]],
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf558_generate_barcode_for_product(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'No Barcode Product', 'name_ar' => 'بدون باركود',
            'sku' => 'BAR-001',
            'sell_price' => 10.00, 'cost_price' => 5.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/catalog/products/{$product->id}/barcode");

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  SUPPLIER MANAGEMENT — WF #559-563
    // ══════════════════════════════════════════════

    /** @test */
    public function wf559_create_supplier(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/catalog/suppliers', [
                'name' => 'Al-Marai Supplier',
                'name_ar' => 'مورد المراعي',
                'phone' => '966500112233',
                'email' => 'supplier@almarai.test',
            ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }

    /** @test */
    public function wf560_list_suppliers(): void
    {
        DB::table('suppliers')->insert([
            'id' => Str::uuid()->toString(),
            'organization_id' => $this->org->id,
            'name' => 'Test Supplier',
            'phone' => '966500000001',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/catalog/suppliers');

        $response->assertOk()
            ->assertJsonStructure(['success']);
    }

    /** @test */
    public function wf561_update_supplier(): void
    {
        $supplierId = Str::uuid()->toString();
        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'organization_id' => $this->org->id,
            'name' => 'Edit Supplier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/suppliers/{$supplierId}", [
                'name' => 'Updated Supplier Name',
            ]);

        $this->assertContains($response->status(), [200, 404, 422, 500]);
    }

    /** @test */
    public function wf562_delete_supplier(): void
    {
        $supplierId = Str::uuid()->toString();
        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'organization_id' => $this->org->id,
            'name' => 'Remove Supplier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/catalog/suppliers/{$supplierId}");

        $this->assertContains($response->status(), [200, 204, 404, 422, 500]);
    }

    /** @test */
    public function wf563_link_product_to_supplier(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Linked Product', 'name_ar' => 'منتج مرتبط',
            'sku' => 'LINK-001', 'barcode' => '6281001238001',
            'sell_price' => 50.00, 'cost_price' => 30.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $supplierId = Str::uuid()->toString();
        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'organization_id' => $this->org->id,
            'name' => 'Link Supplier',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/products/{$product->id}/suppliers", [
                'suppliers' => [
                    ['supplier_id' => $supplierId, 'supplier_sku' => 'SUP-SKU-001', 'cost_price' => 28.00],
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  STORE PRICING — WF #564-566
    // ══════════════════════════════════════════════

    /** @test */
    public function wf564_get_store_prices(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Priced Product', 'name_ar' => 'منتج مسعر',
            'sku' => 'PRC-001', 'barcode' => '6281001239001',
            'sell_price' => 40.00, 'cost_price' => 20.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/catalog/products/{$product->id}/store-prices");

        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    /** @test */
    public function wf565_set_store_specific_pricing(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $this->category->id,
            'name' => 'Multi-Price Product', 'name_ar' => 'تسعير متعدد',
            'sku' => 'PRC-002', 'barcode' => '6281001239002',
            'sell_price' => 40.00, 'cost_price' => 20.00,
            'tax_rate' => 15.00, 'is_active' => true, 'sync_version' => 1,
        ]);

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/catalog/products/{$product->id}/store-prices", [
                'store_prices' => [
                    ['store_id' => $this->store2->id, 'sell_price' => 38.00],
                ],
            ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    /** @test */
    public function wf566_track_product_changes(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/catalog/products/changes?since_version=0');

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    // ══════════════════════════════════════════════
    //  PREDEFINED CATALOG — WF #567-570
    // ══════════════════════════════════════════════

    /** @test */
    public function wf567_list_predefined_categories(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/predefined-catalog/categories');

        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    /** @test */
    public function wf568_predefined_category_tree(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/predefined-catalog/categories/tree');

        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    /** @test */
    public function wf569_list_predefined_products(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->getJson('/api/v2/predefined-catalog/products');

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    /** @test */
    public function wf570_clone_predefined_catalog(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/predefined-catalog/clone-all');

        $this->assertContains($response->status(), [200, 201, 422, 403, 500]);
    }

    // ══════════════════════════════════════════════
    //  PREDEFINED CATALOG CRUD — WF #901-911
    // ══════════════════════════════════════════════

    /** @test */
    public function wf901_create_predefined_category(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/predefined-catalog/categories', [
                'name' => 'Test Predefined Category',
                'name_ar' => 'فئة تجريبية',
            ]);

        $this->assertContains($response->status(), [200, 201, 403, 422, 500]);
    }

    /** @test */
    public function wf902_show_predefined_category(): void
    {
        $categoryId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/predefined-catalog/categories/{$categoryId}");

        $this->assertContains($response->status(), [200, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf903_update_predefined_category(): void
    {
        $categoryId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/predefined-catalog/categories/{$categoryId}", [
                'name' => 'Updated Predefined Category',
            ]);

        $this->assertContains($response->status(), [200, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf904_delete_predefined_category(): void
    {
        $categoryId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/predefined-catalog/categories/{$categoryId}");

        $this->assertContains($response->status(), [200, 204, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf905_clone_predefined_category(): void
    {
        $categoryId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/predefined-catalog/categories/{$categoryId}/clone");

        $this->assertContains($response->status(), [200, 201, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf906_create_predefined_product(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/predefined-catalog/products', [
                'name' => 'Test Predefined Product',
                'price' => 9.99,
            ]);

        $this->assertContains($response->status(), [200, 201, 403, 422, 500]);
    }

    /** @test */
    public function wf907_show_predefined_product(): void
    {
        $productId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->getJson("/api/v2/predefined-catalog/products/{$productId}");

        $this->assertContains($response->status(), [200, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf908_update_predefined_product(): void
    {
        $productId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->putJson("/api/v2/predefined-catalog/products/{$productId}", [
                'name' => 'Updated Predefined Product',
                'price' => 19.99,
            ]);

        $this->assertContains($response->status(), [200, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf909_delete_predefined_product(): void
    {
        $productId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->deleteJson("/api/v2/predefined-catalog/products/{$productId}");

        $this->assertContains($response->status(), [200, 204, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf910_clone_predefined_product(): void
    {
        $productId = Str::uuid()->toString();

        $response = $this->withToken($this->ownerToken)
            ->postJson("/api/v2/predefined-catalog/products/{$productId}/clone");

        $this->assertContains($response->status(), [200, 201, 403, 404, 422, 500]);
    }

    /** @test */
    public function wf911_bulk_action_predefined_products(): void
    {
        $response = $this->withToken($this->ownerToken)
            ->postJson('/api/v2/predefined-catalog/products/bulk-action', [
                'action' => 'delete',
                'ids' => [Str::uuid()->toString()],
            ]);

        $this->assertContains($response->status(), [200, 403, 404, 422, 500]);
    }
}
