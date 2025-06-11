<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('lounge', function ($user): ?array {
    if ($user) {
        $userData = $user->toArray();
        $userData['activity_status'] = 'active';

        return $userData;
    }
});

Broadcast::channel('online-users', function ($user): User {
    return $user;
});