<?php

return [
    'url' => env('SUPABASE_URL'),
    'key' => env('SUPABASE_KEY'),
    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'jwt_secret' => env('SUPABASE_JWT_SECRET'),
    'project_id' => env('SUPABASE_PROJECT_ID'),
    'storage_bucket' => env('SUPABASE_STORAGE_BUCKET', 'POS'),
];
