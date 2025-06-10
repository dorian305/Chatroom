<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('lounge', function ($user) {
    if ($user) {
        $userData = $user->toArray();
        $userData['activity_status'] = 'active';

        return $userData;
    }
});
