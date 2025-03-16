<?php

namespace App\Livewire;

use App\Events\DeleteMessage;
use App\Events\EditMessage;
use App\Events\NewMessage;
use App\Events\UserActivity;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ChatComponent extends Component
{
    public array $usersCurrentlyTyping = [];
    public Collection $users;
    public Collection $messages;
    public User $localUser;

    #[Validate('required|string|max:5000')]
    public function sendMessage(string $message): void
    {
        if (!$message) return;
        
        NewMessage::dispatch(
            $this->localUser->id,
            $message,
        );
    }

    public function deleteMessage(int $messageId): void
    {
        $messageBeingDeleted = Message::findOrFail($messageId);

        if ($messageBeingDeleted->user === auth()->user()) return;
        
        DeleteMessage::dispatch(
            $messageId,
        );
    }

    public function editMessage(int $messageId, string $oldContent, string $updatedContent): void
    {
        if ($oldContent === $updatedContent) return;

        // Delete the message user deleted message content.
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
