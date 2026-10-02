<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('activities', fn ($user) => $user !== null);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
