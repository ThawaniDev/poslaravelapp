<?php

namespace Tests\Feature\Inventory;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\WasteRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WasteApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@waste.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        $this->product = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Coffee Beans',
            'sell_price' => 10.00,
            'sync_version' => 1,
        ]);

        // Create stock level so waste deduction works
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'reorder_point' => 10,
            'average_cost' => 5.00,
            'sync_version' => 1,
        ]);
    }

    public function test_can_create_waste_record(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 5,
                'unit_cost' => 5.00,
                'reason' => 'expired',
                'batch_number' => 'BATCH-001',
                'notes' => 'Past expiry date',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.reason', 'expired')
            ->assertJsonPath('data.notes', 'Past expiry date');

        // Verify stock was deducted (100 - 5 = 95)
        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(95, (float) $level->quantity);
    }

    public function test_can_create_waste_record_without_unit_cost(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 3,
                'reason' => 'damaged',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        // unit_cost should be pulled from stock level average_cost (5.00)
        $this->assertEquals('5.0000', $response->json('data.unit_cost'));
    }

    public function test_can_list_waste_records(): void
    {
        // Create a waste record
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 2,
                'reason' => 'spillage',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/waste-records?store_id=' . $this->store->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, count($response->json('data.data')));
    }

    public function test_can_filter_waste_records_by_reason(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 2,
                'reason' => 'expired',
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 1,
                'reason' => 'damaged',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/waste-records?store_id=' . $this->store->id . '&reason=expired');

        $response->assertOk();

        $records = $response->json('data.data');
        foreach ($records as $record) {
            $this->assertEquals('expired', $record['reason']);
        }
    }

    public function test_can_filter_waste_records_by_product(): void
    {
        $product2 = Product::create([
            'organization_id' => $this->org->id,
            'name' => 'Tea Leaves',
            'sell_price' => 8.00,
            'sync_version' => 1,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'quantity' => 50,
            'average_cost' => 3.00,
            'sync_version' => 1,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 2,
                'reason' => 'expired',
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $product2->id,
                'quantity' => 1,
                'reason' => 'damaged',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/inventory/waste-records?store_id=' . $this->store->id . '&product_id=' . $product2->id);

        $response->assertOk();

        $records = $response->json('data.data');
        foreach ($records as $record) {
            $this->assertEquals($product2->id, $record['product_id']);
        }
    }

    public function test_create_waste_record_validation(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id', 'product_id', 'quantity', 'reason']);
    }

    public function test_waste_record_invalid_reason(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 2,
                'reason' => 'invalid_reason',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_multiple_waste_records_deduct_stock_cumulatively(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 10,
                'reason' => 'expired',
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v2/inventory/waste-records', [
                'store_id' => $this->store->id,
                'product_id' => $this->product->id,
                'quantity' => 5,
                'reason' => 'damaged',
            ]);

        // Stock should be 100 - 10 - 5 = 85
        $level = StockLevel::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(85, (float) $level->quantity);
    }
}
