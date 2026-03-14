<?php

namespace Tests\Feature\Admin;

use App\Domain\AccountingIntegration\Models\AccountingExport;
use App\Domain\AccountingIntegration\Models\AccountMapping;
use App\Domain\AccountingIntegration\Models\AutoExportConfig;
use App\Domain\AccountingIntegration\Models\StoreAccountingConfig;
use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Payment\Models\CashEvent;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\Expense;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\Payment\Models\GiftCardTransaction;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\ThawaniIntegration\Models\ThawaniOrderMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniSettlement;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix = '/api/v2/admin/financial-operations';

    protected function setUp(): void
    {
        parent::setUp();

        $admin = AdminUser::forceCreate([
            'id'            => Str::uuid(),
            'name'          => 'Admin',
            'email'         => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($admin, ['*'], 'admin-api');
    }

    private function createStore(): Store
    {
        $org = Organization::forceCreate([
            'id'   => Str::uuid(),
            'name' => 'Test Org',
        ]);

        return Store::forceCreate([
            'id'              => Str::uuid(),
            'organization_id' => $org->id,
            'name'            => 'Test Store',
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // OVERVIEW
    // ────────────────────────────────────────────────────────────
    public function test_overview_returns_aggregated_stats(): void
    {
        $store = $this->createStore();

        Payment::forceCreate(['id' => Str::uuid(), 'transaction_id' => Str::uuid(), 'method' => 'cash', 'amount' => 100.00]);
        Payment::forceCreate(['id' => Str::uuid(), 'transaction_id' => Str::uuid(), 'method' => 'card_visa', 'amount' => 200.00]);

        Refund::forceCreate(['id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash', 'amount' => 30.00, 'status' => 'completed', 'processed_by' => Str::uuid()]);
        Refund::forceCreate(['id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash', 'amount' => 20.00, 'status' => 'pending', 'processed_by' => Str::uuid()]);

        CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'opened_by' => Str::uuid(), 'status' => 'open']);
        CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'opened_by' => Str::uuid(), 'status' => 'closed']);

        Expense::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'amount' => 50.00, 'category' => 'supplies', 'recorded_by' => Str::uuid()]);

        GiftCard::forceCreate(['id' => Str::uuid(), 'organization_id' => Str::uuid(), 'code' => 'GC001', 'initial_amount' => 100.00, 'balance' => 75.00, 'status' => 'active']);

        $response = $this->getJson("{$this->prefix}/overview");
        $response->assertOk();
        $response->assertJsonPath('data.payments.total', 2);
        $response->assertJsonPath('data.refunds.total', 2);
        $response->assertJsonPath('data.refunds.pending', 1);
        $response->assertJsonPath('data.refunds.completed', 1);
        $response->assertJsonPath('data.cash_sessions.total', 2);
        $response->assertJsonPath('data.cash_sessions.open', 1);
        $response->assertJsonPath('data.expenses.total', 1);
        $response->assertJsonPath('data.gift_cards.total', 1);
        $response->assertJsonPath('data.gift_cards.active', 1);
    }

    // ────────────────────────────────────────────────────────────
    // PAYMENTS
    // ────────────────────────────────────────────────────────────
    public function test_list_payments(): void
    {
        Payment::forceCreate(['id' => Str::uuid(), 'transaction_id' => Str::uuid(), 'method' => 'cash', 'amount' => 100.00]);
        Payment::forceCreate(['id' => Str::uuid(), 'transaction_id' => Str::uuid(), 'method' => 'card_visa', 'amount' => 200.00]);

        $response = $this->getJson("{$this->prefix}/payments");
        $response->assertOk()->assertJsonPath('data.total', 2);
    }

    public function test_list_payments_filter_by_method(): void
    {
        Payment::forceCreate(['id' => Str::uuid(), 'transaction_id' => Str::uuid(), 'method' => 'cash', 'amount' => 100.00]);
        Payment::forceCreate(['id' => Str::uuid(), 'transaction_id' => Str::uuid(), 'method' => 'card_visa', 'amount' => 200.00]);

        $response = $this->getJson("{$this->prefix}/payments?method=cash");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_payment(): void
    {
        $payment = Payment::forceCreate(['id' => Str::uuid(), 'transaction_id' => Str::uuid(), 'method' => 'cash', 'amount' => 150.00]);

        $response = $this->getJson("{$this->prefix}/payments/{$payment->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $payment->id);
    }

    public function test_show_payment_not_found(): void
    {
        $this->getJson("{$this->prefix}/payments/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // REFUNDS
    // ────────────────────────────────────────────────────────────
    public function test_list_refunds(): void
    {
        Refund::forceCreate(['id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash', 'amount' => 30.00, 'status' => 'completed', 'processed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/refunds");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_refunds_filter_by_status(): void
    {
        Refund::forceCreate(['id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash', 'amount' => 30.00, 'status' => 'completed', 'processed_by' => Str::uuid()]);
        Refund::forceCreate(['id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash', 'amount' => 20.00, 'status' => 'pending', 'processed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/refunds?status=pending");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_refund(): void
    {
        $refund = Refund::forceCreate(['id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash', 'amount' => 30.00, 'status' => 'completed', 'processed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/refunds/{$refund->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $refund->id);
    }

    public function test_show_refund_not_found(): void
    {
        $this->getJson("{$this->prefix}/refunds/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // CASH SESSIONS
    // ────────────────────────────────────────────────────────────
    public function test_list_cash_sessions(): void
    {
        $store = $this->createStore();
        CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'opened_by' => Str::uuid(), 'status' => 'open']);

        $response = $this->getJson("{$this->prefix}/cash-sessions");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_cash_sessions_filter_by_status(): void
    {
        $store = $this->createStore();
        CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'opened_by' => Str::uuid(), 'status' => 'open']);
        CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'opened_by' => Str::uuid(), 'status' => 'closed']);

        $response = $this->getJson("{$this->prefix}/cash-sessions?status=open");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_cash_sessions_filter_by_store(): void
    {
        $store1 = $this->createStore();
        $store2 = $this->createStore();
        CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store1->id, 'opened_by' => Str::uuid(), 'status' => 'open']);
        CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store2->id, 'opened_by' => Str::uuid(), 'status' => 'open']);

        $response = $this->getJson("{$this->prefix}/cash-sessions?store_id={$store1->id}");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_cash_session(): void
    {
        $store = $this->createStore();
        $session = CashSession::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'opened_by' => Str::uuid(), 'status' => 'open']);

        $response = $this->getJson("{$this->prefix}/cash-sessions/{$session->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $session->id);
    }

    public function test_show_cash_session_not_found(): void
    {
        $this->getJson("{$this->prefix}/cash-sessions/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // CASH EVENTS
    // ────────────────────────────────────────────────────────────
    public function test_list_cash_events(): void
    {
        CashEvent::forceCreate(['id' => Str::uuid(), 'cash_session_id' => Str::uuid(), 'type' => 'cash_in', 'amount' => 50.00, 'performed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/cash-events");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_cash_events_filter_by_session(): void
    {
        $sessionId = Str::uuid()->toString();
        CashEvent::forceCreate(['id' => Str::uuid(), 'cash_session_id' => $sessionId, 'type' => 'cash_in', 'amount' => 50.00, 'performed_by' => Str::uuid()]);
        CashEvent::forceCreate(['id' => Str::uuid(), 'cash_session_id' => Str::uuid(), 'type' => 'cash_out', 'amount' => 30.00, 'performed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/cash-events?cash_session_id={$sessionId}");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_cash_event(): void
    {
        $event = CashEvent::forceCreate(['id' => Str::uuid(), 'cash_session_id' => Str::uuid(), 'type' => 'cash_in', 'amount' => 50.00, 'performed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/cash-events/{$event->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $event->id);
    }

    public function test_show_cash_event_not_found(): void
    {
        $this->getJson("{$this->prefix}/cash-events/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // EXPENSES
    // ────────────────────────────────────────────────────────────
    public function test_list_expenses(): void
    {
        $store = $this->createStore();
        Expense::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'amount' => 50.00, 'category' => 'supplies', 'recorded_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/expenses");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_expenses_filter_by_category(): void
    {
        $store = $this->createStore();
        Expense::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'amount' => 50.00, 'category' => 'supplies', 'recorded_by' => Str::uuid()]);
        Expense::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'amount' => 30.00, 'category' => 'food', 'recorded_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/expenses?category=food");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_expense(): void
    {
        $store = $this->createStore();
        $expense = Expense::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'amount' => 50.00, 'category' => 'supplies', 'recorded_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/expenses/{$expense->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $expense->id);
    }

    public function test_show_expense_not_found(): void
    {
        $this->getJson("{$this->prefix}/expenses/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // GIFT CARDS
    // ────────────────────────────────────────────────────────────
    public function test_list_gift_cards(): void
    {
        GiftCard::forceCreate(['id' => Str::uuid(), 'organization_id' => Str::uuid(), 'code' => 'GC001', 'initial_amount' => 100.00, 'balance' => 75.00, 'status' => 'active']);

        $response = $this->getJson("{$this->prefix}/gift-cards");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_gift_cards_filter_by_status(): void
    {
        GiftCard::forceCreate(['id' => Str::uuid(), 'organization_id' => Str::uuid(), 'code' => 'GC001', 'initial_amount' => 100.00, 'balance' => 75.00, 'status' => 'active']);
        GiftCard::forceCreate(['id' => Str::uuid(), 'organization_id' => Str::uuid(), 'code' => 'GC002', 'initial_amount' => 50.00, 'balance' => 0, 'status' => 'redeemed']);

        $response = $this->getJson("{$this->prefix}/gift-cards?status=active");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_gift_card(): void
    {
        $card = GiftCard::forceCreate(['id' => Str::uuid(), 'organization_id' => Str::uuid(), 'code' => 'GC001', 'initial_amount' => 100.00, 'balance' => 75.00, 'status' => 'active']);

        $response = $this->getJson("{$this->prefix}/gift-cards/{$card->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $card->id);
    }

    public function test_show_gift_card_not_found(): void
    {
        $this->getJson("{$this->prefix}/gift-cards/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // GIFT CARD TRANSACTIONS
    // ────────────────────────────────────────────────────────────
    public function test_list_gift_card_transactions(): void
    {
        $store = $this->createStore();
        GiftCardTransaction::forceCreate(['id' => Str::uuid(), 'gift_card_id' => Str::uuid(), 'type' => 'redemption', 'amount' => 25.00, 'balance_after' => 75.00, 'store_id' => $store->id, 'performed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/gift-card-transactions");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_gift_card_transactions_filter_by_card(): void
    {
        $store = $this->createStore();
        $cardId = Str::uuid()->toString();
        GiftCardTransaction::forceCreate(['id' => Str::uuid(), 'gift_card_id' => $cardId, 'type' => 'redemption', 'amount' => 25.00, 'balance_after' => 75.00, 'store_id' => $store->id, 'performed_by' => Str::uuid()]);
        GiftCardTransaction::forceCreate(['id' => Str::uuid(), 'gift_card_id' => Str::uuid(), 'type' => 'top_up', 'amount' => 50.00, 'balance_after' => 150.00, 'store_id' => $store->id, 'performed_by' => Str::uuid()]);

        $response = $this->getJson("{$this->prefix}/gift-card-transactions?gift_card_id={$cardId}");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    // ────────────────────────────────────────────────────────────
    // ACCOUNTING CONFIGS
    // ────────────────────────────────────────────────────────────
    public function test_list_accounting_configs(): void
    {
        $store = $this->createStore();
        StoreAccountingConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks', 'access_token_encrypted' => 'enc_token', 'refresh_token_encrypted' => 'enc_refresh', 'token_expires_at' => now()->addDay()]);

        $response = $this->getJson("{$this->prefix}/accounting-configs");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_accounting_configs_filter_by_provider(): void
    {
        $store1 = $this->createStore();
        $store2 = $this->createStore();
        StoreAccountingConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store1->id, 'provider' => 'quickbooks', 'access_token_encrypted' => 'enc', 'refresh_token_encrypted' => 'enc', 'token_expires_at' => now()->addDay()]);
        StoreAccountingConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store2->id, 'provider' => 'xero', 'access_token_encrypted' => 'enc', 'refresh_token_encrypted' => 'enc', 'token_expires_at' => now()->addDay()]);

        $response = $this->getJson("{$this->prefix}/accounting-configs?provider=xero");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_accounting_config(): void
    {
        $store = $this->createStore();
        $config = StoreAccountingConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks', 'access_token_encrypted' => 'enc', 'refresh_token_encrypted' => 'enc', 'token_expires_at' => now()->addDay()]);

        $response = $this->getJson("{$this->prefix}/accounting-configs/{$config->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $config->id);
    }

    public function test_show_accounting_config_not_found(): void
    {
        $this->getJson("{$this->prefix}/accounting-configs/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // ACCOUNT MAPPINGS
    // ────────────────────────────────────────────────────────────
    public function test_list_account_mappings(): void
    {
        $store = $this->createStore();
        AccountMapping::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'pos_account_key' => 'sales', 'provider_account_id' => 'acc_123', 'provider_account_name' => 'Sales Revenue']);

        $response = $this->getJson("{$this->prefix}/account-mappings");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_account_mapping(): void
    {
        $store = $this->createStore();
        $mapping = AccountMapping::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'pos_account_key' => 'sales', 'provider_account_id' => 'acc_123', 'provider_account_name' => 'Sales Revenue']);

        $response = $this->getJson("{$this->prefix}/account-mappings/{$mapping->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $mapping->id);
    }

    public function test_show_account_mapping_not_found(): void
    {
        $this->getJson("{$this->prefix}/account-mappings/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // ACCOUNTING EXPORTS
    // ────────────────────────────────────────────────────────────
    public function test_list_accounting_exports(): void
    {
        $store = $this->createStore();
        AccountingExport::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks', 'start_date' => '2025-01-01', 'end_date' => '2025-01-31', 'status' => 'success', 'triggered_by' => 'manual']);

        $response = $this->getJson("{$this->prefix}/accounting-exports");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_accounting_exports_filter_by_status(): void
    {
        $store = $this->createStore();
        AccountingExport::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks', 'start_date' => '2025-01-01', 'end_date' => '2025-01-31', 'status' => 'success', 'triggered_by' => 'manual']);
        AccountingExport::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks', 'start_date' => '2025-02-01', 'end_date' => '2025-02-28', 'status' => 'failed', 'triggered_by' => 'scheduled']);

        $response = $this->getJson("{$this->prefix}/accounting-exports?status=failed");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_accounting_export(): void
    {
        $store = $this->createStore();
        $export = AccountingExport::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks', 'start_date' => '2025-01-01', 'end_date' => '2025-01-31', 'status' => 'success', 'triggered_by' => 'manual']);

        $response = $this->getJson("{$this->prefix}/accounting-exports/{$export->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $export->id);
    }

    public function test_show_accounting_export_not_found(): void
    {
        $this->getJson("{$this->prefix}/accounting-exports/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // AUTO EXPORT CONFIGS
    // ────────────────────────────────────────────────────────────
    public function test_list_auto_export_configs(): void
    {
        $store = $this->createStore();
        AutoExportConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'enabled' => true, 'frequency' => 'daily']);

        $response = $this->getJson("{$this->prefix}/auto-export-configs");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_auto_export_config(): void
    {
        $store = $this->createStore();
        $config = AutoExportConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'enabled' => true, 'frequency' => 'daily']);

        $response = $this->getJson("{$this->prefix}/auto-export-configs/{$config->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $config->id);
    }

    public function test_show_auto_export_config_not_found(): void
    {
        $this->getJson("{$this->prefix}/auto-export-configs/" . Str::uuid())->assertNotFound();
    }

    public function test_update_auto_export_config(): void
    {
        $store = $this->createStore();
        $config = AutoExportConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'enabled' => false, 'frequency' => 'daily']);

        $response = $this->putJson("{$this->prefix}/auto-export-configs/{$config->id}", [
            'enabled'   => true,
            'frequency' => 'weekly',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('auto_export_configs', ['id' => $config->id, 'enabled' => true, 'frequency' => 'weekly']);
    }

    public function test_update_auto_export_config_validation(): void
    {
        $store = $this->createStore();
        $config = AutoExportConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'enabled' => false, 'frequency' => 'daily']);

        $response = $this->putJson("{$this->prefix}/auto-export-configs/{$config->id}", [
            'frequency' => 'invalid',
        ]);

        $response->assertUnprocessable();
    }

    public function test_update_auto_export_config_not_found(): void
    {
        $this->putJson("{$this->prefix}/auto-export-configs/" . Str::uuid(), ['enabled' => true])->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // THAWANI SETTLEMENTS
    // ────────────────────────────────────────────────────────────
    public function test_list_thawani_settlements(): void
    {
        $store = $this->createStore();
        ThawaniSettlement::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'settlement_date' => '2025-06-01', 'gross_amount' => 1000.00, 'commission_amount' => 50.00, 'net_amount' => 950.00, 'order_count' => 25]);

        $response = $this->getJson("{$this->prefix}/thawani-settlements");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_thawani_settlement(): void
    {
        $store = $this->createStore();
        $settlement = ThawaniSettlement::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'settlement_date' => '2025-06-01', 'gross_amount' => 1000.00, 'commission_amount' => 50.00, 'net_amount' => 950.00, 'order_count' => 25]);

        $response = $this->getJson("{$this->prefix}/thawani-settlements/{$settlement->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $settlement->id);
    }

    public function test_show_thawani_settlement_not_found(): void
    {
        $this->getJson("{$this->prefix}/thawani-settlements/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // THAWANI ORDERS
    // ────────────────────────────────────────────────────────────
    public function test_list_thawani_orders(): void
    {
        $store = $this->createStore();
        ThawaniOrderMapping::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'thawani_order_id' => 'TH001', 'thawani_order_number' => 'ORD001', 'status' => 'new', 'order_total' => 150.00]);

        $response = $this->getJson("{$this->prefix}/thawani-orders");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_thawani_orders_filter_by_status(): void
    {
        $store = $this->createStore();
        ThawaniOrderMapping::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'thawani_order_id' => 'TH001', 'thawani_order_number' => 'ORD001', 'status' => 'new', 'order_total' => 150.00]);
        ThawaniOrderMapping::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'thawani_order_id' => 'TH002', 'thawani_order_number' => 'ORD002', 'status' => 'completed', 'order_total' => 200.00]);

        $response = $this->getJson("{$this->prefix}/thawani-orders?status=completed");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_thawani_order(): void
    {
        $store = $this->createStore();
        $order = ThawaniOrderMapping::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'thawani_order_id' => 'TH001', 'thawani_order_number' => 'ORD001', 'status' => 'new', 'order_total' => 150.00]);

        $response = $this->getJson("{$this->prefix}/thawani-orders/{$order->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $order->id);
    }

    public function test_show_thawani_order_not_found(): void
    {
        $this->getJson("{$this->prefix}/thawani-orders/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // THAWANI STORE CONFIGS
    // ────────────────────────────────────────────────────────────
    public function test_list_thawani_store_configs(): void
    {
        $store = $this->createStore();
        ThawaniStoreConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'thawani_store_id' => 'TS001', 'is_connected' => true]);

        $response = $this->getJson("{$this->prefix}/thawani-store-configs");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_show_thawani_store_config(): void
    {
        $store = $this->createStore();
        $config = ThawaniStoreConfig::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'thawani_store_id' => 'TS001', 'is_connected' => true]);

        $response = $this->getJson("{$this->prefix}/thawani-store-configs/{$config->id}");
        $response->assertOk()->assertJsonPath('data.id', (string) $config->id);
    }

    public function test_show_thawani_store_config_not_found(): void
    {
        $this->getJson("{$this->prefix}/thawani-store-configs/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // DAILY SALES SUMMARY
    // ────────────────────────────────────────────────────────────
    public function test_list_daily_sales_summary(): void
    {
        $store = $this->createStore();
        DailySalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'date' => '2025-06-01', 'total_transactions' => 50, 'total_revenue' => 5000.00, 'net_revenue' => 4500.00]);

        $response = $this->getJson("{$this->prefix}/daily-sales-summary");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_daily_sales_summary_filter_by_date_range(): void
    {
        $store = $this->createStore();
        DailySalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'date' => '2025-06-01', 'total_transactions' => 50, 'total_revenue' => 5000.00]);
        DailySalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'date' => '2025-07-15', 'total_transactions' => 60, 'total_revenue' => 6000.00]);

        $response = $this->getJson("{$this->prefix}/daily-sales-summary?date_from=2025-07-01&date_to=2025-07-31");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    // ────────────────────────────────────────────────────────────
    // PRODUCT SALES SUMMARY
    // ────────────────────────────────────────────────────────────
    public function test_list_product_sales_summary(): void
    {
        $store = $this->createStore();
        ProductSalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'product_id' => Str::uuid(), 'date' => '2025-06-01', 'quantity_sold' => 100, 'revenue' => 2500.00]);

        $response = $this->getJson("{$this->prefix}/product-sales-summary");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_product_sales_summary_filter_by_product(): void
    {
        $store = $this->createStore();
        $productId = Str::uuid()->toString();
        ProductSalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'product_id' => $productId, 'date' => '2025-06-01', 'quantity_sold' => 100, 'revenue' => 2500.00]);
        ProductSalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'product_id' => Str::uuid(), 'date' => '2025-06-01', 'quantity_sold' => 50, 'revenue' => 1000.00]);

        $response = $this->getJson("{$this->prefix}/product-sales-summary?product_id={$productId}");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }
}
