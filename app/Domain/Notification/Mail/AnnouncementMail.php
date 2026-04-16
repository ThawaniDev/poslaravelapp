<?php

namespace App\Domain\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $announcementTitle,
        public readonly string $announcementBody,
        public readonly string $type,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address_transactional', config('mail.from.address')),
                config('mail.from.name_transactional', config('mail.from.name')),
            ),
            subject: $this->announcementTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.announcement',
            with: [
                'announcementTitle' => $this->announcementTitle,
                'announcementBody' => $this->announcementBody,
                'type' => $this->type,
            ],
        );
    }
}
