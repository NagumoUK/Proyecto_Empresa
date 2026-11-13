<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('activities', fn ($user) => ['ably-capability' => ['subscribe']]);

Broadcast::channel('workspace', function (User $user): array|false {
    if (! $user->hasVerifiedEmail()) {
        return false;
    }

    return [
        'id' => $user->getAuthIdentifier(),
        'name' => $user->name,
        'ably-capability' => ['subscribe', 'presence', 'publish'],
    ];
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});
