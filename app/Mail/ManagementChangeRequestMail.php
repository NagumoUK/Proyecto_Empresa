<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagementChangeRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $action,
        public string $entityType,
        public string $entityName,
        public string $requesterName,
        public array $details,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Aprobación pendiente: {$this->action} {$this->entityType}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.management-change-request',
        );
    }
}
