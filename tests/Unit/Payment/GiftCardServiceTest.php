<?php

namespace Tests\Unit\Payment;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\Payment\Services\GiftCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for GiftCardService.
 *
 * Covers: issue, balance check, redemption, partial redemption,
 * expiry, deactivation, status transitions, cross-branch.
 */
class GiftCardServiceTest extends TestCase
{
    use RefreshDatabase;

    private GiftCardService $service;
    private User $user;
    private Organization $org;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GiftCardService::class);

        $this->org = Organization::create([
            'name' => 'GiftCard Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'GiftCard Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'Issuer',
            'email' => 'issuer@gc.test',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    // ─── Issue ────────────────────────────────────────────────

    public function test_issue_creates_gift_card_with_active_status(): void
    {
        $card = $this->service->issue([
            'amount' => 100.00,
            'recipient_name' => 'Alice',
        ], $this->user);

        $this->assertInstanceOf(GiftCard::class, $card);
        $this->assertEquals(GiftCardStatus::Active, $card->status);
        $this->assertEquals(100.00, (float) $card->initial_amount);
        $this->assertEquals(100.00, (float) $card->balance);
        $this->assertEquals('Alice', $card->recipient_name);
        $this->assertEquals($this->org->id, $card->organization_id);
        $this->assertEquals($this->store->id, $card->issued_at_store);
    }

    public function test_issue_generates_unique_code_when_not_provided(): void
    {
        $card1 = $this->service->issue(['amount' => 50], $this->user);
        $card2 = $this->service->issue(['amount' => 50], $this->user);

        $this->assertNotEquals($card1->code, $card2->code);
    }

    public function test_issue_uses_provided_code(): void
    {
        $card = $this->service->issue([
            'amount' => 200,
            'code' => 'CUSTOM-CODE-001',
        ], $this->user);

        $this->assertEquals('CUSTOM-CODE-001', $card->code);
    }

    public function test_issue_sets_expiry_12_months_from_now_by_default(): void
    {
        $card = $this->service->issue(['amount' => 100], $this->user);

        $this->assertNotNull($card->expires_at);
        $expiresIn = now()->diffInMonths($card->expires_at);
        $this->assertGreaterThanOrEqual(11, $expiresIn);
        $this->assertLessThanOrEqual(13, $expiresIn);
    }

    public function test_issue_accepts_custom_expiry(): void
    {
        $expires = now()->addMonths(6)->toDateString();
        $card = $this->service->issue([
            'amount' => 50,
            'expires_at' => $expires,
        ], $this->user);

        $this->assertEquals($expires, $card->expires_at?->toDateString());
    }

    // ─── Balance Check ────────────────────────────────────────

    public function test_check_balance_returns_correct_data(): void
    {
        $card = $this->makeCard('GC-BAL-001', 100, 75);

        $result = $this->service->checkBalance('GC-BAL-001');

        $this->assertEquals('GC-BAL-001', $result['code']);
        $this->assertEquals(75, (float) $result['balance']);
        $this->assertEquals(100, (float) $result['initial_amount']);
        $this->assertEquals('active', $result['status']);
        $this->assertFalse($result['is_expired']);
    }

    public function test_check_balance_marks_expired_card(): void
    {
        $this->makeCard('GC-EXP-CHECK', 100, 100, expiresAt: now()->subDay());

        $result = $this->service->checkBalance('GC-EXP-CHECK');

        $this->assertTrue($result['is_expired']);
    }

    public function test_check_balance_throws_for_missing_card(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->checkBalance('NON-EXISTENT-CODE');
    }

    // ─── Redeem ───────────────────────────────────────────────

    public function test_redeem_decreases_balance(): void
    {
        $this->makeCard('GC-REDEEM', 100, 100);

        $card = $this->service->redeem('GC-REDEEM', 40);

        $this->assertEquals(60, (float) $card->balance);
        $this->assertEquals(GiftCardStatus::Active, $card->status);
    }

    public function test_full_redeem_sets_status_to_redeemed(): void
    {
        $this->makeCard('GC-FULL-REDEEM', 50, 50);

        $card = $this->service->redeem('GC-FULL-REDEEM', 50);

        $this->assertEquals(0, (float) $card->balance);
        $this->assertEquals(GiftCardStatus::Redeemed, $card->status);
    }

    public function test_partial_redeem_keeps_active_status(): void
    {
        $this->makeCard('GC-PARTIAL', 200, 200);

        $card = $this->service->redeem('GC-PARTIAL', 50);
        $this->assertEquals(GiftCardStatus::Active, $card->status);

        $card = $this->service->redeem('GC-PARTIAL', 50);
        $this->assertEquals(100, (float) $card->balance);
        $this->assertEquals(GiftCardStatus::Active, $card->status);
    }

    public function test_multiple_partial_redemptions_until_exhausted(): void
    {
        $this->makeCard('GC-MULTI', 100, 100);

        $this->service->redeem('GC-MULTI', 30);
        $this->service->redeem('GC-MULTI', 30);
        $this->service->redeem('GC-MULTI', 30);
        $card = $this->service->redeem('GC-MULTI', 10);

        $this->assertEquals(0, (float) $card->balance);
        $this->assertEquals(GiftCardStatus::Redeemed, $card->status);
    }

    public function test_redeem_throws_for_inactive_card(): void
    {
        $this->makeCard('GC-INACTIVE', 100, 100, status: GiftCardStatus::Deactivated);

        $this->expectException(\RuntimeException::class);
        $this->service->redeem('GC-INACTIVE', 10);
    }

    public function test_redeem_throws_when_amount_exceeds_balance(): void
    {
        $this->makeCard('GC-LOW', 50, 20);

        $this->expectException(\RuntimeException::class);
        $this->service->redeem('GC-LOW', 30);
    }

    public function test_redeem_throws_for_expired_card(): void
    {
        $this->makeCard('GC-PAST-EXP', 100, 100, expiresAt: now()->subDay());

        $this->expectException(\RuntimeException::class);
        $this->service->redeem('GC-PAST-EXP', 10);
    }

    public function test_redeem_expired_card_updates_status_to_expired(): void
    {
        $this->makeCard('GC-MARK-EXP', 100, 100, expiresAt: now()->subDay());

        try {
            $this->service->redeem('GC-MARK-EXP', 10);
        } catch (\RuntimeException) {}

        $card = GiftCard::where('code', 'GC-MARK-EXP')->first();
        $this->assertEquals(GiftCardStatus::Expired, $card->status);
    }

    // ─── Deactivate ───────────────────────────────────────────

    public function test_deactivate_active_card_by_code(): void
    {
        $this->makeCard('GC-DEACT', 100, 80);

        $card = $this->service->deactivateByCode('GC-DEACT', $this->org->id);

        $this->assertEquals(GiftCardStatus::Deactivated, $card->status);
    }

    public function test_deactivate_throws_for_redeemed_card(): void
    {
        $this->makeCard('GC-FULLY-REDEEMED', 100, 0, status: GiftCardStatus::Redeemed);

        $this->expectException(\RuntimeException::class);
        $this->service->deactivateByCode('GC-FULLY-REDEEMED', $this->org->id);
    }

    public function test_deactivate_expired_card_is_allowed(): void
    {
        $this->makeCard('GC-EXP-DEACT', 100, 50, status: GiftCardStatus::Expired);

        $card = $this->service->deactivateByCode('GC-EXP-DEACT', $this->org->id);
        $this->assertEquals(GiftCardStatus::Deactivated, $card->status);
    }

    public function test_deactivate_throws_for_wrong_organization(): void
    {
        $this->makeCard('GC-WRONG-ORG', 100, 100);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->deactivateByCode('GC-WRONG-ORG', '00000000-0000-0000-0000-000000000099');
    }

    // ─── List ─────────────────────────────────────────────────

    public function test_list_returns_all_cards_for_organization(): void
    {
        $this->makeCard('GC-LIST-A', 100, 100);
        $this->makeCard('GC-LIST-B', 200, 200);

        $result = $this->service->list($this->org->id);
        $this->assertEquals(2, $result->total());
    }

    public function test_list_filters_by_status(): void
    {
        $this->makeCard('GC-ACT', 100, 100, status: GiftCardStatus::Active);
        $this->makeCard('GC-EXP', 100, 0, status: GiftCardStatus::Expired);
        $this->makeCard('GC-RED', 100, 0, status: GiftCardStatus::Redeemed);

        $active = $this->service->list($this->org->id, 20, ['status' => 'active']);
        $this->assertEquals(1, $active->total());
        $this->assertEquals('GC-ACT', $active->items()[0]->code);
    }

    // ─── Cross-branch (same org) ──────────────────────────────

    public function test_gift_card_issued_at_branch_a_redeemable_at_branch_b(): void
    {
        // Gift cards belong to org, not store — same org can redeem across branches
        $this->makeCard('GC-XBRANCH', 100, 100);

        // Can check balance regardless of which store
        $result = $this->service->checkBalance('GC-XBRANCH');
        $this->assertEquals('GC-XBRANCH', $result['code']);

        // Can redeem regardless of which store
        $card = $this->service->redeem('GC-XBRANCH', 40);
        $this->assertEquals(60, (float) $card->balance);
    }

    // ─── Find ─────────────────────────────────────────────────

    public function test_find_by_code_returns_null_for_missing(): void
    {
        $card = $this->service->findByCode('NO-SUCH-CODE');
        $this->assertNull($card);
    }

    public function test_find_by_code_returns_card(): void
    {
        $this->makeCard('GC-FINDME', 50, 50);
        $card = $this->service->findByCode('GC-FINDME');
        $this->assertNotNull($card);
        $this->assertEquals('GC-FINDME', $card->code);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function makeCard(
        string $code,
        float $initialAmount,
        float $balance,
        GiftCardStatus $status = GiftCardStatus::Active,
        ?\DateTimeInterface $expiresAt = null,
    ): GiftCard {
        return GiftCard::create([
            'organization_id' => $this->org->id,
            'code' => $code,
            'barcode' => $code,
            'initial_amount' => $initialAmount,
            'balance' => $balance,
            'status' => $status,
            'issued_by' => $this->user->id,
            'issued_at_store' => $this->store->id,
            'expires_at' => $expiresAt ?? now()->addMonths(12)->toDateString(),
        ]);
    }
}
