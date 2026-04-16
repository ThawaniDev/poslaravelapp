<?php

namespace App\Domain\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $reminderType,
        public readonly string $planName,
        public readonly string $expiryDate,
        public readonly string $organizationName,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->reminderType) {
            'upcoming' => "Your subscription expires on {$this->expiryDate}",
            'overdue' => 'Your subscription has expired — please renew',
            'trial_ending' => "Your trial ends on {$this->expiryDate}",
            default => 'Subscription Reminder',
        };

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address_transactional', config('mail.from.address')),
                config('mail.from.name_transactional', config('mail.from.name')),
            ),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-reminder',
            with: [
                'reminderType' => $this->reminderType,
                'planName' => $this->planName,
                'expiryDate' => $this->expiryDate,
                'organizationName' => $this->organizationName,
            ],
        );
    }
}
