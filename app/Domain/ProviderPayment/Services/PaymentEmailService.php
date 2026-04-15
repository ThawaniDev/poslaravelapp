<?php

namespace App\Domain\ProviderPayment\Services;

use App\Domain\ProviderPayment\Enums\PaymentEmailType;
use App\Domain\ProviderPayment\Models\PaymentEmailLog;
use App\Domain\ProviderPayment\Models\ProviderPayment;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\SystemConfig\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

class PaymentEmailService
{
    private string $apiToken;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $dbSettings = SystemSetting::where('group', 'email')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $this->apiToken = $dbSettings['email_api_key']
            ?? config('services.mailtrap.token')
            ?? env('MAILTRAP_API_TOKEN', '');

        $this->fromAddress = $dbSettings['email_from_address']
            ?? env('MAIL_FROM_ADDRESS_TRANSACTIONAL', 'hello@wameedpos.com');

        $this->fromName = $dbSettings['email_from_name']
            ?? env('MAIL_FROM_NAME_TRANSACTIONAL', 'Wameed POS');
    }

    /**
     * Send payment confirmation email after successful payment.
     */
    public function sendPaymentConfirmation(ProviderPayment $payment): bool
    {
        $org = $payment->organization;
        $recipientEmail = $this->getOrganizationEmail($payment);

        if (! $recipientEmail) {
            Log::warning('No email found for payment confirmation', ['payment_id' => $payment->id]);
            return false;
        }

        $subject = __('provider_payments.email_payment_confirmation_subject', ['cart_id' => $payment->cart_id ?? $payment->id]);

        $body = $this->buildPaymentConfirmationBody($payment);

        return $this->sendEmail(
            recipientEmail: $recipientEmail,
            subject: $subject,
            htmlBody: $body,
            emailType: PaymentEmailType::PaymentConfirmation,
            payment: $payment,
        );
    }

    /**
     * Send invoice email with PDF link.
     */
    public function sendInvoiceEmail(Invoice $invoice, ?ProviderPayment $payment = null): bool
    {
        $subscription = $invoice->storeSubscription;
        $org = $subscription?->organization;

        $recipientEmail = $org?->email ?? $org?->owner_email ?? null;

        if (! $recipientEmail) {
            Log::warning('No email found for invoice email', ['invoice_id' => $invoice->id]);
            return false;
        }

        $subject = __('provider_payments.email_invoice_subject', ['invoice_number' => $invoice->invoice_number]);

        $body = $this->buildInvoiceEmailBody($invoice);

        return $this->sendEmail(
            recipientEmail: $recipientEmail,
            subject: $subject,
            htmlBody: $body,
            emailType: PaymentEmailType::Invoice,
            payment: $payment,
            invoice: $invoice,
        );
    }

    /**
     * Send payment failed notification email.
     */
    public function sendPaymentFailedEmail(ProviderPayment $payment): bool
    {
        $recipientEmail = $this->getOrganizationEmail($payment);

        if (! $recipientEmail) {
            return false;
        }

        $subject = __('provider_payments.email_payment_failed_subject', ['cart_id' => $payment->cart_id ?? $payment->id]);

        $body = $this->buildPaymentFailedBody($payment);

        return $this->sendEmail(
            recipientEmail: $recipientEmail,
            subject: $subject,
            htmlBody: $body,
            emailType: PaymentEmailType::PaymentFailed,
            payment: $payment,
        );
    }

    /**
     * Send refund confirmation email.
     */
    public function sendRefundConfirmation(ProviderPayment $payment): bool
    {
        $recipientEmail = $this->getOrganizationEmail($payment);

        if (! $recipientEmail) {
            return false;
        }

        $subject = __('provider_payments.email_refund_subject', ['cart_id' => $payment->cart_id ?? $payment->id]);

        $body = $this->buildRefundConfirmationBody($payment);

        return $this->sendEmail(
            recipientEmail: $recipientEmail,
            subject: $subject,
            htmlBody: $body,
            emailType: PaymentEmailType::RefundConfirmation,
            payment: $payment,
        );
    }

    // ─── Private Helpers ────────────────────────────────────

    private function sendEmail(
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        PaymentEmailType $emailType,
        ?ProviderPayment $payment = null,
        ?Invoice $invoice = null,
    ): bool {
        $log = PaymentEmailLog::create([
            'provider_payment_id' => $payment?->id,
            'invoice_id' => $invoice?->id,
            'email_type' => $emailType,
            'recipient_email' => $recipientEmail,
            'subject' => $subject,
            'status' => 'pending',
        ]);

        try {
            $email = (new MailtrapEmail())
                ->from(new Address($this->fromAddress, $this->fromName))
                ->to(new Address($recipientEmail))
                ->subject($subject)
                ->category('payment')
                ->html($htmlBody);

            $response = MailtrapClient::initSendingEmails(
                apiKey: $this->apiToken,
            )->send($email);

            $responseData = ResponseHelper::toArray($response);
            $messageId = $responseData['message_ids'][0] ?? null;

            $log->update([
                'status' => 'sent',
                'mailtrap_message_id' => $messageId,
            ]);

            if ($payment) {
                $payment->update([
                    'confirmation_email_sent' => true,
                    'confirmation_email_sent_at' => now(),
                    'confirmation_email_error' => null,
                ]);
            }

            if ($invoice) {
                $invoice->update([
                    'email_sent' => true,
                    'email_sent_at' => now(),
                    'email_error' => null,
                ]);
            }

            Log::info('Payment email sent successfully', [
                'type' => $emailType->value,
                'recipient' => $recipientEmail,
                'payment_id' => $payment?->id,
            ]);

            return true;
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();

            $log->update([
                'status' => 'failed',
                'error_message' => mb_substr($errorMsg, 0, 500),
            ]);

            if ($payment) {
                $payment->update([
                    'confirmation_email_error' => mb_substr($errorMsg, 0, 500),
                ]);
            }

            if ($invoice) {
                $invoice->update([
                    'email_error' => mb_substr($errorMsg, 0, 500),
                ]);
            }

            Log::error('Failed to send payment email', [
                'type' => $emailType->value,
                'recipient' => $recipientEmail,
                'error' => $errorMsg,
            ]);

            return false;
        }
    }

    private function getOrganizationEmail(ProviderPayment $payment): ?string
    {
        $org = $payment->organization;
        $customerDetails = $payment->customer_details;

        return $customerDetails['email'] ?? $org?->email ?? $org?->owner_email ?? null;
    }

    private function buildPaymentConfirmationBody(ProviderPayment $payment): string
    {
        $purpose = $payment->purpose?->label() ?? $payment->purpose_label ?? '-';
        $amount = $payment->getFormattedAmount();
        $date = $payment->updated_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i');
        $ref = $payment->tran_ref ?? '-';
        $cardInfo = $payment->payment_description ?? '-';
        $orgName = $payment->organization?->name ?? '-';

        $conversionRow = '';
        if ($payment->hasOriginalCurrency()) {
            $origAmount = number_format((float) $payment->original_amount, 2);
            $rate = number_format((float) $payment->exchange_rate_used, 4);
            $sarAmount = number_format((float) $payment->amount, 2);
            $conversionRow = <<<HTML
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;font-size:13px;">التحويل / Conversion</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#6366F1;font-size:14px;text-align:left;">\${$origAmount} USD × {$rate} = {$sarAmount} SAR</td></tr>
            HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;background-color:#F8F7F5;font-family:'Cairo',Arial,sans-serif;">
        <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">
            <div style="background:linear-gradient(135deg,#FD8209,#FFBF0D);padding:32px 24px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:24px;">✅ تأكيد الدفع</h1>
                <p style="color:rgba(255,255,255,.9);margin:8px 0 0;font-size:14px;">Payment Confirmation</p>
            </div>
            <div style="padding:32px 24px;">
                <p style="color:#475569;font-size:16px;line-height:1.6;">مرحباً <strong>{$orgName}</strong>،</p>
                <p style="color:#475569;font-size:14px;line-height:1.6;">تم استلام دفعتك بنجاح. فيما يلي تفاصيل العملية:</p>
                <table style="width:100%;border-collapse:collapse;margin:24px 0;">
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;font-size:13px;">الغرض / Purpose</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;font-weight:600;text-align:left;">{$purpose}</td></tr>
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;font-size:13px;">المبلغ / Amount</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#10B981;font-weight:700;font-size:18px;text-align:left;">{$amount}</td></tr>
                    {$conversionRow}
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;font-size:13px;">رقم المرجع / Reference</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;font-family:monospace;text-align:left;">{$ref}</td></tr>
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;font-size:13px;">البطاقة / Card</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;text-align:left;">{$cardInfo}</td></tr>
                    <tr><td style="padding:12px;color:#64748B;font-size:13px;">التاريخ / Date</td><td style="padding:12px;color:#0F172A;text-align:left;">{$date}</td></tr>
                </table>
                <p style="color:#94A3B8;font-size:12px;text-align:center;margin-top:32px;">شكراً لاختياركم وميض نقاط البيع | Wameed POS</p>
            </div>
        </div>
        </body></html>
        HTML;
    }

    private function buildInvoiceEmailBody(Invoice $invoice): string
    {
        $invoiceNumber = $invoice->invoice_number ?? '-';
        $total = number_format((float) $invoice->total, 2) . ' SAR';
        $status = $invoice->status?->value ?? 'pending';
        $dueDate = $invoice->due_date?->format('Y-m-d') ?? '-';
        $storeName = $invoice->storeSubscription?->organization?->name ?? '-';
        $pdfUrl = $invoice->pdf_url;

        $pdfButton = $pdfUrl
            ? "<a href=\"{$pdfUrl}\" style=\"display:inline-block;background:#FD8209;color:#fff;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:600;margin-top:16px;\">تحميل الفاتورة PDF</a>"
            : '';

        return <<<HTML
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;background-color:#F8F7F5;font-family:'Cairo',Arial,sans-serif;">
        <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">
            <div style="background:linear-gradient(135deg,#FD8209,#FFBF0D);padding:32px 24px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:24px;">📄 فاتورة جديدة</h1>
                <p style="color:rgba(255,255,255,.9);margin:8px 0 0;font-size:14px;">Invoice #{$invoiceNumber}</p>
            </div>
            <div style="padding:32px 24px;">
                <p style="color:#475569;font-size:16px;line-height:1.6;">مرحباً <strong>{$storeName}</strong>،</p>
                <table style="width:100%;border-collapse:collapse;margin:24px 0;">
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;">رقم الفاتورة</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;font-weight:600;text-align:left;">{$invoiceNumber}</td></tr>
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;">الإجمالي</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;font-weight:700;font-size:18px;text-align:left;">{$total}</td></tr>
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;">الحالة</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;text-align:left;">{$status}</td></tr>
                    <tr><td style="padding:12px;color:#64748B;">تاريخ الاستحقاق</td><td style="padding:12px;color:#0F172A;text-align:left;">{$dueDate}</td></tr>
                </table>
                <div style="text-align:center;">{$pdfButton}</div>
                <p style="color:#94A3B8;font-size:12px;text-align:center;margin-top:32px;">وميض نقاط البيع | Wameed POS</p>
            </div>
        </div>
        </body></html>
        HTML;
    }

    private function buildPaymentFailedBody(ProviderPayment $payment): string
    {
        $purpose = $payment->purpose?->label() ?? '-';
        $amount = $payment->getFormattedAmount();
        $reason = $payment->response_message ?? __('provider_payments.unknown_error');
        $orgName = $payment->organization?->name ?? '-';

        return <<<HTML
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;background-color:#F8F7F5;font-family:'Cairo',Arial,sans-serif;">
        <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">
            <div style="background:#EF4444;padding:32px 24px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:24px;">❌ فشل الدفع</h1>
                <p style="color:rgba(255,255,255,.9);margin:8px 0 0;font-size:14px;">Payment Failed</p>
            </div>
            <div style="padding:32px 24px;">
                <p style="color:#475569;font-size:16px;line-height:1.6;">مرحباً <strong>{$orgName}</strong>،</p>
                <p style="color:#475569;font-size:14px;">للأسف، لم تتم عملية الدفع. يرجى المحاولة مرة أخرى.</p>
                <table style="width:100%;border-collapse:collapse;margin:24px 0;">
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;">الغرض</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;text-align:left;">{$purpose}</td></tr>
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;">المبلغ</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;text-align:left;">{$amount}</td></tr>
                    <tr><td style="padding:12px;color:#64748B;">السبب</td><td style="padding:12px;color:#EF4444;text-align:left;">{$reason}</td></tr>
                </table>
                <p style="color:#94A3B8;font-size:12px;text-align:center;margin-top:32px;">وميض نقاط البيع | Wameed POS</p>
            </div>
        </div>
        </body></html>
        HTML;
    }

    private function buildRefundConfirmationBody(ProviderPayment $payment): string
    {
        $amount = number_format((float) $payment->refund_amount, 2) . ' ' . $payment->currency;
        $reason = $payment->refund_reason ?? '-';
        $orgName = $payment->organization?->name ?? '-';
        $ref = $payment->refund_tran_ref ?? $payment->tran_ref ?? '-';

        return <<<HTML
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
        <body style="margin:0;padding:0;background-color:#F8F7F5;font-family:'Cairo',Arial,sans-serif;">
        <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">
            <div style="background:#3B82F6;padding:32px 24px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:24px;">↩️ تأكيد الاسترداد</h1>
                <p style="color:rgba(255,255,255,.9);margin:8px 0 0;font-size:14px;">Refund Confirmation</p>
            </div>
            <div style="padding:32px 24px;">
                <p style="color:#475569;font-size:16px;line-height:1.6;">مرحباً <strong>{$orgName}</strong>،</p>
                <p style="color:#475569;font-size:14px;">تم استرداد المبلغ بنجاح.</p>
                <table style="width:100%;border-collapse:collapse;margin:24px 0;">
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;">المبلغ المسترد</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#3B82F6;font-weight:700;font-size:18px;text-align:left;">{$amount}</td></tr>
                    <tr><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#64748B;">رقم المرجع</td><td style="padding:12px;border-bottom:1px solid #F1F5F9;color:#0F172A;font-family:monospace;text-align:left;">{$ref}</td></tr>
                    <tr><td style="padding:12px;color:#64748B;">السبب</td><td style="padding:12px;color:#0F172A;text-align:left;">{$reason}</td></tr>
                </table>
                <p style="color:#94A3B8;font-size:12px;text-align:center;margin-top:32px;">وميض نقاط البيع | Wameed POS</p>
            </div>
        </div>
        </body></html>
        HTML;
    }
}
