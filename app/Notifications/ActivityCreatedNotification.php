<?php

namespace App\Notifications;

use App\Models\CompanyActivity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LogicException;

class ActivityCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CompanyActivity $activity) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * @return array{title: string, message: string, url: string, activityId: int}
     */
    public function toArray(object $notifiable): array
    {
        $activity = $this->activity->loadMissing('user');

        return [
            'title' => 'Nueva actividad en el equipo',
            'message' => $activity->user->name.': '.$activity->description,
            'url' => route('dashboard'),
            'activityId' => $activity->getKey(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new LogicException('Activity notifications require a user recipient.');
        }

        $activity = $this->activity->loadMissing('user');

        return (new MailMessage)
            ->subject('Nueva actividad en el equipo')
            ->greeting('Hola '.$notifiable->name.',')
            ->line($activity->user->name.' registró una nueva actividad.')
            ->line($activity->description)
            ->action('Ver dashboard', route('dashboard'));
    }
}
