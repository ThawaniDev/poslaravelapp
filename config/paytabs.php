<?php

return [

    'profile_id' => env('paytabs_profile_id', null),
    'server_key' => env('paytabs_server_key', null),
    'currency'   => env('paytabs_currency', 'SAR'),
    'region'     => env('paytabs_region', 'SAU'),

    /*
    |--------------------------------------------------------------------------
    | IPN Callback
    |--------------------------------------------------------------------------
    |
    | The class that will handle the IPN (Instant Payment Notification) from
    | PayTabs. Must implement the updateCartByIPN($requestData) method.
    |
    */
    'callback' => env('paytabs_ipn_callback', null),

];
