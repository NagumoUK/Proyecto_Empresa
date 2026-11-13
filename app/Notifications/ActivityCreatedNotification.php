<?php

namespace App\Notifications;

use App\Models\CompanyActivity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LogicException;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ActivityCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CompanyActivity $activity) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast', 'mail'];

        if ($notifiable instanceof User
            && $notifiable->pushSubscriptions()->exists()
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'))
            && filled(config('webpush.vapid.subject'))) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
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

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        if (! $notifiable instanceof User) {
            throw new LogicException('Activity notifications require a user recipient.');
        }

        return (new WebPushMessage)
            ->title('Nueva actividad en el equipo')
            ->body('Se ha registrado una nueva actividad.')
            ->tag('company-activity')
            ->data(['url' => route('dashboard')]);
    }
}
