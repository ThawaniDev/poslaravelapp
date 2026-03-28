<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Contact / Demo Request Submissions ───────────────
        Schema::create('website_contact_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_number', 20)->unique();
            $table->string('full_name');
            $table->string('store_name');
            $table->string('phone', 20);
            $table->string('email');
            $table->string('store_type')->nullable();         // coffee, retail, restaurant, cloud-kitchen, other
            $table->string('branches')->nullable();            // 1, 2-5, 6-20, 20+
            $table->text('message')->nullable();
            $table->string('source_page')->default('contact'); // contact, home, pricing, features, about, zatca
            $table->string('selected_plan')->nullable();       // starter, growth, pro, enterprise
            $table->string('inquiry_type')->default('demo');   // demo, general, zatca, trial
            $table->string('status')->default('new');          // new, contacted, qualified, converted, closed
            $table->text('admin_notes')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('inquiry_type');
            $table->index('source_page');
            $table->index('created_at');
        });

        // ─── 2. Newsletter Subscribers ───────────────────────────
        Schema::create('website_newsletter_subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('source_page')->default('footer');
            $table->string('status')->default('active'); // active, unsubscribed
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // ─── 3. Partnership Applications ─────────────────────────
        Schema::create('website_partnership_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_number', 20)->unique();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('partnership_type');  // delivery_platform, payment_provider, developer, reseller, technology, other
            $table->string('website')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new, reviewing, approved, rejected, closed
            $table->text('admin_notes')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('partnership_type');
            $table->index('created_at');
        });

        // ─── 4. Hardware Quote Requests ──────────────────────────
        Schema::create('website_hardware_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_number', 20)->unique();
            $table->string('full_name');
            $table->string('business_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('hardware_bundle')->nullable(); // starter_kit, pro_bundle, enterprise_suite, custom
            $table->unsignedInteger('terminal_quantity')->default(1);
            $table->boolean('needs_printer')->default(false);
            $table->boolean('needs_scanner')->default(false);
            $table->boolean('needs_cash_drawer')->default(false);
            $table->boolean('needs_payment_terminal')->default(false);
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new, quoted, negotiating, ordered, closed
            $table->text('admin_notes')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('hardware_bundle');
            $table->index('created_at');
        });

        // ─── 5. Consultation Requests (ZATCA / Compliance) ──────
        Schema::create('website_consultation_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_number', 20)->unique();
            $table->string('full_name');
            $table->string('business_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('cr_number', 30)->nullable();   // Commercial Registration
            $table->string('vat_number', 30)->nullable();   // VAT Registration
            $table->string('current_pos_system')->nullable();
            $table->string('consultation_type');             // zatca_phase2, compliance_audit, pos_migration, general
            $table->string('branches')->nullable();          // 1, 2-5, 6-20, 20+
            $table->text('message')->nullable();
            $table->string('status')->default('new');        // new, scheduled, in_progress, completed, closed
            $table->text('admin_notes')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('consultation_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_consultation_requests');
        Schema::dropIfExists('website_hardware_quotes');
        Schema::dropIfExists('website_partnership_applications');
        Schema::dropIfExists('website_newsletter_subscribers');
        Schema::dropIfExists('website_contact_submissions');
    }
};
