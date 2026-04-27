<?php

namespace Tests\Feature\StaffManagement;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\BreakRecord;
use App\Domain\StaffManagement\Models\CommissionEarning;
use App\Domain\StaffManagement\Models\CommissionRule;
use App\Domain\StaffManagement\Models\ShiftSchedule;
use App\Domain\StaffManagement\Models\ShiftTemplate;
use App\Domain\StaffManagement\Models\StaffActivityLog;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Store $store;
    private string $token;

    private Organization $otherOrg;
    private Store $otherStore;
    private User $otherUser;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Staff Org',
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
            'name' => 'Admin',
            'email' => 'admin@staff.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;

        // Other org for isolation
        $this->otherOrg = Organization::create([
            'name' => 'Other Org',
            'business_type' => 'grocery',
            'country' => 'OM',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'grocery',
            'currency' => 'SAR',
            'is_active' => true,
            'is_main_branch' => true,
        ]);
        $this->otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@staff.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->otherStore->id,
            'organization_id' => $this->otherOrg->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->otherToken = $this->otherUser->createToken('test', ['*'])->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════
    // Auth
    // ═══════════════════════════════════════════════════════════

    public function test_staff_list_requires_auth(): void
    {
        $this->getJson('/api/v2/staff/members')
            ->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════
    // Staff CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_list_staff_members(): void
    {
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
            'status' => 'active',
        ]);
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Sara',
            'last_name' => 'Hassan',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data.data');
        $this->assertCount(2, $data);
    }

    public function test_list_staff_with_search(): void
    {
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
            'status' => 'active',
        ]);
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Sara',
            'last_name' => 'Hassan',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members?search=Ahmed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_list_staff_cross_store_isolation(): void
    {
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'InStore',
            'last_name' => 'Staff',
        ]);
        StaffUser::create([
            'store_id' => $this->otherStore->id,
            'first_name' => 'Other',
            'last_name' => 'Staff',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members');

        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('InStore', $response->json('data.data.0.first_name'));
    }

    public function test_create_staff_member(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', [
                'store_id' => $this->store->id,
                'first_name' => 'Khalid',
                'last_name' => 'Nasser',
                'email' => 'khalid@test.com',
                'phone' => '+96812345678',
                'employment_type' => 'full_time',
                'salary_type' => 'monthly',
                'status' => 'active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.first_name', 'Khalid')
            ->assertJsonPath('data.last_name', 'Nasser')
            ->assertJsonPath('data.email', 'khalid@test.com');
    }

    public function test_create_staff_validation_error(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', [
                'store_id' => $this->store->id,
                // missing required first_name and last_name
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_staff_with_pin(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/members', [
                'store_id' => $this->store->id,
                'first_name' => 'PinUser',
                'last_name' => 'Test',
                'pin' => '1234',
            ]);

        $response->assertCreated();

        // pin_hash should be hidden
        $this->assertArrayNotHasKey('pin_hash', $response->json('data'));

        // Verify hash was saved
        $staff = StaffUser::find($response->json('data.id'));
        $this->assertNotNull($staff->pin_hash);
    }

    public function test_show_staff_member(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Show',
            'last_name' => 'Test',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}");

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Show');
    }

    public function test_show_staff_cross_store_404(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->otherStore->id,
            'first_name' => 'Other',
            'last_name' => 'Staff',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}");

        $response->assertNotFound();
    }

    public function test_update_staff_member(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Original',
            'last_name' => 'Name',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/staff/members/{$staff->id}", [
                'first_name' => 'Updated',
                'phone' => '+96899999999',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.phone', '+96899999999');
    }

    public function test_delete_staff_member(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Delete',
            'last_name' => 'Me',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/members/{$staff->id}");

        $response->assertOk();
        $this->assertNull(StaffUser::find($staff->id));
    }

    public function test_set_staff_pin(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Pin',
            'last_name' => 'User',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/pin", [
                'pin' => '5678',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'PIN updated');
    }

    public function test_register_nfc_badge(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'NFC',
            'last_name' => 'User',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/nfc", [
                'nfc_badge_uid' => 'ABC123DEF456',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nfc_badge_uid', 'ABC123DEF456');
    }

    // ═══════════════════════════════════════════════════════════
    // Attendance
    // ═══════════════════════════════════════════════════════════

    public function test_clock_in(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Clock',
            'last_name' => 'In',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
                'action' => 'clock_in',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendance_records', [
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
        ]);
    }

    public function test_clock_in_while_already_clocked_in(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Double',
            'last_name' => 'Clock',
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHour(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
                'action' => 'clock_in',
            ]);

        $response->assertStatus(422);
    }

    public function test_clock_out(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Clock',
            'last_name' => 'Out',
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(8),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
                'action' => 'clock_out',
            ]);

        $response->assertOk();

        $record = AttendanceRecord::where('staff_user_id', $staff->id)->first();
        $this->assertNotNull($record->clock_out_at);
    }

    public function test_clock_out_without_clock_in(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'No',
            'last_name' => 'ClockIn',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
                'action' => 'clock_out',
            ]);

        $response->assertStatus(422);
    }

    public function test_list_attendance(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Attend',
            'last_name' => 'Test',
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(8),
            'clock_out_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/attendance');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_list_attendance_filtered_by_staff(): void
    {
        $staff1 = StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'S1', 'last_name' => 'T']);
        $staff2 = StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'S2', 'last_name' => 'T']);

        AttendanceRecord::create(['staff_user_id' => $staff1->id, 'store_id' => $this->store->id, 'clock_in_at' => now()]);
        AttendanceRecord::create(['staff_user_id' => $staff2->id, 'store_id' => $this->store->id, 'clock_in_at' => now()]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/attendance?staff_user_id={$staff1->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ═══════════════════════════════════════════════════════════
    // Shifts
    // ═══════════════════════════════════════════════════════════

    public function test_create_shift(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Shift',
            'last_name' => 'Worker',
        ]);

        $template = ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/shifts', [
                'store_id' => $this->store->id,
                'staff_user_id' => $staff->id,
                'shift_template_id' => $template->id,
                'date' => '2025-03-01',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.staff_user_id', $staff->id);
    }

    public function test_create_shift_conflict(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Conflict',
            'last_name' => 'Shift',
        ]);

        $template = ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'shift_template_id' => $template->id,
            'date' => '2025-03-01',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/shifts', [
                'store_id' => $this->store->id,
                'staff_user_id' => $staff->id,
                'shift_template_id' => $template->id,
                'date' => '2025-03-01',
            ]);

        $response->assertStatus(422);
    }

    public function test_list_shifts(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'List',
            'last_name' => 'Shifts',
        ]);

        $template = ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'shift_template_id' => $template->id,
            'date' => '2025-03-01',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/shifts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_update_shift(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Update',
            'last_name' => 'Shift',
        ]);

        $template = ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $shift = ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'shift_template_id' => $template->id,
            'date' => '2025-03-01',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/staff/shifts/{$shift->id}", [
                'status' => 'completed',
            ]);

        $response->assertOk();
    }

    public function test_delete_shift(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Delete',
            'last_name' => 'Shift',
        ]);

        $template = ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $shift = ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'shift_template_id' => $template->id,
            'date' => '2025-03-01',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/shifts/{$shift->id}");

        $response->assertOk();
        $this->assertNull(ShiftSchedule::find($shift->id));
    }

    // ═══════════════════════════════════════════════════════════
    // Shift Templates
    // ═══════════════════════════════════════════════════════════

    public function test_create_shift_template(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/shift-templates', [
                'store_id' => $this->store->id,
                'name' => 'Morning',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'color' => '#FF5733',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Morning');
    }

    public function test_list_shift_templates(): void
    {
        ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/shift-templates');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ═══════════════════════════════════════════════════════════
    // Commissions
    // ═══════════════════════════════════════════════════════════

    public function test_commission_summary(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Commission',
            'last_name' => 'Test',
        ]);

        $rule = CommissionRule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'type' => 'flat_percentage',
            'percentage' => 5.00,
            'is_active' => true,
        ]);

        $order = \App\Domain\Order\Models\Order::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'order_number' => 'ORD-001',
            'status' => 'completed',
            'total' => 100.00,
        ]);

        $order2 = \App\Domain\Order\Models\Order::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'order_number' => 'ORD-002',
            'status' => 'completed',
            'total' => 200.00,
        ]);

        CommissionEarning::create([
            'staff_user_id' => $staff->id,
            'commission_rule_id' => $rule->id,
            'order_id' => $order->id,
            'order_total' => 100.00,
            'commission_amount' => 5.00,
        ]);
        CommissionEarning::create([
            'staff_user_id' => $staff->id,
            'commission_rule_id' => $rule->id,
            'order_id' => $order2->id,
            'order_total' => 200.00,
            'commission_amount' => 10.00,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/commissions");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(15.0, $data['total_earnings']);
        $this->assertEquals(2, $data['total_orders']);
        $this->assertEquals(7.5, $data['avg_per_order']);
    }

    public function test_commission_cross_store_404(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->otherStore->id,
            'first_name' => 'Other',
            'last_name' => 'Staff',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/commissions");

        $response->assertNotFound();
    }

    public function test_set_commission_config(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Config',
            'last_name' => 'Commission',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/staff/members/{$staff->id}/commission-config", [
                'type' => 'flat_percentage',
                'percentage' => 7.5,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertEquals(7.5, (float) $response->json('data.percentage'));
    }

    // ═══════════════════════════════════════════════════════════
    // Activity Log
    // ═══════════════════════════════════════════════════════════

    public function test_activity_log(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Log',
            'last_name' => 'Test',
        ]);

        StaffActivityLog::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'action' => 'login',
            'entity_type' => 'order',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/activity-log");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_activity_log_cross_store_404(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->otherStore->id,
            'first_name' => 'Other',
            'last_name' => 'Log',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/activity-log");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Staff Stats
    // ═══════════════════════════════════════════════════════════

    public function test_staff_stats(): void
    {
        StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active']);
        StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'C', 'last_name' => 'D', 'status' => 'active']);
        StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'E', 'last_name' => 'F', 'status' => 'inactive']);
        StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'G', 'last_name' => 'H', 'status' => 'on_leave']);

        $response = $this->withToken($this->token)->getJson('/api/v2/staff/members/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_staff', 4)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.inactive', 1)
            ->assertJsonPath('data.on_leave', 1);
    }

    public function test_staff_stats_cross_store_isolation(): void
    {
        StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active']);
        StaffUser::create(['store_id' => $this->otherStore->id, 'first_name' => 'C', 'last_name' => 'D', 'status' => 'active']);

        $response = $this->withToken($this->token)->getJson('/api/v2/staff/members/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_staff', 1)
            ->assertJsonPath('data.active', 1);
    }

    public function test_staff_stats_includes_clocked_in_count(): void
    {
        $staff = StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active']);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHour(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v2/staff/members/stats');

        $response->assertOk()
            ->assertJsonPath('data.currently_clocked_in', 1);
    }

    // ═══════════════════════════════════════════════════════════
    // Branch Assignments
    // ═══════════════════════════════════════════════════════════

    public function test_assign_staff_to_branch(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Branch',
            'last_name' => 'Worker',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/branch-assignments", [
                'branch_id' => $this->store->id,
                'is_primary' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branch_id', $this->store->id)
            ->assertJsonPath('data.is_primary', true);
    }

    public function test_assign_staff_to_branch_duplicate_error(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Dup',
            'last_name' => 'Assign',
        ]);

        \App\Domain\StaffManagement\Models\StaffBranchAssignment::create([
            'staff_user_id' => $staff->id,
            'branch_id' => $this->store->id,
            'is_primary' => false,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/branch-assignments", [
                'branch_id' => $this->store->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_list_branch_assignments(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'List',
            'last_name' => 'Branches',
        ]);

        \App\Domain\StaffManagement\Models\StaffBranchAssignment::create([
            'staff_user_id' => $staff->id,
            'branch_id' => $this->store->id,
            'is_primary' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/branch-assignments");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_unassign_staff_from_branch(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Un',
            'last_name' => 'Assign',
        ]);

        \App\Domain\StaffManagement\Models\StaffBranchAssignment::create([
            'staff_user_id' => $staff->id,
            'branch_id' => $this->store->id,
            'is_primary' => false,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/members/{$staff->id}/branch-assignments", [
                'branch_id' => $this->store->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Branch assignment removed');
    }

    public function test_unassign_nonexistent_branch_error(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'No',
            'last_name' => 'Branch',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/members/{$staff->id}/branch-assignments", [
                'branch_id' => $this->store->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_branch_assignment_cross_store_404(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->otherStore->id,
            'first_name' => 'Other',
            'last_name' => 'Staff',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/branch-assignments");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Attendance Export
    // ═══════════════════════════════════════════════════════════

    public function test_attendance_export(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Export',
            'last_name' => 'Test',
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(8),
            'clock_out_at' => now(),
            'break_minutes' => 30,
            'overtime_minutes' => 15,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/attendance/export');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1);

        $rows = $response->json('data.rows');
        $this->assertCount(1, $rows);
        $this->assertEquals('Export Test', $rows[0]['staff_name']);
        $this->assertEquals(30, $rows[0]['break_minutes']);
        $this->assertEquals(15, $rows[0]['overtime_minutes']);
    }

    public function test_attendance_export_with_date_filter(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Filter',
            'last_name' => 'Export',
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subDays(5),
            'clock_out_at' => now()->subDays(5)->addHours(8),
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subDays(1),
            'clock_out_at' => now(),
        ]);

        $dateFrom = now()->subDays(2)->toDateString();
        $dateTo = now()->toDateString();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/attendance/export?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_attendance_export_has_headers(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/attendance/export');

        $response->assertOk();

        $headers = $response->json('data.headers');
        $this->assertCount(6, $headers);
        $this->assertContains('Staff Name', $headers);
        $this->assertContains('Clock In', $headers);
        $this->assertContains('Clock Out', $headers);
    }

    // ═══════════════════════════════════════════════════════════
    // Break Tracking Edge Cases
    // ═══════════════════════════════════════════════════════════

    public function test_start_break(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Break',
            'last_name' => 'Start',
        ]);

        $attendance = AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(2),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'action' => 'start_break',
                'attendance_record_id' => $attendance->id,
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('break_records', [
            'attendance_record_id' => $attendance->id,
        ]);
    }

    public function test_end_break(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Break',
            'last_name' => 'End',
        ]);

        $attendance = AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(2),
        ]);

        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_start' => now()->subMinutes(30),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'action' => 'end_break',
                'attendance_record_id' => $attendance->id,
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
            ]);

        $response->assertOk();

        $break = BreakRecord::where('attendance_record_id', $attendance->id)->first();
        $this->assertNotNull($break->break_end);
    }

    public function test_double_break_start_error(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Double',
            'last_name' => 'Break',
        ]);

        $attendance = AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(2),
        ]);

        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_start' => now()->subMinutes(15),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'action' => 'start_break',
                'attendance_record_id' => $attendance->id,
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_break_on_ended_shift_error(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Ended',
            'last_name' => 'Shift',
        ]);

        $attendance = AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subHours(8),
            'clock_out_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v2/staff/attendance/clock', [
                'action' => 'start_break',
                'attendance_record_id' => $attendance->id,
                'staff_user_id' => $staff->id,
                'store_id' => $this->store->id,
            ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // Search & Filter Edge Cases
    // ═══════════════════════════════════════════════════════════

    public function test_search_case_insensitive(): void
    {
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'MOHAMMED',
            'last_name' => 'Ali',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members?search=mohammed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_filter_by_status(): void
    {
        StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active']);
        StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'C', 'last_name' => 'D', 'status' => 'inactive']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members?status=active');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_filter_by_employment_type(): void
    {
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Full',
            'last_name' => 'Time',
            'employment_type' => 'full_time',
        ]);
        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Part',
            'last_name' => 'Time',
            'employment_type' => 'part_time',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members?employment_type=part_time');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ═══════════════════════════════════════════════════════════
    // Commission Edge Cases
    // ═══════════════════════════════════════════════════════════

    public function test_commission_replace_existing_rules(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Replace',
            'last_name' => 'Rules',
        ]);

        CommissionRule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'type' => 'flat_percentage',
            'percentage' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v2/staff/members/{$staff->id}/commission-config", [
                'type' => 'flat_percentage',
                'percentage' => 10.00,
                'replace_existing' => true,
            ]);

        $response->assertCreated();

        $rules = CommissionRule::where('staff_user_id', $staff->id)->get();
        $this->assertEquals(2, $rules->count());
        $inactiveRules = $rules->where('is_active', false);
        $this->assertEquals(1, $inactiveRules->count());
    }

    public function test_commission_summary_empty(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Empty',
            'last_name' => 'Commission',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/commissions");

        $response->assertOk()
            ->assertJsonPath('data.total_earnings', 0)
            ->assertJsonPath('data.total_orders', 0)
            ->assertJsonPath('data.avg_per_order', 0);
    }

    // ═══════════════════════════════════════════════════════════
    // Shifts - Date Filtering
    // ═══════════════════════════════════════════════════════════

    public function test_list_shifts_filtered_by_date_range(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Shift',
            'last_name' => 'Filter',
        ]);

        $template = ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'shift_template_id' => $template->id,
            'date' => '2025-01-15',
        ]);

        ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff->id,
            'shift_template_id' => $template->id,
            'date' => '2025-03-15',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/shifts?date_from=2025-03-01&date_to=2025-03-31');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_list_shifts_filtered_by_staff(): void
    {
        $staff1 = StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'S1', 'last_name' => 'T']);
        $staff2 = StaffUser::create(['store_id' => $this->store->id, 'first_name' => 'S2', 'last_name' => 'T']);

        $template = ShiftTemplate::create([
            'store_id' => $this->store->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff1->id,
            'shift_template_id' => $template->id,
            'date' => '2025-03-01',
        ]);

        ShiftSchedule::create([
            'store_id' => $this->store->id,
            'staff_user_id' => $staff2->id,
            'shift_template_id' => $template->id,
            'date' => '2025-03-02',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/shifts?staff_user_id={$staff1->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_attendance_date_range_filter(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Date',
            'last_name' => 'Range',
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subDays(10),
            'clock_out_at' => now()->subDays(10)->addHours(8),
        ]);

        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id' => $this->store->id,
            'clock_in_at' => now()->subDays(1),
            'clock_out_at' => now(),
        ]);

        $dateFrom = now()->subDays(2)->toDateString();
        $dateTo = now()->toDateString();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/attendance?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ═══════════════════════════════════════════════════════════
    // User Account Linking
    // ═══════════════════════════════════════════════════════════

    public function test_link_user_account_to_staff(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Link',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $linkableUser = User::create([
            'name' => 'Linkable User',
            'email' => 'linkable@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/link-user", [
                'user_id' => $linkableUser->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.user_id', $linkableUser->id)
            ->assertJsonPath('data.linked_user.id', $linkableUser->id)
            ->assertJsonPath('data.linked_user.name', 'Linkable User')
            ->assertJsonPath('data.linked_user.email', 'linkable@test.com');

        $this->assertDatabaseHas('staff_users', [
            'id' => $staff->id,
            'user_id' => $linkableUser->id,
        ]);
    }

    public function test_unlink_user_account_from_staff(): void
    {
        $linkableUser = User::create([
            'name' => 'Unlink User',
            'email' => 'unlink@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Unlink',
            'last_name' => 'Test',
            'status' => 'active',
            'user_id' => $linkableUser->id,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/members/{$staff->id}/link-user");

        $response->assertOk()
            ->assertJsonPath('data.user_id', null);

        $this->assertDatabaseHas('staff_users', [
            'id' => $staff->id,
            'user_id' => null,
        ]);
    }

    public function test_cannot_link_user_from_different_store(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Cross',
            'last_name' => 'Store',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/link-user", [
                'user_id' => $this->otherUser->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_link_user_already_linked_to_another_staff(): void
    {
        $linkableUser = User::create([
            'name' => 'Already Linked',
            'email' => 'already@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'First',
            'last_name' => 'Staff',
            'status' => 'active',
            'user_id' => $linkableUser->id,
        ]);

        $secondStaff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Second',
            'last_name' => 'Staff',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$secondStaff->id}/link-user", [
                'user_id' => $linkableUser->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_unlink_staff_without_linked_user(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'No',
            'last_name' => 'Link',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/members/{$staff->id}/link-user");

        $response->assertStatus(422);
    }

    public function test_linkable_users_returns_unlinked_users_only(): void
    {
        $linkedUser = User::create([
            'name' => 'Already Linked',
            'email' => 'linked@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $freeUser = User::create([
            'name' => 'Free User',
            'email' => 'free@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Linked',
            'last_name' => 'Staff',
            'status' => 'active',
            'user_id' => $linkedUser->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v2/staff/members/linkable-users');

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($freeUser->id, $userIds);
        $this->assertNotContains($linkedUser->id, $userIds);
    }

    public function test_staff_show_includes_user_id(): void
    {
        $linkableUser = User::create([
            'name' => 'Show User',
            'email' => 'show@test.com',
            'password_hash' => bcrypt('password'),
            'store_id' => $this->store->id,
            'organization_id' => $this->org->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Show',
            'last_name' => 'Staff',
            'status' => 'active',
            'user_id' => $linkableUser->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}");

        $response->assertOk()
            ->assertJsonPath('data.user_id', $linkableUser->id);
    }

    public function test_link_user_requires_valid_user_id(): void
    {
        $staff = StaffUser::create([
            'store_id' => $this->store->id,
            'first_name' => 'Validate',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/link-user", [
                'user_id' => 'not-a-uuid',
            ]);

        $response->assertUnprocessable();
    }

    public function test_link_user_cross_store_isolation(): void
    {
        $otherStaff = StaffUser::create([
            'store_id' => $this->otherStore->id,
            'first_name' => 'Other',
            'last_name' => 'Staff',
            'status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$otherStaff->id}/link-user", [
                'user_id' => $this->user->id,
            ]);

        $response->assertNotFound();
    }

    // ─── Staff Documents Tests ────────────────────────────────────────────────

    private function createStaff(array $overrides = []): StaffUser
    {
        return StaffUser::create(array_merge([
            'store_id'        => $this->store->id,
            'first_name'      => 'Doc',
            'last_name'       => 'Test',
            'employment_type' => 'full_time',
            'status'          => 'active',
        ], $overrides));
    }

    public function test_list_staff_documents_empty(): void
    {
        $staff = $this->createStaff();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/documents");

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_add_staff_document(): void
    {
        $staff = $this->createStaff();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/documents", [
                'document_type' => 'national_id',
                'file_url'      => 'https://storage.example.com/docs/id.pdf',
                'expiry_date'   => now()->addYear()->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_type', 'national_id')
            ->assertJsonPath('data.file_url', 'https://storage.example.com/docs/id.pdf')
            ->assertJsonStructure(['data' => [
                'id', 'document_type', 'file_url', 'expiry_date',
                'days_until_expiry', 'is_expired', 'expiring_soon',
            ]]);
    }

    public function test_add_document_validates_type(): void
    {
        $staff = $this->createStaff();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/documents", [
                'document_type' => 'invalid_type',
                'file_url'      => 'https://storage.example.com/docs/id.pdf',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type']);
    }

    public function test_add_document_requires_file_url(): void
    {
        $staff = $this->createStaff();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/documents", [
                'document_type' => 'national_id',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file_url']);
    }

    public function test_delete_staff_document(): void
    {
        $staff = $this->createStaff();

        $createResponse = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/documents", [
                'document_type' => 'contract',
                'file_url'      => 'https://storage.example.com/docs/contract.pdf',
            ]);
        $docId = $createResponse->json('data.id');

        $deleteResponse = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/members/{$staff->id}/documents/{$docId}");

        $deleteResponse->assertOk();

        $listResponse = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/documents");

        $listResponse->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_documents_cross_store_isolation(): void
    {
        $otherStaff = StaffUser::create([
            'store_id'        => $this->otherStore->id,
            'first_name'      => 'Other',
            'last_name'       => 'Staff',
            'employment_type' => 'full_time',
            'status'          => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$otherStaff->id}/documents");

        $response->assertNotFound();
    }

    public function test_document_expiry_flags(): void
    {
        $staff = $this->createStaff();

        // Expired document
        $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/documents", [
                'document_type' => 'visa',
                'file_url'      => 'https://storage.example.com/docs/visa.pdf',
                'expiry_date'   => now()->subDay()->toDateString(),
            ]);

        // Workaround: validate flag via raw query since backend validates expiry_date is after today
        $this->assertDatabaseCount('staff_documents', 1);
        $doc = \App\Domain\StaffManagement\Models\StaffDocument::first();
        $doc->update(['expiry_date' => now()->subDay()->toDateString()]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/documents");

        $response->assertOk();
        $data = $response->json('data.0');
        $this->assertTrue($data['is_expired']);
        $this->assertFalse($data['expiring_soon']);
    }

    // ─── Training Sessions Tests ───────────────────────────────────────────────

    public function test_list_training_sessions_empty(): void
    {
        $staff = $this->createStaff();

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/training-sessions");

        $response->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_start_training_session(): void
    {
        $staff = $this->createStaff();

        $response = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/training-sessions", [
                'notes' => 'First training session',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.notes', 'First training session')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => [
                'id', 'staff_user_id', 'started_at', 'ended_at',
                'transactions_count', 'notes', 'is_active', 'duration_minutes',
            ]]);
    }

    public function test_starting_new_session_ends_open_one(): void
    {
        $staff = $this->createStaff();

        $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/training-sessions", [
                'notes' => 'Session 1',
            ]);

        $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/training-sessions", [
                'notes' => 'Session 2',
            ]);

        $listResponse = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/training-sessions");

        $sessions = $listResponse->json('data.data');
        $this->assertCount(2, $sessions);

        // The first session should now be ended (is_active = false)
        $activeCount = collect($sessions)->where('is_active', true)->count();
        $this->assertEquals(1, $activeCount);
    }

    public function test_end_training_session(): void
    {
        $staff = $this->createStaff();

        $startResponse = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/training-sessions");

        $sessionId = $startResponse->json('data.id');

        $endResponse = $this->withToken($this->token)
            ->putJson("/api/v2/staff/members/{$staff->id}/training-sessions/{$sessionId}/end", [
                'transactions_count' => 15,
                'notes'              => 'Completed well',
            ]);

        $endResponse->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.transactions_count', 15);

        $this->assertNotNull($endResponse->json('data.ended_at'));
    }

    public function test_end_already_ended_session_fails(): void
    {
        $staff = $this->createStaff();

        $startResponse = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/training-sessions");

        $sessionId = $startResponse->json('data.id');

        $this->withToken($this->token)
            ->putJson("/api/v2/staff/members/{$staff->id}/training-sessions/{$sessionId}/end");

        $secondEnd = $this->withToken($this->token)
            ->putJson("/api/v2/staff/members/{$staff->id}/training-sessions/{$sessionId}/end");

        $secondEnd->assertUnprocessable();
    }

    public function test_delete_training_session(): void
    {
        $staff = $this->createStaff();

        $startResponse = $this->withToken($this->token)
            ->postJson("/api/v2/staff/members/{$staff->id}/training-sessions");

        $sessionId = $startResponse->json('data.id');

        $deleteResponse = $this->withToken($this->token)
            ->deleteJson("/api/v2/staff/members/{$staff->id}/training-sessions/{$sessionId}");

        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('training_sessions', ['id' => $sessionId]);
    }

    public function test_training_sessions_cross_store_isolation(): void
    {
        $otherStaff = StaffUser::create([
            'store_id'        => $this->otherStore->id,
            'first_name'      => 'Other',
            'last_name'       => 'Staff',
            'employment_type' => 'full_time',
            'status'          => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$otherStaff->id}/training-sessions");

        $response->assertNotFound();
    }

    public function test_training_session_pagination(): void
    {
        $staff = $this->createStaff();

        // Create 5 sessions
        for ($i = 0; $i < 5; $i++) {
            $session = \App\Domain\StaffManagement\Models\TrainingSession::create([
                'staff_user_id' => $staff->id,
                'store_id'      => $this->store->id,
                'started_at'    => now()->subHours($i + 1),
                'ended_at'      => now()->subHours($i),
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson("/api/v2/staff/members/{$staff->id}/training-sessions?per_page=3");

        $response->assertOk()
            ->assertJsonPath('data.per_page', 3)
            ->assertJsonCount(3, 'data.data');
    }
}
