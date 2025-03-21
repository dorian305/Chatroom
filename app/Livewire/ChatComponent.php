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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChatComponent extends Component
{
    use WithFileUploads;

    public array $newFileUploads = [];
    public array $uploadedFiles = [];
    public array $usersCurrentlyTyping = [];
    public User $localUser;
    public Collection $users;
    public int $onlineUsersNumber = 0;
    public string $searchUsers = '';
    public Collection $messages;

    public function updatedNewFileUploads(): void
    {
        $this->uploadedFiles = [
            ...$this->uploadedFiles,
            ...$this->newFileUploads
        ];
        $this->newFileUploads = [];
    }

    public function updatedSearchUsers(): void
    {
        $this->searchUsers = strtolower($this->searchUsers);

        if ($this->searchUsers === '') {
            $this->users = User::where('is_online', true)
                ->get()
                ->reject(fn ($user) => $user->id == $this->localUser->id)
                ->prepend($this->localUser);
        } else {
            $this->users = User::where('is_online', '=', true)
                ->whereRaw('LOWER(name) like ?', ["%{$this->searchUsers}%"])
                ->get();
        }
    }


    public function sendMessage(string $message): void
    {
        Validator::make(
            [
                'message' => $message,
                'files' => $this->uploadedFiles
            ],
            [
                'message' => ['required_without:file', 'nullable', 'string', 'max:5000'],
                'files' => ['required_without:message', 'nullable', 'array'],
                'files.*' => ['file', 'max:10240'],
            ]
        )->validate();

        NewMessage::dispatch(
            $this->localUser->id,
            $message,
            $this->uploadedFiles,
        );

        $this->uploadedFiles = [];
    }

    public function deleteMessage(int $messageId): void
    {
        Validator::make(
            ['messageId' => $messageId],
            ['messageId' => ['required', 'exists:messages,id']],
        )->validate();

        $messageBeingDeleted = Message::findOrFail($messageId);

        // Prevent non-owners of the message to delete it.
        if ($messageBeingDeleted->user->id !== $this->localUser->id) return;
        
        DeleteMessage::dispatch(
            $messageId,
        );
    }

    public function editMessage(int $messageId, string $oldContent, string $updatedContent): void
    {
        Validator::make(
            [
                'messageId' => $messageId,
                'oldContent' => $oldContent,
                'updatedContent' => $updatedContent,
            ],
            [
                'messageId' => ['required', 'exists:messages,id'],
                'oldContent' => ['string', "max:5000", 'different:updatedContent'],
                'updatedContent' => ['nullable', 'string', "max:5000"],
            ]
        )->validate();

        $messageBeingEdited = Message::findOrFail($messageId);

        // Prevent non-owners of the message to edit it.
        if ($messageBeingEdited->user->id !== $this->localUser->id) return;

        // Delete the message if user deleted message content and message has no files attached to it.
        if (!$updatedContent && $messageBeingEdited->files->isEmpty()) {
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
                'userId' => $userId,
                'activityStatus' => $activityStatus,
            ],
            [
                'userId' => ['required', 'exists:users,id'],
                'activityStatus' => ['required', new ActivityStatusRule()],
            ],
        )->validate();

        UserActivity::dispatch(
            $userId,
            $activityStatus,
        );
    }

    public function typing(string $username, bool $isTyping): void
    {
        Validator::make(
            ['username' => $username],
            ['username' => ['required', 'exists:users,name']]
        )->validate();

        // If local user has attempted to change the name, just ignore.
        if (!$username === $this->localUser->name) return;

        UserTyping::dispatch(
            $username,
            $isTyping,
        );
    }

    public function deleteUploadedFilePreview(string $fileId): void
    {
        $path = "livewire-tmp/$fileId";

        foreach ($this->uploadedFiles as $uploadedFile) {
            if (Storage::exists($path)){
                Storage::delete($path);
            }

            $this->uploadedFiles = array_values(
                array_filter(
                    $this->uploadedFiles,
                    fn ($uploadedFile): bool => $uploadedFile->getFilename() !== $fileId
                )
            );
        }
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
        $this->messages = $this->messages->reject(fn ($msg): bool =>
            $msg->id === $data['messageId']
        );

        $this->dispatch('updated-messages');
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
        $this->onlineUsersNumber = $this->users->count();
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
        $this->onlineUsersNumber = $this->users->count();
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

        $this->updateUserStatus($this->localUser->id, true);
        $this->updateUserActivity($this->localUser->id, 'active');

        $this->users = User::where('is_online', true)
            ->get()
            ->reject(fn ($user) => $user->id == $this->localUser->id)
            ->prepend($this->localUser);
        $this->messages = Message::where('is_deleted', '=', false)
            ->get();
        $this->onlineUsersNumber = $this->users->count();
    }

    public function render()
    {
        return view('livewire.chat-component');
    }
}
