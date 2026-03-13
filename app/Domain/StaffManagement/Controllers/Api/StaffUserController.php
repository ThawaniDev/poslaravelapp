<?php

namespace App\Domain\StaffManagement\Controllers\Api;

use App\Domain\StaffManagement\Requests\ClockRequest;
use App\Domain\StaffManagement\Requests\CreateShiftRequest;
use App\Domain\StaffManagement\Requests\CreateStaffRequest;
use App\Domain\StaffManagement\Requests\UpdateStaffRequest;
use App\Domain\StaffManagement\Resources\AttendanceRecordResource;
use App\Domain\StaffManagement\Resources\ShiftScheduleResource;
use App\Domain\StaffManagement\Resources\ShiftTemplateResource;
use App\Domain\StaffManagement\Resources\StaffActivityLogResource;
use App\Domain\StaffManagement\Resources\StaffUserResource;
use App\Domain\StaffManagement\Services\StaffService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class StaffUserController extends BaseApiController
{
    public function __construct(
        private readonly StaffService $staffService,
    ) {}

    // ─── Staff CRUD ──────────────────────────────────────────

    public function index(Request $request)
    {
        $storeId = $request->user()->store_id;

        $result = $this->staffService->list($storeId, $request->only([
            'search', 'status', 'employment_type', 'per_page',
        ]));

        return $this->success(StaffUserResource::collection($result));
    }

    public function store(CreateStaffRequest $request)
    {
        $data = $request->validated();

        // Ensure store_id belongs to user's org
        $staff = $this->staffService->create($data);

        return $this->created(new StaffUserResource($staff));
    }

    public function show(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        return $this->success(new StaffUserResource($staff));
    }

    public function update(UpdateStaffRequest $request, string $id)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $updated = $this->staffService->update($staff, $request->validated());

        return $this->success(new StaffUserResource($updated));
    }

    public function destroy(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $this->staffService->delete($staff);

        return $this->success(null, 'Staff deleted');
    }

    public function setPin(string $id, Request $request)
    {
        $request->validate(['pin' => 'required|string|min:4|max:8']);

        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $this->staffService->setPin($staff, $request->pin);

        return $this->success(null, 'PIN updated');
    }

    public function registerNfc(string $id, Request $request)
    {
        $request->validate(['nfc_badge_uid' => 'required|string|max:100']);

        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $updated = $this->staffService->registerNfc($staff, $request->nfc_badge_uid);

        return $this->success(new StaffUserResource($updated));
    }

    // ─── Attendance ─────────────────────────────────────────

    public function attendance(Request $request)
    {
        $storeId = $request->user()->store_id;

        $result = $this->staffService->listAttendance($storeId, $request->only([
            'staff_user_id', 'date_from', 'date_to', 'per_page',
        ]));

        return $this->success(AttendanceRecordResource::collection($result));
    }

    public function clock(ClockRequest $request)
    {
        $data = $request->validated();

        try {
            $result = match ($data['action']) {
                'clock_in'    => $this->staffService->clockIn($data['staff_user_id'], $data['store_id'], $data['notes'] ?? null),
                'clock_out'   => $this->staffService->clockOut($data['staff_user_id'], $data['store_id'], $data['notes'] ?? null),
                'start_break' => $this->staffService->startBreak($data['attendance_record_id']),
                'end_break'   => $this->staffService->endBreak($data['attendance_record_id']),
            };

            $resource = $data['action'] === 'clock_in' || $data['action'] === 'clock_out'
                ? new AttendanceRecordResource($result)
                : $result;

            return $this->success($resource, ucfirst(str_replace('_', ' ', $data['action'])) . ' successful');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Shifts ─────────────────────────────────────────────

    public function shifts(Request $request)
    {
        $storeId = $request->user()->store_id;

        $result = $this->staffService->listShifts($storeId, $request->only([
            'staff_user_id', 'date_from', 'date_to', 'status', 'per_page',
        ]));

        return $this->success(ShiftScheduleResource::collection($result));
    }

    public function storeShift(CreateShiftRequest $request)
    {
        try {
            $shift = $this->staffService->createShift($request->validated());
            return $this->created(new ShiftScheduleResource($shift));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function updateShift(Request $request, string $id)
    {
        $shift = \App\Domain\StaffManagement\Models\ShiftSchedule::findOrFail($id);

        if ($shift->store_id !== $request->user()->store_id) {
            return $this->notFound('Shift not found');
        }

        $updated = $this->staffService->updateShift($shift, $request->only([
            'shift_template_id', 'date', 'status',
        ]));

        return $this->success(new ShiftScheduleResource($updated));
    }

    public function destroyShift(string $id, Request $request)
    {
        $shift = \App\Domain\StaffManagement\Models\ShiftSchedule::findOrFail($id);

        if ($shift->store_id !== $request->user()->store_id) {
            return $this->notFound('Shift not found');
        }

        $this->staffService->deleteShift($shift);

        return $this->success(null, 'Shift deleted');
    }

    // ─── Shift Templates ────────────────────────────────────

    public function shiftTemplates(Request $request)
    {
        $storeId = $request->user()->store_id;
        $templates = $this->staffService->listShiftTemplates($storeId);

        return $this->success(ShiftTemplateResource::collection($templates));
    }

    public function storeShiftTemplate(Request $request)
    {
        $data = $request->validate([
            'store_id'   => 'required|uuid|exists:stores,id',
            'name'       => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'color'      => 'nullable|string|max:7',
        ]);

        $template = $this->staffService->createShiftTemplate($data);

        return $this->created(new ShiftTemplateResource($template));
    }

    // ─── Commissions ────────────────────────────────────────

    public function commissions(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $summary = $this->staffService->getCommissionSummary($id, $request->only([
            'date_from', 'date_to',
        ]));

        return $this->success($summary);
    }

    public function setCommissionConfig(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $data = $request->validate([
            'type'                => 'required|string',
            'percentage'          => 'required|numeric|min:0|max:100',
            'tiers_json'          => 'nullable|array',
            'product_category_id' => 'nullable|uuid',
            'is_active'           => 'nullable|boolean',
            'replace_existing'    => 'nullable|boolean',
        ]);

        $rule = $this->staffService->setCommissionConfig($staff, $data);

        return $this->created(new \App\Domain\StaffManagement\Resources\CommissionRuleResource($rule));
    }

    // ─── Activity Log ───────────────────────────────────────

    public function activityLog(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $logs = $this->staffService->getActivityLog($id, $request->integer('per_page', 20));

        return $this->success(StaffActivityLogResource::collection($logs));
    }
}
