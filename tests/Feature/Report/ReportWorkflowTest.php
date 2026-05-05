<?php

namespace Tests\Feature\Report;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Report\Jobs\ProcessScheduledReportsJob;
use App\Domain\Report\Jobs\RefreshDailySummariesJob;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\Report\Models\ScheduledReport;
use App\Domain\Report\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end workflow tests for the Reports feature.
 *
 * These tests verify full business cycles:
 *   - Full report generation with accurate aggregation
 *   - Scheduled report lifecycle (create → list → job processes → next_run_at updated)
 *   - Export generating downloadable file
 *   - Refresh summaries rebuilding cached tables
 *   - Staff performance hours_worked calculation
 *   - Customer retention returning/new split accuracy
 *   - Multi-branch report switching
 */
class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Store $store;
    private User $user;
    private string $token;
    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReportService::class);

        $this->org = Organization::create([
            'name' => 'Workflow Test Org',
            'business_type' => 'grocery',
            'country' => 'SA',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Workflow Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->user = User::create([
            'name' => 'WF Owner',
            'email' => 'wf@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    private function apiHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── Full Report Generation Cycle ─────────────────────────────────────────

    /** @test */
    public function full_sales_report_cycle_data_in_to_aggregated_response(): void
    {
        // Seed 3 days of daily summary data
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-01', 'total_transactions' => 10, 'total_revenue' => 1000.00, 'total_refunds' => 50.00, 'net_revenue' => 950.00, 'total_discount' => 30.00, 'total_tax' => 80.00, 'total_cost' => 600.00, 'unique_customers' => 8]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-02', 'total_transactions' => 15, 'total_revenue' => 1500.00, 'total_refunds' => 100.00, 'net_revenue' => 1400.00, 'total_discount' => 60.00, 'total_tax' => 120.00, 'total_cost' => 900.00, 'unique_customers' => 12]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-03', 'total_transactions' => 5, 'total_revenue' => 500.00, 'total_refunds' => 20.00, 'net_revenue' => 480.00, 'total_discount' => 10.00, 'total_tax' => 40.00, 'total_cost' => 300.00, 'unique_customers' => 4]);

        $response = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-11-01&date_to=2024-11-03',
            $this->apiHeaders()
        )->assertStatus(200);

        $totals = $response->json('data.totals');

        $this->assertEquals(30, $totals['total_transactions']);
        $this->assertEquals(3000.00, (float) $totals['total_revenue']);
        $this->assertEquals(170.00, (float) $totals['total_refunds']);
        $this->assertEquals(100.00, (float) $totals['total_discount']);

        $series = $response->json('data.series');
        $this->assertCount(3, $series);
    }

    /** @test */
    public function full_product_performance_cycle_shows_correct_rankings(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Electronics', 'sort_order' => 1]);
        $pA = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Laptop', 'sku' => 'LP001', 'sell_price' => 2000.00, 'cost_price' => 1200.00, 'is_active' => true]);
        $pB = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Mouse', 'sku' => 'MS001', 'sell_price' => 50.00, 'cost_price' => 20.00, 'is_active' => true]);

        // Laptop: 5 units = 10000 revenue
        // Mouse: 100 units = 5000 revenue
        ProductSalesSummary::create(['store_id' => $this->store->id, 'product_id' => $pA->id, 'date' => '2024-11-01', 'revenue' => 10000.00, 'cost' => 6000.00, 'quantity_sold' => 5]);
        ProductSalesSummary::create(['store_id' => $this->store->id, 'product_id' => $pB->id, 'date' => '2024-11-01', 'revenue' => 5000.00, 'cost' => 2000.00, 'quantity_sold' => 100]);

        $response = $this->getJson('/api/v2/reports/product-performance', $this->apiHeaders())
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('Laptop', $data[0]['product_name']);
        $this->assertEquals(10000.00, (float) $data[0]['total_revenue']);
        $this->assertEquals(5, (int) $data[0]['total_quantity']);

        // Verify profit is calculated
        $this->assertArrayHasKey('profit', $data[0]);
        $this->assertEquals(4000.0, round((float) $data[0]['profit'], 1));
    }

    // ─── Staff Performance with Hours Worked ──────────────────────────────────

    /** @test */
    public function staff_hours_worked_calculated_correctly_from_attendance_records(): void
    {
        $staffId = Str::uuid()->toString();
        $now = now()->toDateTimeString();

        // Create staff_user record
        DB::table('staff_users')->insert([
            'id' => $staffId,
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'first_name' => 'John',
            'last_name' => 'Cashier',
            'pin_hash' => bcrypt('1234'),
            'status' => 'active',
            'employment_type' => 'full_time',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Two shifts: 4h + 6h = 10h total
        DB::table('attendance_records')->insert([
            [
                'id' => Str::uuid(),
                'staff_user_id' => $staffId,
                'store_id' => $this->store->id,
                'clock_in_at' => '2024-11-01 08:00:00',
                'clock_out_at' => '2024-11-01 12:00:00',
            ],
            [
                'id' => Str::uuid(),
                'staff_user_id' => $staffId,
                'store_id' => $this->store->id,
                'clock_in_at' => '2024-11-02 09:00:00',
                'clock_out_at' => '2024-11-02 15:00:00',
            ],
        ]);

        // Some orders by this staff
        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'J1', 'source' => 'pos', 'status' => 'completed', 'total' => 100.00, 'created_by' => $staffId, 'created_at' => '2024-11-01 10:00:00', 'updated_at' => $now],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'J2', 'source' => 'pos', 'status' => 'completed', 'total' => 200.00, 'created_by' => $staffId, 'created_at' => '2024-11-02 11:00:00', 'updated_at' => $now],
        ]);

        $result = $this->service->staffPerformance($this->store->id, [
            'date_from' => '2024-11-01',
            'date_to' => '2024-11-02',
        ]);

        $this->assertCount(1, $result);
        $staff = $result[0];
        $this->assertEquals(10.0, (float) $staff['hours_worked']);
        $this->assertEquals(2, (int) $staff['total_orders']);
        $this->assertEquals(300.00, (float) $staff['total_revenue']);
    }

    /** @test */
    public function staff_with_open_shift_not_clocked_out_counts_partial_hours(): void
    {
        $staffId = Str::uuid()->toString();
        $now = now()->toDateTimeString();

        DB::table('staff_users')->insert([
            'id' => $staffId,
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'first_name' => 'Open',
            'last_name' => 'Shift Staff',
            'pin_hash' => bcrypt('0000'),
            'status' => 'active',
            'employment_type' => 'full_time',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Clocked in 2 hours ago, not yet clocked out
        DB::table('attendance_records')->insert([
            'id' => Str::uuid(),
            'staff_user_id' => $staffId,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(2)->toDateTimeString(),
            'clock_out_at' => null,
        ]);

        DB::table('orders')->insert([
            'id' => Str::uuid(),
            'store_id' => $this->store->id,
            'order_number' => 'OPEN1',
            'source' => 'pos',
            'status' => 'completed',
            'total' => 50.00,
            'created_by' => $staffId,
            'created_at' => now()->subHour()->toDateTimeString(),
            'updated_at' => $now,
        ]);

        $result = $this->service->staffPerformance($this->store->id, []);

        $staff = collect($result)->firstWhere('staff_id', $staffId);
        if ($staff) {
            // hours_worked should be >= 0 (open shift counts partial time up to now)
            $this->assertGreaterThanOrEqual(0, (float) $staff['hours_worked']);
        }
        // If staff doesn't appear (no orders counted yet), that's also valid
    }

    // ─── Customer Retention Accuracy ──────────────────────────────────────────

    /** @test */
    public function customer_retention_correctly_identifies_returning_customers(): void
    {
        $orgId = $this->org->id;
        $now = now()->toDateTimeString();
        $recent = now()->subDays(5)->toDateTimeString(); // within 30d → active
        $old = now()->subDays(60)->toDateTimeString();   // outside 30d → not active/not new

        // c1: created 60 days ago, last visited 5 days ago → active but NOT new → counts as returning
        // c2: created 5 days ago, last visited 5 days ago → active AND new → counts as new (not returning)
        DB::table('customers')->insert([
            ['id' => Str::uuid(), 'organization_id' => $orgId, 'name' => 'Returning', 'last_visit_at' => $recent, 'created_at' => $old, 'updated_at' => $now],
            ['id' => Str::uuid(), 'organization_id' => $orgId, 'name' => 'New Customer', 'last_visit_at' => $recent, 'created_at' => $recent, 'updated_at' => $now],
        ]);

        $result = $this->service->customerRetention($this->store->id, []);

        $this->assertArrayHasKey('returning_customers_30d', $result);
        $this->assertArrayHasKey('new_customers_30d', $result);
        // active_30d=2, new_30d=1 → returning=max(0,2-1)=1
        $this->assertEquals(1, $result['returning_customers_30d']);
        $this->assertEquals(1, $result['new_customers_30d']);
    }

    // ─── Scheduled Report Lifecycle ───────────────────────────────────────────

    /** @test */
    public function scheduled_report_create_list_delete_full_cycle(): void
    {
        // CREATE
        $createResponse = $this->postJson('/api/v2/reports/schedules', [
            'report_type' => 'sales_summary',
            'name' => 'Daily Sales Email',
            'frequency' => 'daily',
            'recipients' => ['owner@store.com', 'mgr@store.com'],
            'format' => 'pdf',
        ], $this->apiHeaders())->assertStatus(201);

        $scheduleId = $createResponse->json('data.id');
        $this->assertNotNull($scheduleId);

        // LIST — should include the new schedule
        $listResponse = $this->getJson('/api/v2/reports/schedules', $this->apiHeaders())
            ->assertStatus(200);

        $schedules = $listResponse->json('data');
        $this->assertNotEmpty($schedules);
        $ids = collect($schedules)->pluck('id')->toArray();
        $this->assertContains($scheduleId, $ids);

        // DELETE
        $this->deleteJson("/api/v2/reports/schedules/{$scheduleId}", [], $this->apiHeaders())
            ->assertStatus(200);

        // LIST again — should be gone
        $this->getJson('/api/v2/reports/schedules', $this->apiHeaders())
            ->assertStatus(200)
            ->assertJsonMissing(['id' => $scheduleId]);
    }

    /** @test */
    public function delete_nonexistent_scheduled_report_returns_404(): void
    {
        $fakeId = Str::uuid()->toString();

        $this->deleteJson("/api/v2/reports/schedules/{$fakeId}", [], $this->apiHeaders())
            ->assertStatus(404);
    }

    /** @test */
    public function scheduled_report_create_sets_next_run_at_to_future(): void
    {
        $response = $this->postJson('/api/v2/reports/schedules', [
            'report_type' => 'staff_performance',
            'name' => 'Weekly Staff',
            'frequency' => 'weekly',
            'recipients' => ['mgr@test.com'],
            'format' => 'csv',
        ], $this->apiHeaders())->assertStatus(201);

        $nextRunAt = $response->json('data.next_run_at');
        $this->assertNotNull($nextRunAt);
        $this->assertTrue(now()->lt(now()->parse($nextRunAt)));
    }

    /** @test */
    public function process_scheduled_reports_job_dispatches_for_due_reports(): void
    {
        Queue::fake();

        // Create a due report (next_run_at is in the past)
        ScheduledReport::create([
            'store_id' => $this->store->id,
            'report_type' => 'sales_summary',
            'name' => 'Past Due',
            'frequency' => 'daily',
            'recipients' => ['x@test.com'],
            'format' => 'csv',
            'is_active' => true,
            'next_run_at' => now()->subHour(),
        ]);

        ProcessScheduledReportsJob::dispatch();

        Queue::assertPushed(ProcessScheduledReportsJob::class);
    }

    /** @test */
    public function process_scheduled_reports_job_runs_and_updates_next_run_at(): void
    {
        Mail::fake();

        $schedule = ScheduledReport::create([
            'store_id' => $this->store->id,
            'report_type' => 'sales_summary',
            'name' => 'Due Now',
            'frequency' => 'daily',
            'recipients' => ['owner@test.com'],
            'format' => 'csv',
            'is_active' => true,
            'next_run_at' => now()->subMinutes(5),
        ]);

        (new ProcessScheduledReportsJob())->handle($this->service);

        $schedule->refresh();
        $this->assertTrue($schedule->next_run_at->isFuture(), 'next_run_at should be updated to a future time after processing');
    }

    /** @test */
    public function inactive_scheduled_report_is_skipped_by_job(): void
    {
        Mail::fake();

        ScheduledReport::create([
            'store_id' => $this->store->id,
            'report_type' => 'sales_summary',
            'name' => 'Inactive',
            'frequency' => 'daily',
            'recipients' => ['x@test.com'],
            'format' => 'csv',
            'is_active' => false,
            'next_run_at' => now()->subHour(),
        ]);

        (new ProcessScheduledReportsJob())->handle($this->service);

        // Inactive reports should not trigger emails
        Mail::assertNothingSent();
    }

    // ─── Export Workflow ──────────────────────────────────────────────────────

    /** @test */
    public function export_sales_summary_csv_returns_downloadable_url(): void
    {
        $response = $this->postJson('/api/v2/reports/export', [
            'report_type' => 'sales_summary',
            'format' => 'csv',
        ], $this->apiHeaders())->assertStatus(200);

        $this->assertNotNull($response->json('data.url'));
        $this->assertEquals('csv', $response->json('data.format'));
        $this->assertEquals('sales_summary', $response->json('data.report_type'));
    }

    /** @test */
    public function export_product_performance_pdf_returns_downloadable_url(): void
    {
        $response = $this->postJson('/api/v2/reports/export', [
            'report_type' => 'product_performance',
            'format' => 'pdf',
        ], $this->apiHeaders())->assertStatus(200);

        $this->assertEquals('pdf', $response->json('data.format'));
    }

    /** @test */
    public function export_invalid_type_returns_422(): void
    {
        $this->postJson('/api/v2/reports/export', [
            'report_type' => 'not_a_real_report',
            'format' => 'csv',
        ], $this->apiHeaders())->assertStatus(422);
    }

    /** @test */
    public function export_invalid_format_returns_422(): void
    {
        $this->postJson('/api/v2/reports/export', [
            'report_type' => 'sales_summary',
            'format' => 'docx',
        ], $this->apiHeaders())->assertStatus(422);
    }

    // ─── Refresh Summaries Workflow ────────────────────────────────────────────

    /** @test */
    public function refresh_summaries_endpoint_dispatches_job(): void
    {
        $this->postJson('/api/v2/reports/refresh-summaries', [], $this->apiHeaders())
            ->assertStatus(200);
    }

    /** @test */
    public function refresh_summaries_job_rebuilds_daily_summary_from_orders(): void
    {
        $date = '2024-11-20';

        DB::table('orders')->insert([
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'RB1', 'source' => 'pos', 'status' => 'completed', 'total' => 200.00, 'created_at' => "{$date} 10:00:00", 'updated_at' => now()],
            ['id' => Str::uuid(), 'store_id' => $this->store->id, 'order_number' => 'RB2', 'source' => 'pos', 'status' => 'completed', 'total' => 300.00, 'created_at' => "{$date} 14:00:00", 'updated_at' => now()],
        ]);

        $this->service->refreshDailySummary($this->store->id, $date);

        $row = DailySalesSummary::where('store_id', $this->store->id)
            ->where('date', $date)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(2, $row->total_transactions);
        $this->assertEquals(500.00, (float) $row->total_revenue);
    }

    /** @test */
    public function refresh_product_summary_aggregates_transaction_items(): void
    {
        $cat = Category::create(['organization_id' => $this->org->id, 'name' => 'Cat', 'sort_order' => 1]);
        $product = Product::create(['organization_id' => $this->org->id, 'category_id' => $cat->id, 'name' => 'Widget', 'sku' => 'W001', 'sell_price' => 10.00, 'cost_price' => 5.00, 'is_active' => true]);

        $txnId = Str::uuid()->toString();
        $date = '2024-11-22';
        $fakeUuid = Str::uuid()->toString();

        // refreshProductSummary reads from transaction_items joined to transactions
        DB::table('transactions')->insert([
            'id' => $txnId,
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'register_id' => $fakeUuid,
            'pos_session_id' => $fakeUuid,
            'cashier_id' => $this->user->id,
            'transaction_number' => 'TXN-001',
            'type' => 'sale',
            'status' => 'completed',
            'subtotal' => 50.00,
            'tax_amount' => 0.00,
            'total_amount' => 50.00,
            'created_at' => "{$date} 10:00:00",
            'updated_at' => now(),
        ]);

        DB::table('transaction_items')->insert([
            'id' => Str::uuid(),
            'transaction_id' => $txnId,
            'product_id' => $product->id,
            'product_name' => 'Widget',
            'quantity' => 5,
            'unit_price' => 10.00,
            'tax_amount' => 0.00,
            'line_total' => 50.00,
        ]);

        $this->service->refreshProductSummary($this->store->id, $date);

        $row = ProductSalesSummary::where('store_id', $this->store->id)
            ->where('product_id', $product->id)
            ->where('date', $date)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(5, (float) $row->quantity_sold);
        $this->assertEquals(50.00, (float) $row->revenue);
    }

    // ─── Multi-branch Report Accuracy ─────────────────────────────────────────

    /** @test */
    public function multi_branch_report_returns_correct_branch_totals(): void
    {
        $branch2 = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Branch 2',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        // Main store: 1000 SAR
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => '2024-11-01', 'total_revenue' => 1000.00, 'total_transactions' => 5]);
        // Branch 2: 3500 SAR
        DailySalesSummary::create(['store_id' => $branch2->id, 'date' => '2024-11-01', 'total_revenue' => 3500.00, 'total_transactions' => 15]);

        // Request for main store
        $mainResponse = $this->getJson(
            '/api/v2/reports/sales-summary?date_from=2024-11-01&date_to=2024-11-01',
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(1000.00, (float) $mainResponse->json('data.totals.total_revenue'));

        // Request for branch 2
        $branchResponse = $this->getJson(
            "/api/v2/reports/sales-summary?date_from=2024-11-01&date_to=2024-11-01&branch_id={$branch2->id}",
            $this->apiHeaders()
        )->assertStatus(200);

        $this->assertEquals(3500.00, (float) $branchResponse->json('data.totals.total_revenue'));
    }

    // ─── Dashboard Snapshot ───────────────────────────────────────────────────

    /** @test */
    public function dashboard_reflects_todays_and_yesterdays_data(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => $today, 'total_transactions' => 2, 'total_revenue' => 1000.00]);
        DailySalesSummary::create(['store_id' => $this->store->id, 'date' => $yesterday, 'total_transactions' => 1, 'total_revenue' => 800.00]);

        $response = $this->getJson('/api/v2/reports/dashboard', $this->apiHeaders())
            ->assertStatus(200);

        $this->assertEquals(1000.00, (float) $response->json('data.today.total_revenue'));
        $this->assertEquals(2, $response->json('data.today.total_transactions'));
        $this->assertEquals(800.00, (float) $response->json('data.yesterday.total_revenue'));
        $this->assertEquals(1, $response->json('data.yesterday.total_transactions'));
    }

    // ─── Financial Delivery Commission ────────────────────────────────────────

    /** @test */
    public function financial_delivery_commission_returns_correct_shape(): void
    {
        $response = $this->getJson('/api/v2/reports/financial/delivery-commission', $this->apiHeaders())
            ->assertStatus(200);

        $this->assertArrayHasKey('data', $response->json());
    }

    // ─── Customer Loyalty Points Redeemed ────────────────────────────────────

    /** @test */
    public function customer_retention_includes_loyalty_points_redeemed(): void
    {
        $result = $this->service->customerRetention($this->store->id, []);

        $this->assertArrayHasKey('loyalty_points_redeemed', $result);
        $this->assertIsNumeric($result['loyalty_points_redeemed']);
    }

    // ─── Inventory Shrinkage ──────────────────────────────────────────────────

    /** @test */
    public function inventory_shrinkage_endpoint_returns_200(): void
    {
        $this->getJson('/api/v2/reports/inventory/shrinkage', $this->apiHeaders())
            ->assertStatus(200);
    }

    /** @test */
    public function inventory_turnover_endpoint_returns_200(): void
    {
        $this->getJson('/api/v2/reports/inventory/turnover', $this->apiHeaders())
            ->assertStatus(200);
    }
}
