<?php

namespace Tests\Feature\Comprehensive;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebsiteFormsApiTest extends TestCase
{
    use RefreshDatabase;

    // No auth needed - these are public endpoints

    // ═══════════════════════════════════════════════════════
    // ─── CONTACT / DEMO REQUEST ─────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_submit_contact_form(): void
    {
        $response = $this->postJson('/api/v2/website/contact', [
            'full_name' => 'Ahmed Khan',
            'store_name' => 'Khan Grocery',
            'phone' => '+96812345678',
            'email' => 'ahmed@khan.com',
            'store_type' => 'grocery',
            'branches' => '2-5',
            'message' => 'Interested in POS system',
            'inquiry_type' => 'demo_request',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('website_contact_submissions', [
            'email' => 'ahmed@khan.com',
            'store_name' => 'Khan Grocery',
        ]);
    }

    public function test_contact_requires_name_store_phone_email(): void
    {
        $response = $this->postJson('/api/v2/website/contact', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['full_name', 'store_name', 'phone', 'email']);
    }

    public function test_contact_validates_store_type(): void
    {
        $response = $this->postJson('/api/v2/website/contact', [
            'full_name' => 'Test',
            'store_name' => 'Test Store',
            'phone' => '123456',
            'email' => 'test@test.com',
            'store_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['store_type']);
    }

    public function test_contact_validates_inquiry_type(): void
    {
        $response = $this->postJson('/api/v2/website/contact', [
            'full_name' => 'Test',
            'store_name' => 'Test Store',
            'phone' => '123456',
            'email' => 'test@test.com',
            'inquiry_type' => 'hackattempt',
        ]);

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════
    // ─── NEWSLETTER ─────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_subscribe_to_newsletter(): void
    {
        $response = $this->postJson('/api/v2/website/newsletter/subscribe', [
            'email' => 'subscriber@example.com',
            'source_page' => 'home',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('website_newsletter_subscribers', [
            'email' => 'subscriber@example.com',
        ]);
    }

    public function test_subscribe_requires_email(): void
    {
        $response = $this->postJson('/api/v2/website/newsletter/subscribe', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_duplicate_subscribe_returns_success(): void
    {
        // First subscribe
        $this->postJson('/api/v2/website/newsletter/subscribe', [
            'email' => 'dup@example.com',
        ]);

        // Duplicate
        $response = $this->postJson('/api/v2/website/newsletter/subscribe', [
            'email' => 'dup@example.com',
        ]);

        $response->assertOk();
    }

    public function test_can_unsubscribe_from_newsletter(): void
    {
        // Subscribe first
        $this->postJson('/api/v2/website/newsletter/subscribe', [
            'email' => 'unsub@example.com',
        ]);

        // Unsubscribe
        $response = $this->postJson('/api/v2/website/newsletter/unsubscribe', [
            'email' => 'unsub@example.com',
        ]);

        $response->assertOk();
    }

    public function test_unsubscribe_404_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v2/website/newsletter/unsubscribe', [
            'email' => 'noone@nowhere.com',
        ]);

        $response->assertNotFound();
    }

    public function test_resubscribe_after_unsubscribe(): void
    {
        // Subscribe
        $this->postJson('/api/v2/website/newsletter/subscribe', [
            'email' => 'resub@example.com',
        ]);

        // Unsubscribe
        $this->postJson('/api/v2/website/newsletter/unsubscribe', [
            'email' => 'resub@example.com',
        ]);

        // Re-subscribe
        $response = $this->postJson('/api/v2/website/newsletter/subscribe', [
            'email' => 'resub@example.com',
        ]);

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════
    // ─── PARTNERSHIP ────────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_submit_partnership_application(): void
    {
        $response = $this->postJson('/api/v2/website/partnership', [
            'company_name' => 'DeliverCo',
            'contact_name' => 'Sara Ahmed',
            'email' => 'sara@deliverco.com',
            'phone' => '+96898765432',
            'partnership_type' => 'delivery_platform',
            'website' => 'https://deliverco.com',
            'message' => 'We want to integrate',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('website_partnership_applications', [
            'email' => 'sara@deliverco.com',
        ]);
    }

    public function test_partnership_requires_fields(): void
    {
        $response = $this->postJson('/api/v2/website/partnership', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['company_name', 'contact_name', 'email', 'phone', 'partnership_type']);
    }

    public function test_partnership_validates_type(): void
    {
        $response = $this->postJson('/api/v2/website/partnership', [
            'company_name' => 'Test',
            'contact_name' => 'Test',
            'email' => 'test@test.com',
            'phone' => '123',
            'partnership_type' => 'invalid',
        ]);

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════
    // ─── HARDWARE QUOTE ─────────────────────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_submit_hardware_quote(): void
    {
        $response = $this->postJson('/api/v2/website/hardware-quote', [
            'full_name' => 'Khalid Omar',
            'business_name' => 'Omar Electronics',
            'email' => 'khalid@omar.com',
            'phone' => '+96811111111',
            'hardware_bundle' => 'pro_bundle',
            'terminal_quantity' => 3,
            'needs_printer' => true,
            'needs_scanner' => true,
            'needs_cash_drawer' => false,
            'needs_payment_terminal' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
    }

    public function test_hardware_quote_requires_fields(): void
    {
        $response = $this->postJson('/api/v2/website/hardware-quote', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['full_name', 'business_name', 'email', 'phone']);
    }

    public function test_hardware_quote_validates_bundle(): void
    {
        $response = $this->postJson('/api/v2/website/hardware-quote', [
            'full_name' => 'Test',
            'business_name' => 'Test',
            'email' => 'test@test.com',
            'phone' => '123',
            'hardware_bundle' => 'premium_deluxe',
        ]);

        $response->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════
    // ─── CONSULTATION (ZATCA/COMPLIANCE) ────────────────
    // ═══════════════════════════════════════════════════════

    public function test_can_submit_consultation_request(): void
    {
        $response = $this->postJson('/api/v2/website/consultation', [
            'full_name' => 'Fatima Al-Said',
            'business_name' => 'Al-Said Trading',
            'email' => 'fatima@alsaid.com',
            'phone' => '+96822222222',
            'consultation_type' => 'zatca_phase2',
            'cr_number' => 'CR-12345',
            'vat_number' => '300123456789003',
            'branches' => '6-20',
            'message' => 'Need help with ZATCA phase 2 compliance',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('website_consultation_requests', [
            'email' => 'fatima@alsaid.com',
            'consultation_type' => 'zatca_phase2',
        ]);
    }

    public function test_consultation_requires_fields(): void
    {
        $response = $this->postJson('/api/v2/website/consultation', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['full_name', 'business_name', 'email', 'phone', 'consultation_type']);
    }

    public function test_consultation_validates_type(): void
    {
        $response = $this->postJson('/api/v2/website/consultation', [
            'full_name' => 'Test',
            'business_name' => 'Test',
            'email' => 'test@test.com',
            'phone' => '123',
            'consultation_type' => 'hacking',
        ]);

        $response->assertUnprocessable();
    }

    // ─── No Auth Required ────────────────────────────────────

    public function test_website_forms_work_without_auth(): void
    {
        // All website forms should work without authentication
        $response = $this->postJson('/api/v2/website/contact', [
            'full_name' => 'Guest',
            'store_name' => 'Guest Store',
            'phone' => '555',
            'email' => 'guest@test.com',
        ]);

        $response->assertCreated();
    }
}
