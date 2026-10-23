<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeletionApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $entityType,
        public string $entityName,
        public string $requesterName,
        public array $details,
        public string $approvalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Aprobación pendiente: eliminación de {$this->entityType}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.deletion-approval-request',
        );
    }
}
