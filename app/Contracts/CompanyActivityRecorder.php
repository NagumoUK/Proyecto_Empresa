<?php

namespace App\Contracts;

use App\Models\CompanyActivity;
use App\Models\User;

interface CompanyActivityRecorder
{
    public function record(User $user, string $description): CompanyActivity;
}
