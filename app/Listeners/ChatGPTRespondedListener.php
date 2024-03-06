<?php

namespace App\Listeners;

use App\Events\ChatGpTJobProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Broadcast;

class ChatGPTRespondedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ChatGpTJobProcessed $event)
    {
        $jobId = $event->jobId;
        $result = $event->result;

        Broadcast::channel('chat-gpt-responded', function ($user) use ($result) {
            return $result;
        });
    }
}
