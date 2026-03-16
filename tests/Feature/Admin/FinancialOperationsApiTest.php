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
    private function createProduct(string $storeId): Product
    {
        // products table uses organization_id, not store_id
        $org = Organization::first() ?? Organization::forceCreate([
            'id'   => Str::uuid(),
            'name' => 'Product Org',
        ]);

        return Product::forceCreate([
            'id'              => Str::uuid(),
            'organization_id' => $org->id,
            'name'            => 'Test Product ' . Str::random(4),
            'sell_price'      => 25.00,
        ]);
    }

    public function test_list_product_sales_summary(): void
    {
        $store = $this->createStore();
        $product = $this->createProduct($store->id);
        ProductSalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'product_id' => $product->id, 'date' => '2025-06-01', 'quantity_sold' => 100, 'revenue' => 2500.00]);

        $response = $this->getJson("{$this->prefix}/product-sales-summary");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_list_product_sales_summary_filter_by_product(): void
    {
        $store = $this->createStore();
        $product1 = $this->createProduct($store->id);
        $product2 = $this->createProduct($store->id);
        ProductSalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'product_id' => $product1->id, 'date' => '2025-06-01', 'quantity_sold' => 100, 'revenue' => 2500.00]);
        ProductSalesSummary::forceCreate(['id' => Str::uuid(), 'store_id' => $store->id, 'product_id' => $product2->id, 'date' => '2025-06-01', 'quantity_sold' => 50, 'revenue' => 1000.00]);

        $response = $this->getJson("{$this->prefix}/product-sales-summary?product_id={$product1->id}");
        $response->assertOk()->assertJsonPath('data.total', 1);
    }

    // ────────────────────────────────────────────────────────────
    // EXPENSE MUTATIONS
    // ────────────────────────────────────────────────────────────
    public function test_create_expense(): void
    {
        $store = $this->createStore();

        $response = $this->postJson("{$this->prefix}/expenses", [
            'store_id'     => $store->id,
            'amount'       => 99.50,
            'category'     => 'supplies',
            'description'  => 'Office supplies purchase',
            'expense_date' => '2025-06-15',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount', '99.50');
        $response->assertJsonPath('data.category', 'supplies');
        $this->assertDatabaseHas('expenses', ['store_id' => $store->id, 'category' => 'supplies']);
    }

    public function test_create_expense_validation(): void
    {
        $response = $this->postJson("{$this->prefix}/expenses", []);
        $response->assertUnprocessable();
    }

    public function test_update_expense(): void
    {
        $store = $this->createStore();
        $expense = Expense::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id, 'amount' => 50.00,
            'category' => 'supplies', 'recorded_by' => Str::uuid(),
        ]);

        $response = $this->putJson("{$this->prefix}/expenses/{$expense->id}", [
            'amount'   => 75.00,
            'category' => 'maintenance',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.category', 'maintenance');
    }

    public function test_update_expense_not_found(): void
    {
        $this->putJson("{$this->prefix}/expenses/" . Str::uuid(), ['amount' => 10])
            ->assertNotFound();
    }

    public function test_delete_expense(): void
    {
        $store = $this->createStore();
        $expense = Expense::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id, 'amount' => 50.00,
            'category' => 'supplies', 'recorded_by' => Str::uuid(),
        ]);

        $this->deleteJson("{$this->prefix}/expenses/{$expense->id}")->assertOk();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_delete_expense_not_found(): void
    {
        $this->deleteJson("{$this->prefix}/expenses/" . Str::uuid())->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // GIFT CARD MUTATIONS
    // ────────────────────────────────────────────────────────────
    public function test_issue_gift_card(): void
    {
        $response = $this->postJson("{$this->prefix}/gift-cards", [
            'organization_id' => Str::uuid(),
            'code'            => 'GC-NEW-001',
            'initial_amount'  => 150.00,
            'recipient_name'  => 'John Doe',
            'expires_at'      => '2026-12-31',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'GC-NEW-001');
        $response->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('gift_cards', ['code' => 'GC-NEW-001']);
    }

    public function test_issue_gift_card_validation(): void
    {
        $this->postJson("{$this->prefix}/gift-cards", [])->assertUnprocessable();
    }

    public function test_issue_gift_card_duplicate_code(): void
    {
        GiftCard::forceCreate([
            'id' => Str::uuid(), 'organization_id' => Str::uuid(),
            'code' => 'GC-DUP', 'initial_amount' => 100, 'balance' => 100,
        ]);

        $this->postJson("{$this->prefix}/gift-cards", [
            'organization_id' => Str::uuid(),
            'code'            => 'GC-DUP',
            'initial_amount'  => 50,
        ])->assertUnprocessable();
    }

    public function test_update_gift_card(): void
    {
        $card = GiftCard::forceCreate([
            'id' => Str::uuid(), 'organization_id' => Str::uuid(),
            'code' => 'GC-UPD', 'initial_amount' => 100, 'balance' => 100, 'status' => 'active',
        ]);

        $response = $this->putJson("{$this->prefix}/gift-cards/{$card->id}", [
            'status'         => 'deactivated',
            'recipient_name' => 'Jane Doe',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'deactivated');
        $response->assertJsonPath('data.recipient_name', 'Jane Doe');
    }

    public function test_update_gift_card_not_found(): void
    {
        $this->putJson("{$this->prefix}/gift-cards/" . Str::uuid(), ['status' => 'active'])
            ->assertNotFound();
    }

    public function test_void_gift_card(): void
    {
        $card = GiftCard::forceCreate([
            'id' => Str::uuid(), 'organization_id' => Str::uuid(),
            'code' => 'GC-VOID', 'initial_amount' => 200, 'balance' => 150, 'status' => 'active',
        ]);

        $response = $this->postJson("{$this->prefix}/gift-cards/{$card->id}/void");
        $response->assertOk();
        $response->assertJsonPath('data.status', 'deactivated');

        $card->refresh();
        $this->assertEquals(0, (float) $card->balance);
    }

    public function test_void_gift_card_not_found(): void
    {
        $this->postJson("{$this->prefix}/gift-cards/" . Str::uuid() . "/void")
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // GIFT CARD TRANSACTION DETAIL
    // ────────────────────────────────────────────────────────────
    public function test_show_gift_card_transaction(): void
    {
        $store = $this->createStore();
        $card = GiftCard::forceCreate([
            'id' => Str::uuid(), 'organization_id' => Str::uuid(),
            'code' => 'GC-TX', 'initial_amount' => 100, 'balance' => 80,
        ]);

        $tx = GiftCardTransaction::forceCreate([
            'id' => Str::uuid(), 'gift_card_id' => $card->id, 'type' => 'redemption',
            'amount' => 20.00, 'balance_after' => 80.00, 'store_id' => $store->id,
            'performed_by' => Str::uuid(),
        ]);

        $response = $this->getJson("{$this->prefix}/gift-card-transactions/{$tx->id}");
        $response->assertOk();
        $response->assertJsonPath('data.type', 'redemption');
    }

    public function test_show_gift_card_transaction_not_found(): void
    {
        $this->getJson("{$this->prefix}/gift-card-transactions/" . Str::uuid())
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // REFUND PROCESSING
    // ────────────────────────────────────────────────────────────
    public function test_process_refund_approve(): void
    {
        $refund = Refund::forceCreate([
            'id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash',
            'amount' => 50.00, 'status' => 'pending', 'processed_by' => Str::uuid(),
        ]);

        $response = $this->postJson("{$this->prefix}/refunds/{$refund->id}/process", [
            'status' => 'completed',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
    }

    public function test_process_refund_reject(): void
    {
        $refund = Refund::forceCreate([
            'id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash',
            'amount' => 30.00, 'status' => 'pending', 'processed_by' => Str::uuid(),
        ]);

        $response = $this->postJson("{$this->prefix}/refunds/{$refund->id}/process", [
            'status'           => 'failed',
            'reference_number' => 'REJ-001',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'failed');
    }

    public function test_process_refund_validation(): void
    {
        $refund = Refund::forceCreate([
            'id' => Str::uuid(), 'return_id' => Str::uuid(), 'method' => 'cash',
            'amount' => 30.00, 'status' => 'pending', 'processed_by' => Str::uuid(),
        ]);

        $this->postJson("{$this->prefix}/refunds/{$refund->id}/process", [
            'status' => 'invalid_status',
        ])->assertUnprocessable();
    }

    public function test_process_refund_not_found(): void
    {
        $this->postJson("{$this->prefix}/refunds/" . Str::uuid() . "/process", [
            'status' => 'completed',
        ])->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // CASH SESSION FORCE-CLOSE
    // ────────────────────────────────────────────────────────────
    public function test_force_close_cash_session(): void
    {
        $store = $this->createStore();
        $session = CashSession::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'opened_by' => Str::uuid(), 'status' => 'open',
        ]);

        $response = $this->postJson("{$this->prefix}/cash-sessions/{$session->id}/force-close", [
            'notes' => 'Emergency close',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'closed');
    }

    public function test_force_close_cash_session_already_closed(): void
    {
        $store = $this->createStore();
        $session = CashSession::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'opened_by' => Str::uuid(), 'status' => 'closed',
        ]);

        $this->postJson("{$this->prefix}/cash-sessions/{$session->id}/force-close")
            ->assertStatus(422);
    }

    public function test_force_close_cash_session_not_found(): void
    {
        $this->postJson("{$this->prefix}/cash-sessions/" . Str::uuid() . "/force-close")
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // ACCOUNTING CONFIG MUTATIONS
    // ────────────────────────────────────────────────────────────
    public function test_create_accounting_config(): void
    {
        $store = $this->createStore();

        $response = $this->postJson("{$this->prefix}/accounting-configs", [
            'store_id'     => $store->id,
            'provider'     => 'quickbooks',
            'company_name' => 'Test Company',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.provider', 'quickbooks');
        $this->assertDatabaseHas('store_accounting_configs', ['store_id' => $store->id]);
    }

    public function test_create_accounting_config_validation(): void
    {
        $this->postJson("{$this->prefix}/accounting-configs", [])
            ->assertUnprocessable();
    }

    public function test_update_accounting_config(): void
    {
        $store = $this->createStore();
        $config = StoreAccountingConfig::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks',
        ]);

        $response = $this->putJson("{$this->prefix}/accounting-configs/{$config->id}", [
            'company_name' => 'Updated Company',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.company_name', 'Updated Company');
    }

    public function test_update_accounting_config_not_found(): void
    {
        $this->putJson("{$this->prefix}/accounting-configs/" . Str::uuid(), ['provider' => 'xero'])
            ->assertNotFound();
    }

    public function test_delete_accounting_config(): void
    {
        $store = $this->createStore();
        $config = StoreAccountingConfig::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks',
        ]);

        $this->deleteJson("{$this->prefix}/accounting-configs/{$config->id}")->assertOk();
        $this->assertDatabaseMissing('store_accounting_configs', ['id' => $config->id]);
    }

    public function test_delete_accounting_config_not_found(): void
    {
        $this->deleteJson("{$this->prefix}/accounting-configs/" . Str::uuid())
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // ACCOUNT MAPPING MUTATIONS
    // ────────────────────────────────────────────────────────────
    public function test_create_account_mapping(): void
    {
        $store = $this->createStore();

        $response = $this->postJson("{$this->prefix}/account-mappings", [
            'store_id'              => $store->id,
            'pos_account_key'       => 'sales_revenue',
            'provider_account_id'   => 'QB-4000',
            'provider_account_name' => 'Sales Revenue',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.pos_account_key', 'sales_revenue');
    }

    public function test_create_account_mapping_validation(): void
    {
        $this->postJson("{$this->prefix}/account-mappings", [])
            ->assertUnprocessable();
    }

    public function test_update_account_mapping(): void
    {
        $store = $this->createStore();
        $mapping = AccountMapping::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'pos_account_key' => 'sales', 'provider_account_id' => 'QB-1',
            'provider_account_name' => 'Old Name',
        ]);

        $response = $this->putJson("{$this->prefix}/account-mappings/{$mapping->id}", [
            'provider_account_name' => 'New Name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.provider_account_name', 'New Name');
    }

    public function test_update_account_mapping_not_found(): void
    {
        $this->putJson("{$this->prefix}/account-mappings/" . Str::uuid(), ['pos_account_key' => 'x'])
            ->assertNotFound();
    }

    public function test_delete_account_mapping(): void
    {
        $store = $this->createStore();
        $mapping = AccountMapping::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'pos_account_key' => 'sales', 'provider_account_id' => 'QB-1',
            'provider_account_name' => 'Sales',
        ]);

        $this->deleteJson("{$this->prefix}/account-mappings/{$mapping->id}")->assertOk();
        $this->assertDatabaseMissing('account_mappings', ['id' => $mapping->id]);
    }

    public function test_delete_account_mapping_not_found(): void
    {
        $this->deleteJson("{$this->prefix}/account-mappings/" . Str::uuid())
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // ACCOUNTING EXPORT TRIGGER & RETRY
    // ────────────────────────────────────────────────────────────
    public function test_trigger_accounting_export(): void
    {
        $store = $this->createStore();

        $response = $this->postJson("{$this->prefix}/accounting-exports", [
            'store_id'   => $store->id,
            'provider'   => 'quickbooks',
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.triggered_by', 'manual');
    }

    public function test_trigger_accounting_export_validation(): void
    {
        $this->postJson("{$this->prefix}/accounting-exports", [])
            ->assertUnprocessable();
    }

    public function test_retry_failed_accounting_export(): void
    {
        $store = $this->createStore();
        $export = AccountingExport::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks',
            'start_date' => '2025-06-01', 'end_date' => '2025-06-30',
            'status' => 'failed', 'error_message' => 'Timeout',
        ]);

        $response = $this->postJson("{$this->prefix}/accounting-exports/{$export->id}/retry");
        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');

        $export->refresh();
        $this->assertNull($export->error_message);
    }

    public function test_retry_non_failed_export_rejected(): void
    {
        $store = $this->createStore();
        $export = AccountingExport::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id, 'provider' => 'quickbooks',
            'start_date' => '2025-06-01', 'end_date' => '2025-06-30',
            'status' => 'success',
        ]);

        $this->postJson("{$this->prefix}/accounting-exports/{$export->id}/retry")
            ->assertStatus(422);
    }

    public function test_retry_export_not_found(): void
    {
        $this->postJson("{$this->prefix}/accounting-exports/" . Str::uuid() . "/retry")
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // AUTO EXPORT CONFIG CREATE & DELETE
    // ────────────────────────────────────────────────────────────
    public function test_create_auto_export_config(): void
    {
        $store = $this->createStore();

        $response = $this->postJson("{$this->prefix}/auto-export-configs", [
            'store_id'  => $store->id,
            'enabled'   => true,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time'      => '22:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.frequency', 'weekly');
        $this->assertDatabaseHas('auto_export_configs', ['store_id' => $store->id]);
    }

    public function test_create_auto_export_config_validation(): void
    {
        $this->postJson("{$this->prefix}/auto-export-configs", [])
            ->assertUnprocessable();
    }

    public function test_delete_auto_export_config(): void
    {
        $store = $this->createStore();
        $config = AutoExportConfig::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
        ]);

        $this->deleteJson("{$this->prefix}/auto-export-configs/{$config->id}")->assertOk();
        $this->assertDatabaseMissing('auto_export_configs', ['id' => $config->id]);
    }

    public function test_delete_auto_export_config_not_found(): void
    {
        $this->deleteJson("{$this->prefix}/auto-export-configs/" . Str::uuid())
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // THAWANI SETTLEMENT RECONCILIATION
    // ────────────────────────────────────────────────────────────
    public function test_reconcile_thawani_settlement(): void
    {
        $store = $this->createStore();
        $settlement = ThawaniSettlement::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'settlement_date' => '2025-06-01', 'gross_amount' => 1000.00,
            'commission_amount' => 25.00, 'net_amount' => 975.00, 'order_count' => 50,
        ]);

        $response = $this->postJson("{$this->prefix}/thawani-settlements/{$settlement->id}/reconcile", [
            'reconciled' => true,
        ]);

        $response->assertOk();
        $settlement->refresh();
        $this->assertTrue((bool) $settlement->reconciled);
        $this->assertNotNull($settlement->reconciled_at);
    }

    public function test_reconcile_thawani_settlement_unreconcile(): void
    {
        $store = $this->createStore();
        $settlement = ThawaniSettlement::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'settlement_date' => '2025-06-01', 'gross_amount' => 500.00,
            'commission_amount' => 12.00, 'net_amount' => 488.00, 'order_count' => 20,
            'reconciled' => true, 'reconciled_at' => now(),
        ]);

        $response = $this->postJson("{$this->prefix}/thawani-settlements/{$settlement->id}/reconcile", [
            'reconciled' => false,
        ]);

        $response->assertOk();
        $settlement->refresh();
        $this->assertFalse((bool) $settlement->reconciled);
        $this->assertNull($settlement->reconciled_at);
    }

    public function test_reconcile_thawani_settlement_not_found(): void
    {
        $this->postJson("{$this->prefix}/thawani-settlements/" . Str::uuid() . "/reconcile", [
            'reconciled' => true,
        ])->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // DAILY SALES SUMMARY DETAIL
    // ────────────────────────────────────────────────────────────
    public function test_show_daily_sales_summary(): void
    {
        $store = $this->createStore();
        $summary = DailySalesSummary::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id, 'date' => '2025-06-15',
            'total_transactions' => 120, 'total_revenue' => 5000.00, 'net_revenue' => 4500.00,
        ]);

        $response = $this->getJson("{$this->prefix}/daily-sales-summary/{$summary->id}");
        $response->assertOk();
        $response->assertJsonPath('data.total_transactions', 120);
    }

    public function test_show_daily_sales_summary_not_found(): void
    {
        $this->getJson("{$this->prefix}/daily-sales-summary/" . Str::uuid())
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // PRODUCT SALES SUMMARY DETAIL
    // ────────────────────────────────────────────────────────────
    public function test_show_product_sales_summary(): void
    {
        $store = $this->createStore();
        $product = $this->createProduct($store->id);
        $summary = ProductSalesSummary::forceCreate([
            'id' => Str::uuid(), 'store_id' => $store->id,
            'product_id' => $product->id, 'date' => '2025-06-15',
            'quantity_sold' => 50, 'revenue' => 1250.00,
        ]);

        $response = $this->getJson("{$this->prefix}/product-sales-summary/{$summary->id}");
        $response->assertOk();
        $this->assertNotNull($response->json('data.quantity_sold'));
    }

    public function test_show_product_sales_summary_not_found(): void
    {
        $this->getJson("{$this->prefix}/product-sales-summary/" . Str::uuid())
            ->assertNotFound();
    }
}
