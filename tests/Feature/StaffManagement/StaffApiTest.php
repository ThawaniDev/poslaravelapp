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
            'business_type' => 'retail',
            'country' => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name' => 'Main Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
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
            'business_type' => 'retail',
            'country' => 'OM',
        ]);
        $this->otherStore = Store::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Other Store',
            'business_type' => 'retail',
            'currency' => 'OMR',
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

        $data = $response->json('data');
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
        $this->assertCount(1, $response->json('data'));
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

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('InStore', $response->json('data.0.first_name'));
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

        $this->assertCount(1, $response->json('data'));
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
        $this->assertCount(1, $response->json('data'));
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
        $this->assertCount(1, $response->json('data'));
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
        $this->assertCount(1, $response->json('data'));
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
}
