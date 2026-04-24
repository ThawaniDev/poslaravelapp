<?php

/*
|--------------------------------------------------------------------------
| ZATCA / Fatoora Phase 2 configuration
|--------------------------------------------------------------------------
|
| ZATCA exposes three logical environments. Pick ONE per deployment:
|
|   sandbox     -> developer portal (mock, free, no real submissions)
|   simulation  -> Fatoora Simulation portal (end-to-end pre-prod testing)
|   production  -> Fatoora Portal (real, legally binding submissions)
|
| The base URLs and CSR `certificateTemplateName` differ per environment.
| This file derives the right URL/template from ZATCA_ENVIRONMENT, but
| ZATCA_API_URL can still override if ZATCA changes hostnames.
|
*/

$environment = env('ZATCA_ENVIRONMENT', 'sandbox');

$urls = [
    'sandbox'    => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
    'simulation' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
    'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
];

// Per the Fatoora User Manual the CSR must carry a specific
// certificateTemplateName extension or ZATCA will reject the CSID request.
$csrTemplates = [
    'sandbox'    => 'PREZATCA-Code-Signing',
    'simulation' => 'PREZATCA-Code-Signing',
    'production' => 'ZATCA-Code-Signing',
];

return [
    'environment' => $environment,
    'api_url' => env('ZATCA_API_URL', $urls[$environment] ?? $urls['sandbox']),
    'csr_template' => env('ZATCA_CSR_TEMPLATE', $csrTemplates[$environment] ?? $csrTemplates['sandbox']),

    // Optional fallback material for legacy / single-tenant installs;
    // multi-tenant uses zatca_certificates per-store and ignores these.
    'compliance_csid' => env('ZATCA_COMPLIANCE_CSID'),
    'production_csid' => env('ZATCA_PRODUCTION_CSID'),
    'private_key_path' => env('ZATCA_PRIVATE_KEY_PATH'),
    'certificate_path' => env('ZATCA_CERTIFICATE_PATH'),

    // HTTP behaviour against the ZATCA gateway.
    'http_timeout' => (int) env('ZATCA_HTTP_TIMEOUT', 15),
    'http_retries' => (int) env('ZATCA_HTTP_RETRIES', 2),
];

