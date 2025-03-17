<?php

namespace App\Events;

use App\Models\ChatUploadedFile;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $userId,
        string $messageContent,
        $uploadedFile,
    )
    {
        $newMessage = Message::create([
            'user_id' => $userId,
            'content' => $messageContent,
        ]);

        // If file is uploaded, attach it to the message.
        if ($uploadedFile) {
            $uploadedFile = ChatUploadedFile::create([
                'message_id' => $newMessage->id,
                'uploaded_file_path' => $uploadedFile->store('chat-uploads'),
                'file_type' => $uploadedFile->getMimeType(),
            ]);
        }

        $this->message = $newMessage;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chatroom'),
        ];
    }
}
