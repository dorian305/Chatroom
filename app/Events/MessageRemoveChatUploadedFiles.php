<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;

class MessageRemoveChatUploadedFiles
{
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
