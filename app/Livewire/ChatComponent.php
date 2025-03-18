<?php

namespace App\Livewire;

use App\Events\DeleteMessage;
use App\Events\EditMessage;
use App\Events\NewMessage;
use App\Events\UserActivity;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\User;
use App\Rules\ActivityStatusRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChatComponent extends Component
{
    use WithFileUploads;

    public array $usersCurrentlyTyping = [];
    public Collection $users;
    public Collection $messages;
    public User $localUser;
    public $uploadedFile;

    public function sendMessage(string $message): void
    {
        // Prevent empty messages from being submitted.
        if (!$message && !$this->uploadedFile) return;
        
        NewMessage::dispatch(
            $this->localUser->id,
            $message,
            $this->uploadedFile,
        );

        $this->uploadedFile = null;
    }

    public function deleteMessage(int $messageId): void
    {
        Validator::make(
            ['messageId' => $messageId],
            ['messageId' => ['required', 'exists:messages,id']],
        )->validate();

        $messageBeingDeleted = Message::findOrFail($messageId);

        // Prevent non-owners of the message to delete it.
        if ($messageBeingDeleted->user->id !== auth()->user()->id) return;
        
        DeleteMessage::dispatch(
            $messageId,
        );
    }

    public function editMessage(int $messageId, string $oldContent, string $updatedContent): void
    {
        $messageBeingEdited = Message::findOrFail($messageId);

        // If content is the same, just exit.
        if ($oldContent === $updatedContent) return;

        // Prevent non-owners of the message to edit it.
        if ($messageBeingEdited->user->id !== auth()->user()->id) return;

        // Delete the message if user deleted message content.
        if (!$updatedContent) {
            DeleteMessage::dispatch(
                $messageId,
            );

            return;
        }

        EditMessage::dispatch(
            $messageId,
            $updatedContent,
        );
    }

    public function updateUserActivity(int $userId, string $activityStatus): void
    {
        Validator::make(
            [
                'activityStatus' => $activityStatus,
                'userId' => $userId,
            ],
            [
                'activityStatus' => ['required', new ActivityStatusRule()],
                'userId' => ['required', 'exists:users,id'],
            ],
        )->validate();

        UserActivity::dispatch(
            $userId,
            $activityStatus,
        );
    }

    public function typing(string $username, bool $isTyping): void
    {
        UserTyping::dispatch(
            $username,
            $isTyping,
        );
    }

    public function deleteUploadedFile(): void
    {
        $this->uploadedFile = null;
    }

    #[On('echo:chatroom,NewMessage')]
    public function newMessageReceived($data): void
    {
        $message = Message::findOrFail($data['message']['id']);

        $this->messages->push($message);
        $this->dispatch('updated-messages');
    }

    #[On('echo:chatroom,DeleteMessage')]
    public function deletedMessage($data): void
    {
        // This shit just fucking works and i dont know why.
        // Messages just get updated when event is fired.
        // If I comment out this method, then it doesn't work.
    }

    #[On('echo:chatroom,EditMessage')]
    public function editedMessage($data): void
    {
        $messageId = $data['messageId'];
        $updatedContent = $data['updatedContent'];

        $this->messages = $this->messages
            ->map(function ($message) use ($messageId, $updatedContent) {
                if ($message->id === $messageId) {
                    $message->content = $updatedContent;
                }

                return $message;
            });
    }

    #[On('echo:chatroom,UserActivity')]
    public function userActivityStatusUpdated($data): void
    {
        $userId = $data['userId'];
        $activityStatus = $data['activityStatus'];

        $this->users = $this->users->map(function ($user) use ($userId, $activityStatus) {
            if ($user->id === $userId) {
                $user->activity_status = $activityStatus;
            }

            return $user;
        });
    }

    #[On('echo:chatroom,UserTyping')]
    public function userIsTyping($data): void
    {
        $username = $data['username'];
        $isTyping = $data['isTyping'];

        // Add typing user to the array.
        if ($isTyping && !in_array($username, $this->usersCurrentlyTyping)) {
            $this->usersCurrentlyTyping[] = $username;

            return;
        }

        // Remove typing user from the array.
        if (!$isTyping && in_array($username, $this->usersCurrentlyTyping)) {
            $key = array_search($username, $this->usersCurrentlyTyping);
            unset($this->usersCurrentlyTyping[$key]);
            $this->usersCurrentlyTyping = array_values($this->usersCurrentlyTyping);
        }
    }

    #[On('user-connected')]
    public function userConnected($userId)
    {
        $connectedUser = User::findOrFail($userId);
        $this->users = $this->users->push($connectedUser);
    }

    #[On('user-disconnected')]
    public function userDisconnected($userId): void
    {
        $this->updateUserStatus($userId, false);

        $disconnectedUser = User::findOrFail($userId);
        $disconnectedUser->update([
            'activity_status' => null,
        ]);

        $this->users = $this->users->reject(fn ($user) => $user->id === $disconnectedUser->id);
    }

    public function updateUserStatus(int $userId, bool $online): void
    {
        User::findOrFail($userId)->update([
            'is_online' => $online,
        ]);
    }

    public function mount(): void
    {
        $this->users = collect();
        $this->messages = collect();
        $this->localUser = auth()->user();

        UserActivity::dispatch(
            $this->localUser->id,
            'active',
        );

        $this->updateUserStatus($this->localUser->id, true);

        $this->users = User::with('messages')
            ->where('is_online', true)
            ->get()
            ->reject(fn ($user) => $user->id == $this->localUser->id)
            ->prepend($this->localUser);
        $this->messages = Message::where('is_deleted', '=', false)
            ->get();
    }

    public function render()
    {
        return view('livewire.chat-component');
    }
}
