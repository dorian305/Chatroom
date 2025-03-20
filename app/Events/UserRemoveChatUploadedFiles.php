<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserRemoveChatUploadedFiles
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
    )
    {
        /**
         * Delete any chat uploaded files by the user.
         */
        foreach ($this->user->messages as $message) {
            foreach ($message->files as $file) {
                if (!Storage::exists($file->uploaded_file_path)) continue;

                Storage::delete($file->uploaded_file_path);
            }
        }
    }
}
