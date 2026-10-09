<?php

namespace App\Listeners;

use App\Events\ActivityCreated;
use Illuminate\Support\Facades\Log;

class LogCompanyActivity
{
    public function handle(ActivityCreated $event): void
    {
        Log::info('Company activity created.', [
            'activity_id' => $event->activity->getKey(),
        ]);
    }
}
