<?php

namespace App\Domain\StaffManagement\Services;

use App\Domain\StaffManagement\Models\StaffUser;
use App\Domain\StaffManagement\Models\AttendanceRecord;
use App\Domain\StaffManagement\Models\BreakRecord;
use App\Domain\StaffManagement\Models\ShiftSchedule;
use App\Domain\StaffManagement\Models\ShiftTemplate;
use App\Domain\StaffManagement\Models\CommissionRule;
use App\Domain\StaffManagement\Models\CommissionEarning;
use App\Domain\StaffManagement\Models\StaffActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffService
{
    // ─── Staff CRUD ──────────────────────────────────────────

    public function list(string $storeId, array $filters = [])
    {
        $query = StaffUser::where('store_id', $storeId);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
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

            return StaffUser::create($data);
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
            $query->where('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->orderBy('date')->paginate($perPage);
    }

    public function createShift(array $data): ShiftSchedule
    {
        // Check for conflicts
        $conflict = ShiftSchedule::where('store_id', $data['store_id'])
            ->where('staff_user_id', $data['staff_user_id'])
            ->whereDate('date', $data['date'])
            ->exists();

        if ($conflict) {
            throw new \InvalidArgumentException('Staff already has a shift on this date.');
        }

        return ShiftSchedule::create($data);
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
        return $template->delete();
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
}
