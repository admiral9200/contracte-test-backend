<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class ChatGpTJobProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The name of the queue connection to use when broadcasting the event.
     *
     * @var string
     */
    // public $connection = 'redis';

    /**
     * The name of the queue on which to place the broadcasting job.
     *
     * @var string
     */
    // public $queue = 'default';


    public $jobId;
    public $result;

    /**
     * Create a new event instance.
     */
    public function __construct($jobId, $result)
    {
        $this->jobId = $jobId;
        $this->result = $result;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chat-gpt-job'),
        ];
    }


    /**
     * The model event's broadcast name.
     */
    public function broadcastAs()
    {
        return "chatgpt.responded";
    }

    /**
     * Get the data to broadcast for the model.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith()
    {
        return [
            'jobId' => $this->jobId,
            'result' =>  $this->result
        ];
    }
}
