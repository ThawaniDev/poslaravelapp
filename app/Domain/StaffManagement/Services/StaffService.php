<?php

namespace App\Domain\StaffManagement\Services;

use App\Domain\Auth\Models\User;
use App\Domain\StaffManagement\Models\StaffUser;
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\BreakRecord;
use App\Domain\StaffManagement\Models\ShiftSchedule;
use App\Domain\StaffManagement\Models\ShiftTemplate;
use App\Domain\StaffManagement\Models\CommissionRule;
use App\Domain\StaffManagement\Models\CommissionEarning;
use App\Domain\StaffManagement\Models\StaffActivityLog;
use App\Domain\StaffManagement\Models\StaffBranchAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffService
{
    // ─── Staff CRUD ──────────────────────────────────────────

    public function list(string $storeId, array $filters = [])
    {
        $query = StaffUser::where('store_id', $storeId);

        if (!empty($filters['search'])) {
            $s = mb_strtolower($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->whereRaw('lower(first_name) like ?', ["%{$s}%"])
                  ->orWhereRaw('lower(last_name) like ?', ["%{$s}%"])
                  ->orWhereRaw('lower(email) like ?', ["%{$s}%"])
                  ->orWhereRaw('lower(phone) like ?', ["%{$s}%"]);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->orderBy('first_name')->paginate($perPage);
    }

    public function find(string $id): StaffUser
    {
        return StaffUser::with([
            'staffBranchAssignments',
            'commissionRules',
        ])->findOrFail($id);
    }

    public function create(array $data): StaffUser
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['pin'])) {
                $data['pin_hash'] = Hash::make($data['pin']);
                unset($data['pin']);
            }

            // Extract user-account fields before creating StaffUser
            $createUserAccount = filter_var($data['create_user_account'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $password = $data['password'] ?? null;
            $userRole = $data['user_role'] ?? null;
            unset($data['create_user_account'], $data['password'], $data['user_role']);

            // Create linked User account if requested
            if ($createUserAccount && $password && $userRole) {
                $store = \App\Domain\Core\Models\Store::findOrFail($data['store_id']);

                $user = User::create([
                    'store_id'        => $data['store_id'],
                    'organization_id' => $store->organization_id,
                    'name'            => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                    'email'           => $data['email'],
                    'phone'           => $data['phone'] ?? null,
                    'password_hash'   => Hash::make($password),
                    'pin_hash'        => $data['pin_hash'] ?? null,
                    'role'            => $userRole,
                    'locale'          => $data['language_preference'] ?? 'en',
                    'is_active'       => true,
                ]);

                $data['user_id'] = $user->id;
            }

            $staff = StaffUser::create($data);

            return $staff->load('user');
        });
    }

    public function update(StaffUser $staff, array $data): StaffUser
    {
        return DB::transaction(function () use ($staff, $data) {
            if (isset($data['pin'])) {
                $data['pin_hash'] = Hash::make($data['pin']);
                unset($data['pin']);
            }

            $staff->update($data);
            return $staff->refresh();
        });
    }

    public function delete(StaffUser $staff): bool
    {
        return $staff->delete();
    }

    public function setPin(StaffUser $staff, string $pin): StaffUser
    {
        $staff->update(['pin_hash' => Hash::make($pin)]);
        return $staff->refresh();
    }

    public function registerNfc(StaffUser $staff, string $nfcBadgeUid): StaffUser
    {
        $staff->update(['nfc_badge_uid' => $nfcBadgeUid]);
        return $staff->refresh();
    }

    // ─── Attendance ─────────────────────────────────────────

    public function listAttendance(string $storeId, array $filters = [])
    {
        $query = AttendanceRecord::where('store_id', $storeId)
            ->with('staffUser', 'breakRecords');

        if (!empty($filters['staff_user_id'])) {
            $query->where('staff_user_id', $filters['staff_user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('clock_in_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('clock_in_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->orderByDesc('clock_in_at')->paginate($perPage);
    }

    public function clockIn(string $staffUserId, string $storeId, ?string $notes = null): AttendanceRecord
    {
        // Check for existing open attendance
        $open = AttendanceRecord::where('staff_user_id', $staffUserId)
            ->where('store_id', $storeId)
            ->whereNull('clock_out_at')
            ->first();

        if ($open) {
            throw new \InvalidArgumentException('Already clocked in. Please clock out first.');
        }

        return AttendanceRecord::create([
            'staff_user_id'     => $staffUserId,
            'store_id'          => $storeId,
            'clock_in_at'       => now(),
            'notes'             => $notes,
        ]);
    }

    public function clockOut(string $staffUserId, string $storeId, ?string $notes = null): AttendanceRecord
    {
        $record = AttendanceRecord::where('staff_user_id', $staffUserId)
            ->where('store_id', $storeId)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        if (!$record) {
            throw new \InvalidArgumentException('No open clock-in found.');
        }

        $clockOut = now();

        // Calculate total break minutes (SQLite-compatible: avoid TIMESTAMPDIFF)
        $breakMinutes = 0;
        foreach ($record->breakRecords()->whereNotNull('break_end')->get() as $br) {
            $breakMinutes += $br->break_start->diffInMinutes($br->break_end);
        }

        // Calculate total work minutes and overtime
        $totalMinutes = $record->clock_in_at->diffInMinutes($clockOut);
        $workMinutes = $totalMinutes - (int) $breakMinutes;

        // Check for scheduled shift to calculate overtime
        $overtime = 0;
        if ($record->scheduled_shift_id) {
            $schedule = ShiftSchedule::find($record->scheduled_shift_id);
            if ($schedule && $schedule->shiftTemplate) {
                $scheduledMinutes = $this->shiftDurationMinutes($schedule->shiftTemplate);
                $overtime = max(0, $workMinutes - $scheduledMinutes);
            }
        }

        $record->update([
            'clock_out_at'     => $clockOut,
            'break_minutes'    => (int) $breakMinutes,
            'overtime_minutes' => $overtime,
            'notes'            => $notes ?? $record->notes,
        ]);

        return $record->refresh();
    }

    public function startBreak(string $attendanceRecordId): BreakRecord
    {
        $record = AttendanceRecord::findOrFail($attendanceRecordId);

        if ($record->clock_out_at) {
            throw new \InvalidArgumentException('Shift already ended.');
        }

        // Check for open break
        $openBreak = BreakRecord::where('attendance_record_id', $attendanceRecordId)
            ->whereNull('break_end')
            ->first();

        if ($openBreak) {
            throw new \InvalidArgumentException('Already on break.');
        }

        return BreakRecord::create([
            'attendance_record_id' => $attendanceRecordId,
            'break_start'          => now(),
        ]);
    }

    public function endBreak(string $attendanceRecordId): BreakRecord
    {
        $breakRecord = BreakRecord::where('attendance_record_id', $attendanceRecordId)
            ->whereNull('break_end')
            ->latest('break_start')
            ->first();

        if (!$breakRecord) {
            throw new \InvalidArgumentException('No open break found.');
        }

        $breakRecord->update(['break_end' => now()]);
        return $breakRecord->refresh();
    }

    // ─── Shifts ─────────────────────────────────────────────

    public function listShifts(string $storeId, array $filters = [])
    {
        $query = ShiftSchedule::where('store_id', $storeId)
            ->with('staffUser', 'shiftTemplate');

        if (!empty($filters['staff_user_id'])) {
            $query->where('staff_user_id', $filters['staff_user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('end_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('start_date', '<=', $filters['date_to']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->orderBy('start_date')->paginate($perPage);
    }

    public function createShift(array $data): ShiftSchedule
    {
        // If only start_date provided, default end_date = start_date
        if (!empty($data['start_date']) && empty($data['end_date'])) {
            $data['end_date'] = $data['start_date'];
        }

        // Check for overlapping periods (same staff + same template + overlapping dates)
        $query = ShiftSchedule::where('store_id', $data['store_id'])
            ->where('staff_user_id', $data['staff_user_id'])
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date']);

        if (!empty($data['shift_template_id'])) {
            $query->where('shift_template_id', $data['shift_template_id']);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Staff already has this shift in the selected period.');
        }

        return ShiftSchedule::create($data);
    }

    public function bulkCreateShifts(array $data): array
    {
        $staffUserIds = $data['staff_user_ids'];
        $startDate = $data['start_date'];
        $endDate = $data['end_date'] ?? $startDate;
        $created = [];

        foreach ($staffUserIds as $staffUserId) {
            $conflict = ShiftSchedule::where('store_id', $data['store_id'])
                ->where('staff_user_id', $staffUserId)
                ->where('shift_template_id', $data['shift_template_id'])
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->exists();

            if (!$conflict) {
                $created[] = ShiftSchedule::create([
                    'store_id'          => $data['store_id'],
                    'staff_user_id'     => $staffUserId,
                    'shift_template_id' => $data['shift_template_id'],
                    'start_date'        => $startDate,
                    'end_date'          => $endDate,
                    'status'            => $data['status'] ?? 'scheduled',
                    'notes'             => $data['notes'] ?? null,
                ]);
            }
        }

        return $created;
    }

    public function updateShift(ShiftSchedule $shift, array $data): ShiftSchedule
    {
        $shift->update($data);
        return $shift->refresh();
    }

    public function deleteShift(ShiftSchedule $shift): bool
    {
        return $shift->delete();
    }

    // ─── Shift Templates ────────────────────────────────────

    public function listShiftTemplates(string $storeId)
    {
        return ShiftTemplate::where('store_id', $storeId)->orderBy('name')->get();
    }

    public function createShiftTemplate(array $data): ShiftTemplate
    {
        return ShiftTemplate::create($data);
    }

    public function updateShiftTemplate(ShiftTemplate $template, array $data): ShiftTemplate
    {
        $template->update($data);
        return $template->refresh();
    }

    public function deleteShiftTemplate(ShiftTemplate $template): bool
    {
        // Check if template is in use
        if ($template->shiftSchedules()->exists()) {
            throw new \InvalidArgumentException('Cannot delete template that is assigned to shifts.');
        }
        return $template->delete();
    }

    // ─── Attendance Summary ─────────────────────────────────

    public function getAttendanceSummary(string $storeId, array $filters = []): array
    {
        $query = AttendanceRecord::where('store_id', $storeId)
            ->with('staffUser');

        if (!empty($filters['staff_user_id'])) {
            $query->where('staff_user_id', $filters['staff_user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('clock_in_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('clock_in_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $records = $query->get();

        $totalRecords = $records->count();
        $completedRecords = $records->whereNotNull('clock_out_at');

        $totalWorkMinutes = 0;
        $totalBreakMinutes = 0;
        $totalOvertimeMinutes = 0;
        $lateCount = 0;
        $onTimeCount = 0;
        $earlyDepartureCount = 0;

        foreach ($completedRecords as $record) {
            $workMins = $record->clock_in_at->diffInMinutes($record->clock_out_at);
            $totalWorkMinutes += $workMins;
            $totalBreakMinutes += (int) ($record->break_minutes ?? 0);
            $totalOvertimeMinutes += (int) ($record->overtime_minutes ?? 0);

            if ($record->status === 'late') {
                $lateCount++;
            } elseif ($record->status === 'on_time') {
                $onTimeCount++;
            } elseif ($record->status === 'early_departure') {
                $earlyDepartureCount++;
            }
        }

        $currentlyClockedIn = AttendanceRecord::where('store_id', $storeId)
            ->whereNull('clock_out_at')
            ->count();

        $avgWorkMinutes = $completedRecords->count() > 0
            ? round($totalWorkMinutes / $completedRecords->count())
            : 0;

        return [
            'total_records'          => $totalRecords,
            'completed_records'      => $completedRecords->count(),
            'currently_clocked_in'   => $currentlyClockedIn,
            'total_work_hours'       => round($totalWorkMinutes / 60, 1),
            'total_break_hours'      => round($totalBreakMinutes / 60, 1),
            'total_overtime_hours'   => round($totalOvertimeMinutes / 60, 1),
            'avg_work_hours'         => round($avgWorkMinutes / 60, 1),
            'on_time_count'          => $onTimeCount,
            'late_count'             => $lateCount,
            'early_departure_count'  => $earlyDepartureCount,
        ];
    }

    // ─── Commissions ────────────────────────────────────────

    public function getCommissionSummary(string $staffUserId, array $filters = []): array
    {
        $query = CommissionEarning::where('staff_user_id', $staffUserId);

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $earnings = $query->get();

        return [
            'total_earnings'   => $earnings->sum('commission_amount'),
            'total_orders'     => $earnings->count(),
            'avg_per_order'    => $earnings->count() > 0
                ? round($earnings->sum('commission_amount') / $earnings->count(), 2)
                : 0,
        ];
    }

    public function setCommissionConfig(StaffUser $staff, array $data): CommissionRule
    {
        return DB::transaction(function () use ($staff, $data) {
            // Deactivate existing rules if replacing
            if (!empty($data['replace_existing'])) {
                CommissionRule::where('staff_user_id', $staff->id)
                    ->update(['is_active' => false]);
            }

            return CommissionRule::create([
                'store_id'            => $staff->store_id,
                'staff_user_id'       => $staff->id,
                'type'                => $data['type'],
                'percentage'          => $data['percentage'],
                'tiers_json'          => $data['tiers_json'] ?? null,
                'product_category_id' => $data['product_category_id'] ?? null,
                'is_active'           => $data['is_active'] ?? true,
            ]);
        });
    }

    // ─── Activity Log ───────────────────────────────────────

    public function getActivityLog(string $staffUserId, int $perPage = 20)
    {
        return StaffActivityLog::where('staff_user_id', $staffUserId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    // ─── Helpers ────────────────────────────────────────────

    private function shiftDurationMinutes(ShiftTemplate $template): int
    {
        $start = \Carbon\Carbon::parse($template->start_time);
        $end   = \Carbon\Carbon::parse($template->end_time);

        // Handle overnight shifts
        if ($end->lt($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    // ─── Branch Assignments ─────────────────────────────────

    public function assignBranch(StaffUser $staff, array $data): StaffBranchAssignment
    {
        // Don't allow duplicate assignment to same branch
        $existing = StaffBranchAssignment::where('staff_user_id', $staff->id)
            ->where('branch_id', $data['branch_id'])
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('Staff is already assigned to this branch.');
        }

        // If this is set as primary, unset other primaries
        if (!empty($data['is_primary'])) {
            StaffBranchAssignment::where('staff_user_id', $staff->id)
                ->update(['is_primary' => false]);
        }

        return StaffBranchAssignment::create([
            'staff_user_id' => $staff->id,
            'branch_id'     => $data['branch_id'],
            'role_id'       => $data['role_id'] ?? null,
            'is_primary'    => $data['is_primary'] ?? false,
        ]);
    }

    public function unassignBranch(StaffUser $staff, string $branchId): bool
    {
        $assignment = StaffBranchAssignment::where('staff_user_id', $staff->id)
            ->where('branch_id', $branchId)
            ->first();

        if (!$assignment) {
            throw new \InvalidArgumentException('Branch assignment not found.');
        }

        return $assignment->delete();
    }

    public function listBranchAssignments(string $staffUserId)
    {
        return StaffBranchAssignment::where('staff_user_id', $staffUserId)
            ->with('branch', 'role')
            ->get();
    }

    // ─── Staff Stats ────────────────────────────────────────

    public function getStats(string $storeId): array
    {
        $total = StaffUser::where('store_id', $storeId)->count();
        $active = StaffUser::where('store_id', $storeId)->where('status', 'active')->count();
        $inactive = StaffUser::where('store_id', $storeId)->where('status', 'inactive')->count();
        $onLeave = StaffUser::where('store_id', $storeId)->where('status', 'on_leave')->count();

        $clockedIn = AttendanceRecord::where('store_id', $storeId)
            ->whereNull('clock_out_at')
            ->count();

        $todayAttendance = AttendanceRecord::where('store_id', $storeId)
            ->whereDate('clock_in_at', now()->toDateString())
            ->count();

        return [
            'total_staff'       => $total,
            'active'            => $active,
            'inactive'          => $inactive,
            'on_leave'          => $onLeave,
            'currently_clocked_in' => $clockedIn,
            'today_attendance'  => $todayAttendance,
        ];
    }

    // ─── Attendance Export ───────────────────────────────────

    public function exportAttendance(string $storeId, array $filters = []): array
    {
        $query = AttendanceRecord::where('store_id', $storeId)
            ->with('staffUser');

        if (!empty($filters['staff_user_id'])) {
            $query->where('staff_user_id', $filters['staff_user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('clock_in_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('clock_in_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $records = $query->orderByDesc('clock_in_at')->get();

        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'staff_name'       => $record->staffUser
                    ? ($record->staffUser->first_name . ' ' . $record->staffUser->last_name)
                    : 'N/A',
                'clock_in'         => $record->clock_in_at?->toDateTimeString(),
                'clock_out'        => $record->clock_out_at?->toDateTimeString(),
                'break_minutes'    => $record->break_minutes ?? 0,
                'overtime_minutes' => $record->overtime_minutes ?? 0,
                'notes'            => $record->notes,
            ];
        }

        return [
            'headers' => ['Staff Name', 'Clock In', 'Clock Out', 'Break (min)', 'Overtime (min)', 'Notes'],
            'rows'    => $rows,
            'total'   => count($rows),
        ];
    }

    // ─── User Account Linking ────────────────────────────────

    /**
     * Link a staff member to a User login account.
     * Both must belong to the same store.
     */
    public function linkUserAccount(StaffUser $staff, string $userId): StaffUser
    {
        $user = User::findOrFail($userId);

        if ($user->store_id !== $staff->store_id) {
            throw new \InvalidArgumentException('User and staff member must belong to the same store.');
        }

        // Check if this user is already linked to another staff member
        $existing = StaffUser::where('user_id', $userId)->first();
        if ($existing && $existing->id !== $staff->id) {
            throw new \InvalidArgumentException('This user account is already linked to another staff member.');
        }

        $staff->update(['user_id' => $userId]);

        return $staff->refresh()->load('user');
    }

    /**
     * Unlink a staff member from their User login account.
     */
    public function unlinkUserAccount(StaffUser $staff): StaffUser
    {
        if (!$staff->user_id) {
            throw new \InvalidArgumentException('This staff member is not linked to any user account.');
        }

        $staff->update(['user_id' => null]);

        return $staff->refresh();
    }
}
