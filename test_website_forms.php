<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Website Form Models ===\n\n";

// Clean up any leftover test data first
\App\Domain\Website\Models\WebsiteContactSubmission::where('email', 'test@example.com')->delete();
\App\Domain\Website\Models\WebsiteNewsletterSubscriber::where('email', 'newsletter@test.com')->delete();
\App\Domain\Website\Models\WebsitePartnershipApplication::where('email', 'partner@test.com')->delete();
\App\Domain\Website\Models\WebsiteHardwareQuote::where('email', 'hw@test.com')->delete();
\App\Domain\Website\Models\WebsiteConsultationRequest::where('email', 'consult@test.com')->delete();

// 1. Contact Submission
$submission = \App\Domain\Website\Models\WebsiteContactSubmission::create([
    'full_name' => 'Test User',
    'store_name' => 'Test Store',
    'phone' => '0512345678',
    'email' => 'test@example.com',
    'store_type' => 'coffee',
    'branches' => '1',
    'message' => 'Testing contact form',
    'source_page' => 'contact',
    'inquiry_type' => 'demo_request',
    'ip_address' => '127.0.0.1',
]);
echo "✅ Contact: {$submission->reference_number} (status: {$submission->status->value})\n";

// 2. Newsletter
$newsletter = \App\Domain\Website\Models\WebsiteNewsletterSubscriber::create([
    'email' => 'newsletter@test.com',
    'source_page' => 'footer',
    'ip_address' => '127.0.0.1',
]);
echo "✅ Newsletter: {$newsletter->email} (status: {$newsletter->status->value})\n";

// 3. Partnership
$partner = \App\Domain\Website\Models\WebsitePartnershipApplication::create([
    'company_name' => 'Test Co',
    'contact_name' => 'Partner Test',
    'email' => 'partner@test.com',
    'phone' => '0512345678',
    'partnership_type' => 'developer',
    'ip_address' => '127.0.0.1',
]);
echo "✅ Partnership: {$partner->reference_number} (status: {$partner->status->value})\n";

// 4. Hardware Quote
$hw = \App\Domain\Website\Models\WebsiteHardwareQuote::create([
    'full_name' => 'HW User',
    'email' => 'hw@test.com',
    'phone' => '0512345678',
    'hardware_bundle' => 'pro_bundle',
    'terminal_quantity' => 3,
    'needs_printer' => true,
    'needs_scanner' => true,
    'ip_address' => '127.0.0.1',
]);
echo "✅ Hardware: {$hw->reference_number} (status: {$hw->status->value})\n";

// 5. Consultation
$consult = \App\Domain\Website\Models\WebsiteConsultationRequest::create([
    'full_name' => 'Consult User',
    'email' => 'consult@test.com',
    'phone' => '0512345678',
    'consultation_type' => 'zatca_phase2',
    'branches' => '2-5',
    'ip_address' => '127.0.0.1',
]);
echo "✅ Consultation: {$consult->reference_number} (status: {$consult->status->value})\n";

echo "\n=== All 5 models created and verified! ===\n";

// Verify counts
echo "\nDB counts:\n";
echo "  Contact submissions: " . \App\Domain\Website\Models\WebsiteContactSubmission::count() . "\n";
echo "  Newsletter subscribers: " . \App\Domain\Website\Models\WebsiteNewsletterSubscriber::count() . "\n";
echo "  Partnership applications: " . \App\Domain\Website\Models\WebsitePartnershipApplication::count() . "\n";
echo "  Hardware quotes: " . \App\Domain\Website\Models\WebsiteHardwareQuote::count() . "\n";
echo "  Consultation requests: " . \App\Domain\Website\Models\WebsiteConsultationRequest::count() . "\n";

// Clean up test data
\App\Domain\Website\Models\WebsiteContactSubmission::where('email', 'test@example.com')->delete();
\App\Domain\Website\Models\WebsiteNewsletterSubscriber::where('email', 'newsletter@test.com')->delete();
\App\Domain\Website\Models\WebsitePartnershipApplication::where('email', 'partner@test.com')->delete();
\App\Domain\Website\Models\WebsiteHardwareQuote::where('email', 'hw@test.com')->delete();
\App\Domain\Website\Models\WebsiteConsultationRequest::where('email', 'consult@test.com')->delete();

echo "\n🧹 Test data cleaned up.\n";
