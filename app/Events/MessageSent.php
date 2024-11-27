<?php

namespace App\Events;

use App\Models\ChatMessages;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatMessages $message)
    {
        $this->message = $message;
    }


    public function broadcastOn()
    {
//        return [
//            new PrivateChannel('chat.' . $this->message->receiver_id),
//        ];
        return new PrivateChannel('chat.' . $this->message->receiver_id);
    }

    public function broadcastWith()
    {
        Log::info("Broadcasting message fromm {$this->message->sender_id} to {$this->message->receiver_id}");

        return [
            'message' => $this->message->load(['sender', 'receiver']),
        ];
    }

    public function broadcastAs()
    {
        return 'MessageSent';
    }
}
