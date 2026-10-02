<?php

namespace App\Events;

use App\Models\CompanyActivity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ActivityCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CompanyActivity $activity) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('activities')];
    }

    public function broadcastAs(): string
    {
        return 'activity.created';
    }

    /**
     * @return array{activity: array{id: int, userName: string, description: string, time: string}}
     */
    public function broadcastWith(): array
    {
        $activity = $this->activity->loadMissing('user');
        $createdAt = new Carbon($activity->created_at);
        $createdAt->locale('es');

        return [
            'activity' => [
                'id' => $activity->id,
                'userName' => $activity->user->name,
                'description' => $activity->description,
                'time' => $createdAt->diffForHumans(),
            ],
        ];
    }
}
