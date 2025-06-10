<?php

namespace App\Livewire;

use App\Enums\ActivityStatusEnum;
use App\Events\DeleteMessage;
use App\Events\EditMessage;
use App\Events\NewMessage;
use App\Events\UserActivity;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class LoungeComponent extends Component
{
    use WithFileUploads;

    public array $newFileUploads = [];
    public array $uploadedFiles = [];
    public array $usersCurrentlyTyping = [];
    public User $localUser;
    public array $users = [];
    public int $onlineUsersNumber = 0;
    public string $searchUsers = '';
    public array $searchedUsersList = [];
    public Collection $messages;

    public function render()
    {
        return view('livewire.lounge-component');
    }

    public function mount(): void
    {
        $this->localUser = auth()->user();
        $this->messages = Message::where('is_deleted', '=', false)
            ->get();
    }

    #[On('get-connected-users')]
    public function getConnectedUsers($connectedUsers)
    {
        $users = collect($connectedUsers);
        $localUser = $users->firstWhere('id', $this->localUser->id);
        $otherUsers = $users->reject(fn($user) => $user['id'] === $this->localUser->id);
        
        $this->users = $localUser 
            ? $otherUsers->prepend($localUser)->toArray()
            : $otherUsers->toArray();
            
        $this->onlineUsersNumber = count($this->users);
    }

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
        $this->searchedUsersList = collect($this->users)
            ->filter(function ($user): bool {
                return str_contains(
                    strtolower($user['name']),
                    strtolower($this->searchUsers)
                );
            })
            ->values()
            ->toArray();
    }


    public function sendMessage(string $message): void
    {
        Validator::make(
            [
                'message' => $message,
                'files' => $this->uploadedFiles
            ],
            [
                'message' => ['required_without:files', 'string', 'min:3', 'max:5000'],
                'files' => ['nullable', 'array'],
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
        if (!ActivityStatusEnum::tryFrom($activityStatus)) {
            $activityStatus = 'away';
        }

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

    #[On('echo-presence:lounge,NewMessage')]
    public function newMessageReceived($data): void
    {
        $message = Message::findOrFail($data['message']['id']);

        $this->messages->push($message);
        $this->dispatch('updated-messages');
    }

    #[On('echo-presence:lounge,DeleteMessage')]
    public function deletedMessage($data): void
    {
        $this->messages = $this->messages->reject(fn ($msg): bool =>
            $msg->id === $data['messageId']
        );

        $this->dispatch('updated-messages');
    }

    #[On('echo-presence:lounge,EditMessage')]
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

    #[On('echo-presence:lounge,UserActivity')]
    public function userActivityStatusUpdated($data): void
    {
        $userId = $data['userId'];
        $activityStatus = $data['activityStatus'];

        foreach ($this->users as &$user) {
            if ($user['id'] !== $userId) continue;

            $user['activity_status'] = $activityStatus;
        }
    }

    #[On('echo-presence:lounge,UserTyping')]
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
    public function userConnected(array $connectedUser): void
    {
        $this->users[] = $connectedUser;
        $this->onlineUsersNumber = count($this->users);
    }

    #[On('user-disconnected')]
    public function userDisconnected(array $disconnectedUser): void
    {
        $this->users = array_filter(
            $this->users,
            function ($user) use ($disconnectedUser): bool {
                return $user['id'] !== $disconnectedUser['id'];
            }
        );
        $this->onlineUsersNumber = count($this->users);
    }
}
