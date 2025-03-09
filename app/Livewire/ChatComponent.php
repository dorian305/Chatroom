<?php

namespace App\Livewire;

use App\Events\MessageDeleted;
use App\Events\NewMessage;
use App\Events\UserActivity;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatComponent extends Component
{
    public string $message = "";
    public string $username = "";
    public Collection $users;
    public Collection $messages;

    public function sendMessage()
    {
        if (!trim($this->message)) return;

        NewMessage::dispatch(
            auth()->user()->id,
            $this->message,
        );

        $this->message = "";
    }

    public function deleteMessage(int $messageId)
    {
        if (!$messageId) return;

        MessageDeleted::dispatch(
            $messageId,
        );
    }

    public function updateUserActivity(int $userId, string $activityStatus)
    {
        UserActivity::dispatch(
            $userId,
            $activityStatus,
        );
    }

    #[On('echo:chatroom,NewMessage')]
    public function newMessageReceived($data)
    {
        $message = Message::findOrFail($data['message']['id']);

        $this->messages->push($message);
        $this->dispatch('updated-messages');
    }

    #[On('echo:chatroom,MessageDeleted')]
    public function deletedMessage()
    {
        // This shit just fucking works and i dont know why.
        // Messages just get updated when event is fired.
        // If I comment out this method, then it doesn't work.

        $this->dispatch('updated-messages');
    }

    #[On('echo:chatroom,UserActivity')]
    public function userActivityStatusUpdated($data)
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

    #[On('user-connected')]
    public function userConnected($userId)
    {
        $connectedUser = User::findOrFail($userId);
        $this->users = $this->users->push($connectedUser);
    }

    #[On('user-disconnected')]
    public function userDisconnected($userId)
    {
        $this->updateUserStatus($userId, false);

        $disconnectedUser = User::findOrFail($userId);
        $disconnectedUser->update([
            'activity_status' => null,
        ]);

        $this->users = $this->users->reject(fn ($user) => $user->id === $disconnectedUser->id);
    }

    public function updateUserStatus(int $userId, bool $online)
    {
        User::findOrFail($userId)->update([
            'is_online' => $online,
        ]);
    }

    public function mount()
    {
        $this->users = collect();
        $this->messages = collect();

        UserActivity::dispatch(
            auth()->user()->id,
            'active',
        );
        $this->updateUserStatus(auth()->user()->id, true);

        $this->users = User::with('messages')
            ->where('is_online', true)
            ->get()
            ->reject(fn ($user) => $user->id == auth()->user()->id)
            ->prepend(auth()->user());


        $this->messages = Message::with('user')
            ->where('is_deleted', '=', false)
            ->get();
    }

    public function render()
    {
        return view('livewire.chat-component');
    }
}
