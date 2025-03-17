<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MessageRemoveChatUploadedFiles
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message $message,
    )
    {
        foreach ($this->message->files as $file) {
            if (!Storage::exists($file->uploaded_file_path)) continue;

            Storage::delete($file->uploaded_file_path);
        }
    }
}
