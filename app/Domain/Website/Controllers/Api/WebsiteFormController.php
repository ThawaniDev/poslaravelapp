<?php

namespace App\Domain\Website\Controllers\Api;

use App\Domain\Website\Models\WebsiteContactSubmission;
use App\Domain\Website\Models\WebsiteConsultationRequest;
use App\Domain\Website\Models\WebsiteHardwareQuote;
use App\Domain\Website\Models\WebsiteNewsletterSubscriber;
use App\Domain\Website\Models\WebsitePartnershipApplication;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteFormController extends BaseApiController
{
    // ═══════════════════════════════════════════════════════════
    //  1. Contact / Demo Request
    // ═══════════════════════════════════════════════════════════

    public function submitContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'store_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'store_type' => 'nullable|string|in:grocery,restaurant,pharmacy,bakery,electronics,florist,jewelry,fashion',
            'branches' => 'nullable|string|in:1,2-5,6-20,20+',
            'message' => 'nullable|string|max:5000',
            'source_page' => 'nullable|string|in:contact,home,pricing,features,about,zatca,hardware,integrations,compliance',
            'selected_plan' => 'nullable|string|in:starter,growth,pro,enterprise',
            'inquiry_type' => 'nullable|string|in:demo_request,general,zatca,trial,hardware,integration,softpos,compliance',
        ]);

        $submission = WebsiteContactSubmission::create([
            ...$validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->created([
            'reference_number' => $submission->reference_number,
        ], 'Your request has been submitted successfully. Our team will contact you within 24 hours.');
    }

    // ═══════════════════════════════════════════════════════════
    //  2. Newsletter Subscription
    // ═══════════════════════════════════════════════════════════

    public function subscribeNewsletter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'source_page' => 'nullable|string|max:100',
        ]);

        $existing = WebsiteNewsletterSubscriber::where('email', $validated['email'])->first();

        if ($existing) {
            if ($existing->status->value === 'unsubscribed') {
                $existing->update([
                    'status' => 'active',
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                ]);

                return $this->success(null, 'Welcome back! You have been re-subscribed successfully.');
            }

            return $this->success(null, 'You are already subscribed to our newsletter.');
        }

        WebsiteNewsletterSubscriber::create([
            ...$validated,
            'ip_address' => $request->ip(),
            'subscribed_at' => now(),
        ]);

        return $this->created(null, 'You have been subscribed to our newsletter successfully.');
    }

    public function unsubscribeNewsletter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = WebsiteNewsletterSubscriber::where('email', $validated['email'])->first();

        if (!$subscriber) {
            return $this->notFound('Email not found in our subscriber list.');
        }

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return $this->success(null, 'You have been unsubscribed successfully.');
    }

    // ═══════════════════════════════════════════════════════════
    //  3. Partnership Application
    // ═══════════════════════════════════════════════════════════

    public function submitPartnership(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'partnership_type' => 'required|string|in:delivery_platform,payment_provider,developer,reseller,technology,other',
            'website' => 'nullable|url|max:500',
            'message' => 'nullable|string|max:5000',
        ]);

        $application = WebsitePartnershipApplication::create([
            ...$validated,
            'ip_address' => $request->ip(),
        ]);

        return $this->created([
            'reference_number' => $application->reference_number,
        ], 'Your partnership application has been submitted. Our partnerships team will review it shortly.');
    }

    // ═══════════════════════════════════════════════════════════
    //  4. Hardware Quote Request
    // ═══════════════════════════════════════════════════════════

    public function submitHardwareQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'hardware_bundle' => 'nullable|string|in:starter_kit,pro_bundle,enterprise_suite,custom',
            'terminal_quantity' => 'nullable|integer|min:1|max:999',
            'needs_printer' => 'nullable|boolean',
            'needs_scanner' => 'nullable|boolean',
            'needs_cash_drawer' => 'nullable|boolean',
            'needs_payment_terminal' => 'nullable|boolean',
            'message' => 'nullable|string|max:5000',
        ]);

        $quote = WebsiteHardwareQuote::create([
            ...$validated,
            'ip_address' => $request->ip(),
        ]);

        return $this->created([
            'reference_number' => $quote->reference_number,
        ], 'Your hardware quote request has been submitted. Our hardware team will prepare your quote shortly.');
    }

    // ═══════════════════════════════════════════════════════════
    //  5. Consultation Request (ZATCA / Compliance)
    // ═══════════════════════════════════════════════════════════

    public function submitConsultation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'cr_number' => 'nullable|string|max:30',
            'vat_number' => 'nullable|string|max:30',
            'current_pos_system' => 'nullable|string|max:255',
            'consultation_type' => 'required|string|in:zatca_phase2,compliance_audit,pos_migration,general',
            'branches' => 'nullable|string|in:1,2-5,6-20,20+',
            'message' => 'nullable|string|max:5000',
        ]);

        $consultation = WebsiteConsultationRequest::create([
            ...$validated,
            'ip_address' => $request->ip(),
        ]);

        return $this->created([
            'reference_number' => $consultation->reference_number,
        ], 'Your consultation request has been submitted. Our compliance specialist will contact you shortly.');
    }
}
