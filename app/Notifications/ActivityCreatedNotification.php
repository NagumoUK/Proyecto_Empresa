<?php

namespace App\Notifications;

use App\Models\CompanyActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CompanyActivity $activity) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activity = $this->activity->loadMissing('user');

        return (new MailMessage)
            ->subject('Nueva actividad en el equipo')
            ->greeting('Hola '.$notifiable->name.',')
            ->line($activity->user->name.' registró una nueva actividad.')
            ->line($activity->description)
            ->action('Ver dashboard', route('dashboard'));
    }
}
