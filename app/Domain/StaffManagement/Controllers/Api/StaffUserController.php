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
use App\Domain\StaffManagement\Resources\StaffBranchAssignmentResource;
use App\Domain\StaffManagement\Resources\StaffUserResource;
use App\Domain\StaffManagement\Services\StaffService;
use App\Domain\Subscription\Traits\TracksSubscriptionUsage;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class StaffUserController extends BaseApiController
{
    use TracksSubscriptionUsage;
    public function __construct(
        private readonly StaffService $staffService,
    ) {}

    // ─── Staff CRUD ──────────────────────────────────────────

    public function index(Request $request)
    {
        // Allow filtering by store_id; default to the authenticated user's store
        $storeId = $request->input('store_id', $request->user()->store_id);

        // Ensure the requested store belongs to the same organization
        $userOrgId = $request->user()->organization_id;
        if ($storeId !== $request->user()->store_id) {
            $store = \App\Domain\Core\Models\Store::find($storeId);
            if (!$store || $store->organization_id !== $userOrgId) {
                return $this->error('Store not found in your organization', 403);
            }
        }

        $result = $this->staffService->list($storeId, $request->only([
            'search', 'status', 'employment_type', 'per_page',
        ]));

        return $this->successPaginated(StaffUserResource::collection($result), $result);
    }

    public function store(CreateStaffRequest $request)
    {
        $data = $request->validated();

        // Ensure store_id belongs to user's org
        $staff = $this->staffService->create($data);

        // Refresh staff usage snapshot after creation
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $this->refreshUsageFor($orgId, 'staff_members');
        }

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

        // Refresh staff usage snapshot after deletion
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $this->refreshUsageFor($orgId, 'staff_members');
        }

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

        return $this->successPaginated(AttendanceRecordResource::collection($result), $result);
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

        return $this->successPaginated(ShiftScheduleResource::collection($result), $result);
    }

    public function storeShift(CreateShiftRequest $request)
    {
        try {
            $shift = $this->staffService->createShift($request->validated());
            return $this->created(new ShiftScheduleResource($shift->load('staffUser', 'shiftTemplate')));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function bulkStoreShift(Request $request)
    {
        $data = $request->validate([
            'store_id'          => 'required|uuid|exists:stores,id',
            'staff_user_ids'    => 'required|array|min:1',
            'staff_user_ids.*'  => 'uuid|exists:staff_users,id',
            'shift_template_id' => 'required|uuid|exists:shift_templates,id',
            'dates'             => 'required|array|min:1',
            'dates.*'           => 'date',
            'notes'             => 'nullable|string|max:500',
        ]);

        try {
            $shifts = $this->staffService->bulkCreateShifts($data);
            return $this->success(
                ShiftScheduleResource::collection(
                    collect($shifts)->load('staffUser', 'shiftTemplate')
                ),
                count($shifts) . ' shifts created'
            );
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
            'store_id'               => 'required|uuid|exists:stores,id',
            'name'                   => 'required|string|max:100',
            'start_time'             => 'required|date_format:H:i',
            'end_time'               => 'required|date_format:H:i',
            'break_duration_minutes' => 'nullable|integer|min:0',
            'color'                  => 'nullable|string|max:7',
        ]);

        $template = $this->staffService->createShiftTemplate($data);

        return $this->created(new ShiftTemplateResource($template));
    }

    public function updateShiftTemplate(Request $request, string $id)
    {
        $template = \App\Domain\StaffManagement\Models\ShiftTemplate::findOrFail($id);

        if ($template->store_id !== $request->user()->store_id) {
            return $this->notFound('Template not found');
        }

        $data = $request->validate([
            'name'                   => 'sometimes|string|max:100',
            'start_time'             => 'sometimes|date_format:H:i',
            'end_time'               => 'sometimes|date_format:H:i',
            'break_duration_minutes' => 'nullable|integer|min:0',
            'color'                  => 'nullable|string|max:7',
            'is_active'              => 'nullable|boolean',
        ]);

        $updated = $this->staffService->updateShiftTemplate($template, $data);

        return $this->success(new ShiftTemplateResource($updated));
    }

    public function destroyShiftTemplate(Request $request, string $id)
    {
        $template = \App\Domain\StaffManagement\Models\ShiftTemplate::findOrFail($id);

        if ($template->store_id !== $request->user()->store_id) {
            return $this->notFound('Template not found');
        }

        try {
            $this->staffService->deleteShiftTemplate($template);
            return $this->success(null, 'Shift template deleted');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Attendance Summary ─────────────────────────────────

    public function attendanceSummary(Request $request)
    {
        $storeId = $request->user()->store_id;

        $summary = $this->staffService->getAttendanceSummary($storeId, $request->only([
            'staff_user_id', 'date_from', 'date_to',
        ]));

        return $this->success($summary);
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

        return $this->successPaginated(StaffActivityLogResource::collection($logs), $logs);
    }

    // ─── Branch Assignments ─────────────────────────────────

    public function branchAssignments(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $assignments = $this->staffService->listBranchAssignments($id);

        return $this->success(StaffBranchAssignmentResource::collection($assignments));
    }

    public function assignBranch(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $data = $request->validate([
            'branch_id'  => 'required|uuid|exists:stores,id',
            'role_id'    => 'nullable|integer|exists:roles,id',
            'is_primary' => 'nullable|boolean',
        ]);

        try {
            $assignment = $this->staffService->assignBranch($staff, $data);
            return $this->created(new StaffBranchAssignmentResource($assignment));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function unassignBranch(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $data = $request->validate([
            'branch_id' => 'required|uuid|exists:stores,id',
        ]);

        try {
            $this->staffService->unassignBranch($staff, $data['branch_id']);
            return $this->success(null, 'Branch assignment removed');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Staff Stats ────────────────────────────────────────

    public function stats(Request $request)
    {
        $storeId = $request->user()->store_id;
        $stats = $this->staffService->getStats($storeId);
        return $this->success($stats);
    }

    // ─── Attendance Export ───────────────────────────────────

    public function attendanceExport(Request $request)
    {
        $storeId = $request->user()->store_id;

        $data = $this->staffService->exportAttendance($storeId, $request->only([
            'staff_user_id', 'date_from', 'date_to',
        ]));

        return $this->success($data);
    }

    // ─── User Account Linking ────────────────────────────────

    public function linkUser(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        try {
            $linked = $this->staffService->linkUserAccount($staff, $data['user_id']);
            return $this->success(new StaffUserResource($linked), 'User account linked');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function unlinkUser(string $id, Request $request)
    {
        $staff = $this->staffService->find($id);

        if ($staff->store_id !== $request->user()->store_id) {
            return $this->notFound('Staff not found');
        }

        try {
            $unlinked = $this->staffService->unlinkUserAccount($staff);
            return $this->success(new StaffUserResource($unlinked), 'User account unlinked');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function linkableUsers(Request $request)
    {
        $storeId = $request->user()->store_id;

        $users = \App\Domain\Auth\Models\User::where('store_id', $storeId)
            ->whereDoesntHave('staffUser')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return $this->success($users);
    }
}
