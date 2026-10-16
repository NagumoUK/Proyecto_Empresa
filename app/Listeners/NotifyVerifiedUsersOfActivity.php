<?php

namespace App\Listeners;

use App\Events\ActivityCreated;
use App\Models\User;
use App\Notifications\ActivityCreatedNotification;

class NotifyVerifiedUsersOfActivity
{
    public function handle(ActivityCreated $event): void
    {
        User::query()
            ->whereNotNull('email_verified_at')
            ->eachById(static function (User $user) use ($event): void {
                $user->notify(
                    (new ActivityCreatedNotification($event->activity))->afterCommit(),
                );
            });
    }
}
