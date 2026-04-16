<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Admin SDK Service Account
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase service account JSON key file. Can be absolute or
    | relative to base_path().
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', 'wameedpos-firebase-adminsdk.json'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    */
    'project_id' => env('FIREBASE_PROJECT_ID', 'wameedpos'),
];
