<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('activities', fn ($user) => ['ably-capability' => ['subscribe']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
