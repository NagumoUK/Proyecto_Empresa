<?php

namespace App\Actions;

use App\Contracts\CompanyActivityRecorder;
use App\Events\ActivityCreated;
use App\Models\CompanyActivity;
use App\Models\User;

class RecordCompanyActivity implements CompanyActivityRecorder
{
    public function record(User $user, string $description): CompanyActivity
    {
        $activity = CompanyActivity::query()->create([
            'user_id' => $user->getKey(),
            'description' => $description,
        ]);

        ActivityCreated::dispatch($activity);

        return $activity;
    }
}
