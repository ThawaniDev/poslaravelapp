<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'customers.manage_receivables'],
            [
                'display_name' => 'Manage Customer Receivables',
                'display_name_ar' => 'إدارة مستحقات العملاء',
                'module' => 'customers',
                'guard_name' => 'staff',
                'requires_pin' => false,
                'description' => 'Manage receivables owed by customers',
                'description_ar' => 'إدارة الأموال المستحقة من العملاء',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $permissionId = DB::table('permissions')->where('name', 'customers.manage_receivables')->value('id');

        if (! $permissionId) {
            return;
        }

        // Auto-grant to Owner role(s)
        $ownerRoleIds = DB::table('roles')->where('name', 'owner')->pluck('id');

        foreach ($ownerRoleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['role_id' => $roleId, 'permission_id' => $permissionId],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'customers.manage_receivables')->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
