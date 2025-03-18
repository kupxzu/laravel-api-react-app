<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $recipient;
    public $recipientType;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message, $recipient, $recipientType)
    {
        $this->message = $message;
        $this->recipient = $recipient;
        $this->recipientType = $recipientType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->recipientType}.{$this->recipient}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        // Load the sender data
        $message = $this->message->load('sender');
        
        // Add attachment path if exists
        if ($this->message->attachment) {
            $message->attachment_path = $this->message->attachment_path;
        }
        
        return [
            'message' => $message,
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}