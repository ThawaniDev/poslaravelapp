<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider Payments & Invoice Extensions
 *
 * Creates provider_payments table for tracking PayTabs payments from providers
 * (subscriptions, add-ons, AI billing, etc.) and extends invoices with
 * gateway tracking, email status, and comprehensive audit fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // ─── Provider Payments (PayTabs gateway transactions) ────────
        Schema::create('provider_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->uuid('invoice_id')->nullable();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();

            // Payment purpose & context
            $table->string('purpose', 30); // subscription, plan_addon, ai_billing, hardware, implementation_fee, other
            $table->string('purpose_label', 200)->nullable(); // Human-readable label e.g. "Pro Plan - Monthly"
            $table->uuid('purpose_reference_id')->nullable(); // FK to related entity (subscription, addon, etc.)

            // Amounts
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('SAR');

            // Gateway details (PayTabs)
            $table->string('gateway', 30)->default('paytabs'); // paytabs, manual, free
            $table->string('tran_ref', 100)->nullable()->index(); // PayTabs transaction reference
            $table->string('tran_type', 20)->nullable(); // sale, auth, capture, void, refund
            $table->string('cart_id', 100)->nullable(); // Our unique cart identifier sent to PayTabs

            // Payment result from gateway
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, refunded, voided
            $table->string('response_status', 5)->nullable(); // PayTabs: A (approved), D (declined), E (error), H (hold)
            $table->string('response_code', 20)->nullable();
            $table->string('response_message', 255)->nullable();

            // Card info (masked)
            $table->string('card_type', 20)->nullable(); // Credit, Debit
            $table->string('card_scheme', 20)->nullable(); // Visa, Mastercard, Mada
            $table->string('payment_description', 50)->nullable(); // 4111 11## #### 1111
            $table->string('payment_method', 30)->nullable(); // creditcard, mada, applepay, stcpay

            // Tokenization
            $table->string('token', 255)->nullable(); // PayTabs token for recurring
            $table->string('previous_tran_ref', 100)->nullable(); // For recurring token payments

            // Email status
            $table->boolean('confirmation_email_sent')->default(false);
            $table->timestamp('confirmation_email_sent_at')->nullable();
            $table->string('confirmation_email_error', 500)->nullable();

            // Invoice generation status
            $table->boolean('invoice_generated')->default(false);
            $table->timestamp('invoice_generated_at')->nullable();

            // IPN tracking
            $table->boolean('ipn_received')->default(false);
            $table->timestamp('ipn_received_at')->nullable();
            $table->json('ipn_payload')->nullable();

            // Refund tracking
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->string('refund_tran_ref', 100)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason', 500)->nullable();

            // Metadata
            $table->json('gateway_response')->nullable(); // Full gateway response JSON
            $table->json('customer_details')->nullable(); // Customer info sent to gateway
            $table->text('notes')->nullable();
            $table->uuid('initiated_by')->nullable(); // User who started the payment
            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'purpose']);
            $table->index('created_at');
        });

        // ─── Extend invoices table with payment tracking ─────────────
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('provider_payment_id')->nullable()->after('pdf_url');
            $table->string('payment_gateway', 30)->nullable()->after('provider_payment_id');
            $table->string('gateway_tran_ref', 100)->nullable()->after('payment_gateway');
            $table->boolean('email_sent')->default(false)->after('gateway_tran_ref');
            $table->timestamp('email_sent_at')->nullable()->after('email_sent');
            $table->string('email_error', 500)->nullable()->after('email_sent_at');
        });

        // ─── Payment email log for audit trail ───────────────────────
        Schema::create('payment_email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_payment_id')->nullable();
            $table->foreign('provider_payment_id')->references('id')->on('provider_payments')->cascadeOnDelete();
            $table->uuid('invoice_id')->nullable();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->string('email_type', 50); // payment_confirmation, invoice, payment_failed, refund_confirmation
            $table->string('recipient_email', 200);
            $table->string('subject', 300);
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->string('error_message', 500)->nullable();
            $table->string('mailtrap_message_id', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::dropIfExists('payment_email_logs');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'provider_payment_id',
                'payment_gateway',
                'gateway_tran_ref',
                'email_sent',
                'email_sent_at',
                'email_error',
            ]);
        });

        Schema::dropIfExists('provider_payments');
    }
};
