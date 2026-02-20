<?php

use Illuminate\Support\Facades\Broadcast;

// Match channel name used in GenerationCompleted event and frontend
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
