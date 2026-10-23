<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagementActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $action,
        public string $entityType,
        public string $entityName,
        public string $actorName,
        public array $details,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Actividad del sistema: {$this->action} {$this->entityType}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.management-activity',
        );
    }
}
