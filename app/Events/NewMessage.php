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
        array $uploadedFiles,
    )
    {
        $this->message = Message::create([
            'user_id' => $userId,
            'content' => $messageContent,
        ]);

        // Attach any uploaded files to the message.
        if ($uploadedFiles) {
            foreach ($uploadedFiles as $uploadedFile) {
                ChatUploadedFile::create([
                    'message_id' => $this->message->id,
                    'uploaded_file_path' => $uploadedFile->store('chat-uploads'),
                    'file_type' => $uploadedFile->getMimeType(),
                ]);
            }
        }
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
