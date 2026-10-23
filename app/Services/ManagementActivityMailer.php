<?php

namespace App\Services;

use App\Mail\ManagementActivityMail;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ManagementActivityMailer
{
    /**
     * @param  array<string, string>  $details
     */
    public function send(
        string $action,
        string $entityType,
        string $entityName,
        string $actorName,
        array $details,
    ): bool {
        $recipient = config('mail.activity_recipient');

        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            report(new LogicException('The management activity email recipient is not configured.'));

            return false;
        }

        try {
            Mail::to($recipient)->send(new ManagementActivityMail(
                action: $action,
                entityType: $entityType,
                entityName: $entityName,
                actorName: $actorName,
                details: $details,
            ));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
