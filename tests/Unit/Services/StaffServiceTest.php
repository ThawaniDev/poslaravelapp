<?php

namespace Tests\Unit\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Enums\CommissionRuleType;
use App\Domain\StaffManagement\Enums\EmploymentType;
use App\Domain\StaffManagement\Enums\SalaryType;
use App\Domain\StaffManagement\Enums\StaffStatus;
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\BreakRecord;
use App\Domain\StaffManagement\Models\CommissionRule;
use App\Domain\StaffManagement\Models\ShiftSchedule;
use App\Domain\StaffManagement\Models\ShiftTemplate;
use App\Domain\StaffManagement\Models\StaffDocument;
use App\Domain\StaffManagement\Models\StaffUser;
use App\Domain\StaffManagement\Models\TrainingSession;
use App\Domain\StaffManagement\Services\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Unit tests for StaffService business logic.
 *
 * Tests each service method in isolation, verifying:
 * - Correct data transformations
 * - Business rule enforcement
 * - Edge cases and error conditions
 */
class StaffServiceTest extends TestCase
{
    use RefreshDatabase;

    private StaffService $service;
    private User $owner;
    private Store $store;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StaffService::class);

        $this->org = Organization::create([
            'name'          => 'Test Org',
            'business_type' => 'grocery',
            'country'       => 'OM',
        ]);

        $this->store = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Store',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
            'is_main_branch'  => true,
        ]);

        $this->owner = User::create([
            'name'            => 'Owner',
            'email'           => 'owner@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'owner',
            'is_active'       => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Staff Creation
    // ═══════════════════════════════════════════════════════════

    public function test_create_staff_with_all_required_fields(): void
    {
        $staff = $this->service->create([
            'store_id'        => $this->store->id,
            'first_name'      => 'Ahmed',
            'last_name'       => 'Al-Ahmad',
            'employment_type' => 'full_time',
            'salary_type'     => 'monthly',
            'hire_date'       => now()->toDateString(),
            'pin'             => '1234',
        ]);

        $this->assertInstanceOf(StaffUser::class, $staff);
        $this->assertEquals('Ahmed', $staff->first_name);
        $this->assertEquals('Al-Ahmad', $staff->last_name);
        $this->assertEquals('full_time', $staff->employment_type instanceof \BackedEnum ? $staff->employment_type->value : $staff->employment_type);
        $this->assertNotNull($staff->pin_hash);
        $this->assertTrue(Hash::check('1234', $staff->pin_hash));
    }

    public function test_create_staff_hashes_pin_correctly(): void
    {
        $staff = $this->service->create([
            'store_id'        => $this->store->id,
            'first_name'      => 'Test',
            'last_name'       => 'User',
            'employment_type' => 'full_time',
            'salary_type'     => 'monthly',
            'hire_date'       => now()->toDateString(),
            'pin'             => '9876',
        ]);

        // Raw PIN should not appear in the hash
        $this->assertNotEquals('9876', $staff->pin_hash);
        $this->assertTrue(Hash::check('9876', $staff->pin_hash));
    }

    public function test_create_staff_without_pin(): void
    {
        $staff = $this->service->create([
            'store_id'        => $this->store->id,
            'first_name'      => 'No',
            'last_name'       => 'Pin',
            'employment_type' => 'full_time',
            'salary_type'     => 'monthly',
            'hire_date'       => now()->toDateString(),
        ]);

        // pin_hash is NOT NULL in DB and defaults to a placeholder hash when no PIN is provided
        $this->assertNotNull($staff->pin_hash);
    }

    public function test_create_staff_sets_active_status_by_default(): void
    {
        $staff = $this->service->create([
            'store_id'        => $this->store->id,
            'first_name'      => 'Active',
            'last_name'       => 'Staff',
            'employment_type' => 'full_time',
            'salary_type'     => 'monthly',
            'hire_date'       => now()->toDateString(),
        ]);

        $this->assertEquals('active', $staff->status instanceof \BackedEnum ? $staff->status->value : $staff->status);
    }

    public function test_create_staff_with_optional_fields(): void
    {
        $staff = $this->service->create([
            'store_id'        => $this->store->id,
            'first_name'      => 'Full',
            'last_name'       => 'Record',
            'email'           => 'full@example.com',
            'phone'           => '+966501234567',
            'national_id'     => 'ID123456',
            'employment_type' => 'part_time',
            'salary_type'     => 'hourly',
            'hourly_rate'     => 25.50,
            'hire_date'       => now()->toDateString(),
            'language_preference' => 'ar',
        ]);

        $this->assertEquals('full@example.com', $staff->email);
        $this->assertEquals('+966501234567', $staff->phone);
        $this->assertEquals('ID123456', $staff->national_id);
        $this->assertEquals(25.50, (float) $staff->hourly_rate);
        $this->assertEquals('ar', $staff->language_preference);
    }

    // ═══════════════════════════════════════════════════════════
    // Staff Update
    // ═══════════════════════════════════════════════════════════

    public function test_update_staff_basic_fields(): void
    {
        $staff = $this->createStaff();

        $updated = $this->service->update($staff, [
            'first_name' => 'Updated',
            'last_name'  => 'Name',
            'phone'      => '+966509999999',
        ]);

        $this->assertEquals('Updated', $updated->first_name);
        $this->assertEquals('Name', $updated->last_name);
        $this->assertEquals('+966509999999', $updated->phone);
    }

    public function test_update_staff_status_to_on_leave(): void
    {
        $staff = $this->createStaff();

        $updated = $this->service->update($staff, ['status' => 'on_leave']);

        $this->assertEquals('on_leave', $updated->status instanceof \BackedEnum ? $updated->status->value : $updated->status);
    }

    public function test_update_staff_sets_termination_date_when_deactivated(): void
    {
        $staff = $this->createStaff();

        $updated = $this->service->update($staff, [
            'status'           => 'inactive',
            'termination_date' => now()->toDateString(),
        ]);

        $this->assertEquals('inactive', $updated->status instanceof \BackedEnum ? $updated->status->value : $updated->status);
        $this->assertNotNull($updated->termination_date);
    }

    // ═══════════════════════════════════════════════════════════
    // PIN Management
    // ═══════════════════════════════════════════════════════════

    public function test_set_pin_updates_hash(): void
    {
        $staff = $this->createStaff();
        $oldHash = $staff->pin_hash;

        $updated = $this->service->setPin($staff, '5678');

        $this->assertNotEquals($oldHash, $updated->pin_hash);
        $this->assertTrue(Hash::check('5678', $updated->pin_hash));
    }

    public function test_set_pin_rejects_short_pin(): void
    {
        $staff = $this->createStaff();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->setPin($staff, '12');
    }

    public function test_set_pin_accepts_min_4_digits(): void
    {
        $staff = $this->createStaff();
        $updated = $this->service->setPin($staff, '1234');

        $this->assertTrue(Hash::check('1234', $updated->pin_hash));
    }

    // ═══════════════════════════════════════════════════════════
    // NFC Badge
    // ═══════════════════════════════════════════════════════════

    public function test_register_nfc_stores_uid(): void
    {
        $staff = $this->createStaff();

        $updated = $this->service->registerNfc($staff, 'NFC-UID-12345');

        $this->assertEquals('NFC-UID-12345', $updated->nfc_badge_uid);
    }

    public function test_register_nfc_replaces_existing(): void
    {
        $staff = $this->createStaff(['nfc_badge_uid' => 'OLD-UID']);

        $updated = $this->service->registerNfc($staff, 'NEW-UID-999');

        $this->assertEquals('NEW-UID-999', $updated->nfc_badge_uid);
    }

    // ═══════════════════════════════════════════════════════════
    // Clock In / Out
    // ═══════════════════════════════════════════════════════════

    public function test_clock_in_creates_attendance_record(): void
    {
        $staff = $this->createStaff();

        $record = $this->service->clockIn($staff->id, $this->store->id, 'Morning shift');

        $this->assertInstanceOf(AttendanceRecord::class, $record);
        $this->assertEquals($staff->id, $record->staff_user_id);
        $this->assertNotNull($record->clock_in_at);
        $this->assertNull($record->clock_out_at);
    }

    public function test_clock_in_fails_if_already_clocked_in(): void
    {
        $staff = $this->createStaff();
        $this->service->clockIn($staff->id, $this->store->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Already clocked in');
        $this->service->clockIn($staff->id, $this->store->id);
    }

    public function test_clock_out_closes_attendance_record(): void
    {
        $staff = $this->createStaff();
        $this->service->clockIn($staff->id, $this->store->id);

        $record = $this->service->clockOut($staff->id, $this->store->id);

        $this->assertNotNull($record->clock_out_at);
        $this->assertGreaterThanOrEqual(0, $record->work_minutes);
    }

    public function test_clock_out_calculates_work_minutes(): void
    {
        $staff = $this->createStaff();

        // Manually create a record with known clock-in time
        $clockIn = now()->subMinutes(90);
        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id'      => $this->store->id,
            'clock_in_at'   => $clockIn,
            'auth_method'   => 'pin',
        ]);

        $record = $this->service->clockOut($staff->id, $this->store->id);

        $this->assertGreaterThanOrEqual(88, $record->work_minutes);
        $this->assertLessThanOrEqual(92, $record->work_minutes);
    }

    public function test_clock_out_fails_without_clock_in(): void
    {
        $staff = $this->createStaff();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->clockOut($staff->id, $this->store->id);
    }

    // ═══════════════════════════════════════════════════════════
    // Break Management
    // ═══════════════════════════════════════════════════════════

    public function test_start_break_creates_break_record(): void
    {
        $staff = $this->createStaff();
        $attendance = $this->service->clockIn($staff->id, $this->store->id);

        $breakRecord = $this->service->startBreak($attendance->id);

        $this->assertInstanceOf(BreakRecord::class, $breakRecord);
        $this->assertNotNull($breakRecord->break_start);
        $this->assertNull($breakRecord->break_end);
    }

    public function test_end_break_closes_break_record(): void
    {
        $staff   = $this->createStaff();
        $attendance = $this->service->clockIn($staff->id, $this->store->id);
        // Start break with a time in the past so break_end > break_start
        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_start'          => now()->subMinute(),
        ]);

        $breakRecord = $this->service->endBreak($attendance->id);

        $this->assertNotNull($breakRecord->break_end);
        $this->assertGreaterThan($breakRecord->break_start, $breakRecord->break_end);
    }

    public function test_start_break_fails_if_already_on_break(): void
    {
        $staff      = $this->createStaff();
        $attendance = $this->service->clockIn($staff->id, $this->store->id);
        $this->service->startBreak($attendance->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->startBreak($attendance->id);
    }

    public function test_end_break_fails_if_no_active_break(): void
    {
        $staff      = $this->createStaff();
        $attendance = $this->service->clockIn($staff->id, $this->store->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->endBreak($attendance->id);
    }

    public function test_end_break_updates_break_minutes_on_attendance_record(): void
    {
        $staff      = $this->createStaff();
        $attendance = $this->service->clockIn($staff->id, $this->store->id);

        // Manually create break with 15 min ago start
        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_start'          => now()->subMinutes(15),
        ]);

        $this->service->endBreak($attendance->id);

        $attendance->refresh();
        $this->assertGreaterThanOrEqual(14, $attendance->break_minutes);
        $this->assertLessThanOrEqual(16, $attendance->break_minutes);
    }

    // ═══════════════════════════════════════════════════════════
    // Shift Templates
    // ═══════════════════════════════════════════════════════════

    public function test_create_shift_template(): void
    {
        $template = $this->service->createShiftTemplate([
            'store_id'   => $this->store->id,
            'name'       => 'Morning',
            'start_time' => '08:00',
            'end_time'   => '16:00',
            'color'      => '#4CAF50',
        ]);

        $this->assertEquals('Morning', $template->name);
        $this->assertEquals('08:00', $template->start_time);
        $this->assertEquals('16:00', $template->end_time);
    }

    public function test_update_shift_template(): void
    {
        $template = $this->service->createShiftTemplate([
            'store_id'   => $this->store->id,
            'name'       => 'Old Name',
            'start_time' => '08:00',
            'end_time'   => '16:00',
        ]);

        $updated = $this->service->updateShiftTemplate($template, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
    }

    public function test_delete_shift_template(): void
    {
        $template = $this->service->createShiftTemplate([
            'store_id'   => $this->store->id,
            'name'       => 'Temp',
            'start_time' => '08:00',
            'end_time'   => '16:00',
        ]);

        $result = $this->service->deleteShiftTemplate($template);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('shift_templates', ['id' => $template->id]);
    }

    // ═══════════════════════════════════════════════════════════
    // Shift Scheduling
    // ═══════════════════════════════════════════════════════════

    public function test_create_shift_schedule(): void
    {
        $staff    = $this->createStaff();
        $template = $this->createShiftTemplate();

        $shift = $this->service->createShift([
            'store_id'          => $this->store->id,
            'staff_user_id'     => $staff->id,
            'shift_template_id' => $template->id,
            'start_date'        => now()->addDay()->toDateString(),
            'end_date'          => now()->addDays(7)->toDateString(),
        ]);

        $this->assertInstanceOf(ShiftSchedule::class, $shift);
        $this->assertEquals($staff->id, $shift->staff_user_id);
    }

    public function test_bulk_create_shifts_for_multiple_staff(): void
    {
        $staff1   = $this->createStaff(['email' => 's1@test.com']);
        $staff2   = $this->createStaff(['email' => 's2@test.com']);
        $template = $this->createShiftTemplate();

        $shifts = $this->service->bulkCreateShifts([
            'store_id'          => $this->store->id,
            'shift_template_id' => $template->id,
            'start_date'        => now()->addDay()->toDateString(),
            'end_date'          => now()->addDays(3)->toDateString(),
            'staff_user_ids'    => [$staff1->id, $staff2->id],
        ]);

        $this->assertCount(2, $shifts);
    }

    public function test_delete_shift(): void
    {
        $staff    = $this->createStaff();
        $template = $this->createShiftTemplate();
        $shift    = $this->service->createShift([
            'store_id'          => $this->store->id,
            'staff_user_id'     => $staff->id,
            'shift_template_id' => $template->id,
            'start_date'        => now()->addDay()->toDateString(),
            'end_date'          => now()->addDays(3)->toDateString(),
        ]);

        $this->service->deleteShift($shift);

        $this->assertDatabaseMissing('shift_schedules', ['id' => $shift->id]);
    }

    // ═══════════════════════════════════════════════════════════
    // Commission Rules
    // ═══════════════════════════════════════════════════════════

    public function test_set_commission_config_flat_percentage(): void
    {
        $staff = $this->createStaff();

        $rule = $this->service->setCommissionConfig($staff, [
            'type'       => 'flat_percentage',
            'percentage' => 3.5,
            'is_active'  => true,
        ]);

        $this->assertSame('flat_percentage', $rule->type instanceof \BackedEnum ? $rule->type->value : $rule->type);
        $this->assertEquals(3.5, (float) $rule->percentage);
        $this->assertTrue((bool) $rule->is_active);
    }

    public function test_set_commission_config_replaces_existing(): void
    {
        $staff = $this->createStaff();

        // Create first rule
        $this->service->setCommissionConfig($staff, [
            'type'       => 'flat_percentage',
            'percentage' => 2.0,
        ]);

        // Replace with second rule
        $rule = $this->service->setCommissionConfig($staff, [
            'type'       => 'flat_percentage',
            'percentage' => 5.0,
        ]);

        $totalRules = CommissionRule::where('staff_user_id', $staff->id)->count();
        $this->assertEquals(1, $totalRules);
        $this->assertEquals(5.0, (float) $rule->percentage);
    }

    public function test_set_commission_config_tiered(): void
    {
        $staff = $this->createStaff();

        $rule = $this->service->setCommissionConfig($staff, [
            'type'       => 'tiered',
            'tiers_json' => [
                ['min' => 0,    'max' => 1000,  'pct' => 1.0],
                ['min' => 1001, 'max' => 5000,  'pct' => 2.0],
                ['min' => 5001, 'max' => null,   'pct' => 3.0],
            ],
        ]);

        $ruleType = $rule->type instanceof \BackedEnum ? $rule->type->value : $rule->type;
        $this->assertEquals('tiered', $ruleType);
        $this->assertIsArray($rule->tiers_json);
        $this->assertCount(3, $rule->tiers_json);
    }

    // ═══════════════════════════════════════════════════════════
    // Staff Documents
    // ═══════════════════════════════════════════════════════════

    public function test_add_document_persists_to_db(): void
    {
        $staff = $this->createStaff();

        $doc = $this->service->addDocument($staff, [
            'document_type' => 'national_id',
            'file_url'      => 'https://cdn.example.com/docs/id.pdf',
            'expiry_date'   => now()->addYear()->toDateString(),
        ]);

        $this->assertInstanceOf(StaffDocument::class, $doc);
        $this->assertEquals($staff->id, $doc->staff_user_id);
        $this->assertEquals('national_id', $doc->document_type->value);
    }

    public function test_list_documents_returns_correct_staff_docs(): void
    {
        $staff1 = $this->createStaff(['email' => 'doc1@test.com']);
        $staff2 = $this->createStaff(['email' => 'doc2@test.com']);

        $this->service->addDocument($staff1, [
            'document_type' => 'contract',
            'file_url'      => 'https://cdn.example.com/contract.pdf',
        ]);
        $this->service->addDocument($staff1, [
            'document_type' => 'visa',
            'file_url'      => 'https://cdn.example.com/visa.pdf',
        ]);
        $this->service->addDocument($staff2, [
            'document_type' => 'national_id',
            'file_url'      => 'https://cdn.example.com/id.pdf',
        ]);

        $docs1 = $this->service->listDocuments($staff1->id);
        $docs2 = $this->service->listDocuments($staff2->id);

        $this->assertCount(2, $docs1);
        $this->assertCount(1, $docs2);
    }

    public function test_delete_document_removes_from_db(): void
    {
        $staff = $this->createStaff();
        $doc   = $this->service->addDocument($staff, [
            'document_type' => 'certificate',
            'file_url'      => 'https://cdn.example.com/cert.pdf',
        ]);

        $this->service->deleteDocument($staff, $doc->id);

        $this->assertDatabaseMissing('staff_documents', ['id' => $doc->id]);
    }

    public function test_delete_document_from_wrong_staff_throws(): void
    {
        $staff1 = $this->createStaff(['email' => 'sd1@test.com']);
        $staff2 = $this->createStaff(['email' => 'sd2@test.com']);
        $doc    = $this->service->addDocument($staff1, [
            'document_type' => 'visa',
            'file_url'      => 'https://cdn.example.com/visa.pdf',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->deleteDocument($staff2, $doc->id);
    }

    public function test_document_expiry_date_stored_as_date(): void
    {
        $staff       = $this->createStaff();
        $expiryDate  = now()->addYear()->toDateString();

        $doc = $this->service->addDocument($staff, [
            'document_type' => 'visa',
            'file_url'      => 'https://cdn.example.com/visa.pdf',
            'expiry_date'   => $expiryDate,
        ]);

        $this->assertEquals($expiryDate, $doc->expiry_date instanceof \Carbon\Carbon ? $doc->expiry_date->toDateString() : $doc->expiry_date);
    }

    // ═══════════════════════════════════════════════════════════
    // Training Sessions
    // ═══════════════════════════════════════════════════════════

    public function test_start_training_session(): void
    {
        $staff = $this->createStaff();

        $session = $this->service->startTrainingSession($staff, ['notes' => 'Intro session']);

        $this->assertInstanceOf(TrainingSession::class, $session);
        $this->assertEquals($staff->id, $session->staff_user_id);
        $this->assertNotNull($session->started_at);
        $this->assertNull($session->ended_at);
        $this->assertEquals('Intro session', $session->notes);
    }

    public function test_starting_new_session_auto_ends_open_one(): void
    {
        $staff = $this->createStaff();

        $session1 = $this->service->startTrainingSession($staff);
        $session2 = $this->service->startTrainingSession($staff, ['notes' => 'second']);

        $session1->refresh();
        $this->assertNotNull($session1->ended_at);
        $this->assertNull($session2->ended_at);
    }

    public function test_end_training_session_sets_ended_at(): void
    {
        $staff   = $this->createStaff();
        $session = $this->service->startTrainingSession($staff);

        $ended = $this->service->endTrainingSession($session, [
            'transactions_count' => 12,
            'notes'              => 'Completed well',
        ]);

        $this->assertNotNull($ended->ended_at);
        $this->assertEquals(12, $ended->transactions_count);
        $this->assertEquals('Completed well', $ended->notes);
    }

    public function test_end_already_ended_session_throws(): void
    {
        $staff   = $this->createStaff();
        $session = $this->service->startTrainingSession($staff);
        $this->service->endTrainingSession($session);

        $session->refresh();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->endTrainingSession($session);
    }

    public function test_delete_training_session(): void
    {
        $staff   = $this->createStaff();
        $session = $this->service->startTrainingSession($staff);
        $id      = $session->id;

        $this->service->deleteTrainingSession($session);

        $this->assertDatabaseMissing('training_sessions', ['id' => $id]);
    }

    public function test_list_training_sessions_returns_paginated(): void
    {
        $staff = $this->createStaff();

        for ($i = 0; $i < 5; $i++) {
            TrainingSession::create([
                'staff_user_id' => $staff->id,
                'store_id'      => $this->store->id,
                'started_at'    => now()->subDays($i + 1),
                'ended_at'      => now()->subDays($i),
            ]);
        }

        $paginator = $this->service->listTrainingSessions($staff->id, 3);

        $this->assertEquals(5, $paginator->total());
        $this->assertCount(3, $paginator->items());
    }

    // ═══════════════════════════════════════════════════════════
    // Branch Assignments
    // ═══════════════════════════════════════════════════════════

    public function test_assign_staff_to_branch(): void
    {
        $staff  = $this->createStaff();
        $branch = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Branch',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
        ]);

        $assignment = $this->service->assignBranch($staff, [
            'branch_id'  => $branch->id,
            'is_primary' => false,
        ]);

        $this->assertEquals($staff->id, $assignment->staff_user_id);
        $this->assertEquals($branch->id, $assignment->branch_id);
    }

    public function test_unassign_staff_from_branch(): void
    {
        $staff  = $this->createStaff();
        $branch = Store::create([
            'organization_id' => $this->org->id,
            'name'            => 'Branch2',
            'business_type'   => 'grocery',
            'currency'        => 'SAR',
            'is_active'       => true,
        ]);
        $this->service->assignBranch($staff, ['branch_id' => $branch->id]);

        $result = $this->service->unassignBranch($staff, $branch->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('staff_branch_assignments', [
            'staff_user_id' => $staff->id,
            'branch_id'     => $branch->id,
        ]);
    }

    public function test_unassign_nonexistent_branch_throws(): void
    {
        $staff = $this->createStaff();

        $this->expectException(\InvalidArgumentException::class);
        // Use a valid UUID format that simply does not exist in the DB
        $this->service->unassignBranch($staff, '00000000-0000-0000-0000-000000000000');
    }

    // ═══════════════════════════════════════════════════════════
    // Staff Stats
    // ═══════════════════════════════════════════════════════════

    public function test_get_stats_returns_correct_totals(): void
    {
        // 2 active, 1 inactive
        $this->createStaff(['email' => 'a@t.com', 'status' => 'active']);
        $this->createStaff(['email' => 'b@t.com', 'status' => 'active']);
        $this->createStaff(['email' => 'c@t.com', 'status' => 'inactive']);

        $stats = $this->service->getStats($this->store->id);

        $this->assertArrayHasKey('total_staff', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('currently_clocked_in', $stats);
        $this->assertGreaterThanOrEqual(3, $stats['total_staff']);
        $this->assertGreaterThanOrEqual(2, $stats['active']);
    }

    public function test_get_stats_clocked_in_count(): void
    {
        $staff1 = $this->createStaff(['email' => 'ci1@t.com']);
        $staff2 = $this->createStaff(['email' => 'ci2@t.com']);

        // Clock in one
        $this->service->clockIn($staff1->id, $this->store->id);

        $stats = $this->service->getStats($this->store->id);

        $this->assertGreaterThanOrEqual(1, $stats['currently_clocked_in']);
    }

    // ═══════════════════════════════════════════════════════════
    // User Account Linking
    // ═══════════════════════════════════════════════════════════

    public function test_link_user_account(): void
    {
        $staff = $this->createStaff();
        $user  = User::create([
            'name'            => 'Linked User',
            'email'           => 'linked@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'cashier',
            'is_active'       => true,
        ]);

        $updated = $this->service->linkUserAccount($staff, $user->id);

        $this->assertEquals($user->id, $updated->user_id);
    }

    public function test_unlink_user_account(): void
    {
        $staff = $this->createStaff();
        $user  = User::create([
            'name'            => 'To Unlink',
            'email'           => 'tounlink@test.com',
            'password_hash'   => bcrypt('password'),
            'store_id'        => $this->store->id,
            'organization_id' => $this->org->id,
            'role'            => 'cashier',
            'is_active'       => true,
        ]);

        $this->service->linkUserAccount($staff, $user->id);
        $updated = $this->service->unlinkUserAccount($staff);

        $this->assertNull($updated->user_id);
    }

    public function test_unlink_staff_without_linked_user_throws(): void
    {
        $staff = $this->createStaff();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->unlinkUserAccount($staff);
    }

    // ═══════════════════════════════════════════════════════════
    // Attendance Summary
    // ═══════════════════════════════════════════════════════════

    public function test_attendance_summary_aggregates_correctly(): void
    {
        $staff = $this->createStaff();

        // Create two complete attendance records
        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id'      => $this->store->id,
            'clock_in_at'   => now()->subHours(9),
            'clock_out_at'  => now()->subHours(1),
            'work_minutes'  => 480,
            'break_minutes' => 30,
            'auth_method'   => 'pin',
        ]);
        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id'      => $this->store->id,
            'clock_in_at'   => now()->subDays(1)->subHours(9),
            'clock_out_at'  => now()->subDays(1)->subHour(),
            'work_minutes'  => 480,
            'break_minutes' => 0,
            'auth_method'   => 'pin',
        ]);

        $summary = $this->service->getAttendanceSummary($this->store->id);

        $this->assertArrayHasKey('total_records', $summary);
        $this->assertArrayHasKey('total_work_hours', $summary);
        $this->assertGreaterThan(0, $summary['total_work_hours']);
    }

    // ═══════════════════════════════════════════════════════════
    // Attendance Export
    // ═══════════════════════════════════════════════════════════

    public function test_export_attendance_returns_array_data(): void
    {
        $staff = $this->createStaff();
        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id'      => $this->store->id,
            'clock_in_at'   => now()->subHours(9),
            'clock_out_at'  => now()->subHour(),
            'work_minutes'  => 480,
            'break_minutes' => 30,
            'auth_method'   => 'pin',
        ]);

        $result = $this->service->exportAttendance($this->store->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertNotEmpty($result['headers']);
    }

    public function test_export_attendance_filtered_by_date(): void
    {
        $staff = $this->createStaff();

        // Old record (should be excluded)
        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id'      => $this->store->id,
            'clock_in_at'   => now()->subMonths(2),
            'clock_out_at'  => now()->subMonths(2)->addHours(8),
            'work_minutes'  => 480,
            'auth_method'   => 'pin',
        ]);

        // Recent record (should be included)
        AttendanceRecord::create([
            'staff_user_id' => $staff->id,
            'store_id'      => $this->store->id,
            'clock_in_at'   => now()->subDays(2),
            'clock_out_at'  => now()->subDays(2)->addHours(8),
            'work_minutes'  => 480,
            'auth_method'   => 'pin',
        ]);

        $result = $this->service->exportAttendance($this->store->id, [
            'date_from' => now()->subWeek()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]);

        $this->assertCount(1, $result['rows']);
    }

    // ═══════════════════════════════════════════════════════════
    // Activity Log
    // ═══════════════════════════════════════════════════════════

    public function test_activity_log_returns_paginated_records(): void
    {
        $staff = $this->createStaff();

        for ($i = 0; $i < 5; $i++) {
            \App\Domain\StaffManagement\Models\StaffActivityLog::create([
                'staff_user_id' => $staff->id,
                'store_id'      => $this->store->id,
                'action'        => 'create_order',
                'entity_type'   => 'order',
                'entity_id'     => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        }

        $paginator = $this->service->getActivityLog($staff->id, 3);

        $this->assertEquals(5, $paginator->total());
        $this->assertCount(3, $paginator->items());
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function createStaff(array $overrides = []): StaffUser
    {
        return StaffUser::create(array_merge([
            'store_id'        => $this->store->id,
            'first_name'      => 'Test',
            'last_name'       => 'Staff',
            'employment_type' => 'full_time',
            'salary_type'     => 'monthly',
            'hire_date'       => now()->toDateString(),
            'status'          => 'active',
            'pin_hash'        => bcrypt('1234'),
        ], $overrides));
    }

    private function createShiftTemplate(array $overrides = []): ShiftTemplate
    {
        return ShiftTemplate::create(array_merge([
            'store_id'   => $this->store->id,
            'name'       => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time'   => '16:00:00',
            'color'      => '#4CAF50',
        ], $overrides));
    }
}
