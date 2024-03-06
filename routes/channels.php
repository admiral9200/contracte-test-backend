<?php

use App\Events\ChatGpTJobProcessed;
use App\Events\ChatGPTResponded;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat-gpt-job', function ($prompt, $jobId) {
    return true;
});

Broadcast::channel('chat-gpt-responded', function ($data) {
    return true;
});

Broadcast::channel('chat-gpt-job', ChatGpTJobProcessed::class);

Broadcast::channel('chat-gpt-responded', ChatGPTResponded::class);