<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }
        // model_has_roles: change model_id from bigint → uuid
        DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT model_has_roles_pkey');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE uuid USING model_id::text::uuid');
        DB::statement('ALTER TABLE model_has_roles ADD PRIMARY KEY (role_id, model_id, model_type)');

        // model_has_permissions: change model_id from bigint → uuid
        DB::statement('ALTER TABLE model_has_permissions DROP CONSTRAINT model_has_permissions_pkey');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE uuid USING model_id::text::uuid');
        DB::statement('ALTER TABLE model_has_permissions ADD PRIMARY KEY (permission_id, model_id, model_type)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT IF EXISTS model_has_roles_pkey');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE bigint USING model_id::text::bigint');
        DB::statement('ALTER TABLE model_has_roles ADD PRIMARY KEY (role_id, model_id, model_type)');

        DB::statement('ALTER TABLE model_has_permissions DROP CONSTRAINT IF EXISTS model_has_permissions_pkey');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE bigint USING model_id::text::bigint');
        DB::statement('ALTER TABLE model_has_permissions ADD PRIMARY KEY (permission_id, model_id, model_type)');
    }
};
