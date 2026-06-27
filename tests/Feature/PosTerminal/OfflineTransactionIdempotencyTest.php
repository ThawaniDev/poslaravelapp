<?php

namespace Tests\Feature\PosTerminal;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the unified offline idempotency: an online sale and its later
 * offline-queue replay share the same `client_uuid` → `external_id` key, so a
 * sale that commits online but whose response is lost (timeout) is never
 * duplicated when the queue replays it through the batch endpoint.
 */
class OfflineTransactionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Idem Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier_idem@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $this->token = $user->createToken('test', ['*'])->plainTextToken;
    }

    private function salePayload(string $clientUuid): array
    {
        return [
            'type' => 'sale',
            'client_uuid' => $clientUuid,
            'subtotal' => 100.00,
            'tax_amount' => 15.00,
            'total_amount' => 115.00,
            'items' => [
                ['product_name' => 'Coffee', 'quantity' => 2, 'unit_price' => 50.00, 'line_total' => 100.00],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 115.00],
            ],
        ];
    }

    public function test_online_create_is_idempotent_on_client_uuid(): void
    {
        $payload = $this->salePayload('uuid-online-1');

        $r1 = $this->withToken($this->token)->postJson('/api/v2/pos/transactions', $payload);
        $r1->assertStatus(201);
        $id1 = $r1->json('data.id');

        // A retried online submit (double-tap / client retry) returns the same
        // transaction instead of creating a second one.
        $r2 = $this->withToken($this->token)->postJson('/api/v2/pos/transactions', $payload);
        $r2->assertStatus(201);

        $this->assertEquals($id1, $r2->json('data.id'));
        $this->assertEquals(1, Transaction::where('store_id', $this->store->id)->count());
        $this->assertDatabaseHas('transactions', ['id' => $id1, 'external_id' => 'offline:uuid-online-1']);
    }

    public function test_batch_replay_does_not_duplicate_a_committed_online_sale(): void
    {
        $payload = $this->salePayload('uuid-timeout-1');

        // 1) Online POST commits server-side …
        $r1 = $this->withToken($this->token)->postJson('/api/v2/pos/transactions', $payload);
        $r1->assertStatus(201);
        $id1 = $r1->json('data.id');

        // 2) … but the client treated it as a network failure and queued it.
        //    The drainer now replays the SAME sale via the batch endpoint.
        $batch = [
            'register_id' => 'reg-1',
            'transactions' => [
                $payload + ['transaction_number' => 'OFF-REG1-1'],
            ],
        ];
        $rb = $this->withToken($this->token)->postJson('/api/v2/pos/transactions/batch', $batch);

        $rb->assertOk()->assertJsonPath('data.results.0.status', 'duplicate');
        // Crucially, still exactly ONE transaction for that sale.
        $this->assertEquals(1, Transaction::where('store_id', $this->store->id)->count());
        $this->assertEquals($id1, $rb->json('data.results.0.transaction.id'));
    }

    public function test_batch_replay_is_idempotent_across_retries(): void
    {
        $batch = [
            'register_id' => 'reg-1',
            'transactions' => [
                $this->salePayload('uuid-batch-1') + ['transaction_number' => 'OFF-REG1-2'],
            ],
        ];

        $first = $this->withToken($this->token)->postJson('/api/v2/pos/transactions/batch', $batch);
        $first->assertOk()->assertJsonPath('data.results.0.status', 'created');

        $second = $this->withToken($this->token)->postJson('/api/v2/pos/transactions/batch', $batch);
        $second->assertOk()->assertJsonPath('data.results.0.status', 'duplicate');

        $this->assertEquals(1, Transaction::where('store_id', $this->store->id)->count());
    }
}
