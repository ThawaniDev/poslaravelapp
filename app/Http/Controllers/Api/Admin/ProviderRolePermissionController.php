<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\ProviderRegistration\Models\ProviderPermission;
use App\Domain\StaffManagement\Models\DefaultRoleTemplate;
use App\Domain\StaffManagement\Models\DefaultRoleTemplatePermission;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderRolePermissionController extends BaseApiController
{
    // ── Provider Permissions ─────────────────────────────────
    public function permissions(Request $request): JsonResponse
    {
        $q = ProviderPermission::query();

        if ($request->filled('group')) {
            $q->where('group', $request->group);
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return $this->success(
            $q->orderBy('group')->orderBy('name')->paginate($request->integer('per_page', 50)),
            'Provider permissions retrieved'
        );
    }

    // ── Default Role Templates ───────────────────────────────
    public function templates(Request $request): JsonResponse
    {
        $q = DefaultRoleTemplate::query()->latest('updated_at');

        return $this->success(
            $q->paginate($request->integer('per_page', 15)),
            'Default role templates retrieved'
        );
    }

    public function showTemplate(string $id): JsonResponse
    {
        $template = DefaultRoleTemplate::with('defaultRoleTemplatePermissions.providerPermission')
            ->findOrFail($id);

        return $this->success($template, 'Role template details');
    }

    public function createTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:50',
            'name_ar'        => 'nullable|string|max:50',
            'slug'           => 'required|string|max:30|unique:default_role_templates,slug',
            'description'    => 'nullable|string|max:255',
            'description_ar' => 'nullable|string|max:255',
        ]);

        $template = DefaultRoleTemplate::create($request->only([
            'name', 'name_ar', 'slug', 'description', 'description_ar',
        ]));

        return $this->created($template, 'Role template created');
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        $template = DefaultRoleTemplate::findOrFail($id);

        $request->validate([
            'name'           => 'sometimes|string|max:50',
            'name_ar'        => 'nullable|string|max:50',
            'slug'           => "sometimes|string|max:30|unique:default_role_templates,slug,{$id}",
            'description'    => 'nullable|string|max:255',
            'description_ar' => 'nullable|string|max:255',
        ]);

        $template->update($request->only([
            'name', 'name_ar', 'slug', 'description', 'description_ar',
        ]));

        return $this->success($template, 'Role template updated');
    }

    public function deleteTemplate(string $id): JsonResponse
    {
        $template = DefaultRoleTemplate::findOrFail($id);

        // Delete associated template permissions first
        DefaultRoleTemplatePermission::where('default_role_template_id', $id)->delete();
        $template->delete();

        return $this->success(null, 'Role template deleted');
    }

    // ── Template Permission Assignment ───────────────────────
    public function templatePermissions(string $templateId): JsonResponse
    {
        DefaultRoleTemplate::findOrFail($templateId);

        $permissions = DefaultRoleTemplatePermission::where('default_role_template_id', $templateId)
            ->with('providerPermission')
            ->get();

        return $this->success($permissions, 'Template permissions retrieved');
    }

    public function updateTemplatePermissions(Request $request, string $templateId): JsonResponse
    {
        DefaultRoleTemplate::findOrFail($templateId);

        $request->validate([
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'uuid|exists:provider_permissions,id',
        ]);

        // Sync permissions: delete all existing and recreate
        DefaultRoleTemplatePermission::where('default_role_template_id', $templateId)->delete();

        foreach ($request->permission_ids as $permId) {
            DefaultRoleTemplatePermission::create([
                'default_role_template_id' => $templateId,
                'provider_permission_id'   => $permId,
            ]);
        }

        $permissions = DefaultRoleTemplatePermission::where('default_role_template_id', $templateId)
            ->with('providerPermission')
            ->get();

        return $this->success($permissions, 'Template permissions updated');
    }
}
