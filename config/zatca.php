<?php

return [
    'environment' => env('ZATCA_ENVIRONMENT', 'sandbox'),
    'compliance_csid' => env('ZATCA_COMPLIANCE_CSID'),
    'production_csid' => env('ZATCA_PRODUCTION_CSID'),
    'private_key_path' => env('ZATCA_PRIVATE_KEY_PATH'),
    'certificate_path' => env('ZATCA_CERTIFICATE_PATH'),
    'api_url' => env('ZATCA_API_URL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal'),
];
