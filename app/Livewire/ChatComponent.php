<?php

namespace App\Livewire;

use App\Events\MessageDeleted;
use App\Events\NewMessage;
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

    public function deleteMessage($messageId)
    {
        if (!$messageId) return;

        MessageDeleted::dispatch(
            $messageId,
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
    }

    #[On('user-connected')]
    public function userConnected($userId)
    {
        $this->updateUserStatus($userId, true);
        
        $connectedUser = User::findOrFail($userId);
        $this->users = $this->users->push($connectedUser);
    }

    #[On('user-disconnected')]
    public function userDisconnected($userId)
    {
        $this->updateUserStatus($userId, false);

        $disconnectedUser = User::findOrFail($userId);
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

        $this->updateUserStatus(auth()->user()->id, true);

        $this->users = User::with('messages')
            ->where('is_online', true)
            ->get()
            ->reject(function ($user) {
                return $user->id == auth()->user()->id;
            })
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
