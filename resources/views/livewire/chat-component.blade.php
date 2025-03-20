<div
    class="flex items-center justify-center"
    x-data="{
        inactivityTime: 60000,
        activityStatus: '{{ auth()->user()->activity_status }}',
        inactivityTimer: null,

        resetInactivityTimer() {
            if (this.activityStatus !== 'active') {
                this.updateUserActivity('active');
            }

            clearTimeout(this.inactivityTimer);
            this.inactivityTimer = setTimeout(() => this.notifyInactivity(), this.inactivityTime);
        },
        notifyInactivity() {
            this.updateUserActivity('away');
        },
        updateUserActivity(status) {
            this.activityStatus = status;
            $wire.updateUserActivity({{ auth()->user()->id }}, this.activityStatus);
        },
    }"
    x-init="resetInactivityTimer()"
>
    <div class="w-full flex text-white rounded-lg">
		
        <!-- Chatbox -->
        <section
            class="flex flex-col w-5/6 relative"
            x-data="{
                isViewingOldMessages: false,
                scrollOffsetThreshold: 100,
                messagesContainer: document.querySelector('#messages-container'),

                checkIfViewingOldMessages() {
                    this.isViewingOldMessages = 
                        this.messagesContainer.scrollTop
                        + this.messagesContainer.clientHeight
                        + this.scrollOffsetThreshold < this.messagesContainer.scrollHeight;
                },
            }"
        >

            <div class="rounded-lg overflow-hidden relative">

                <!-- Chatbox header -->
                <div class="flex items-center px-4 h-10 bg-gray-800 border-b border-gray-700">
                    Chatbox
                </div>

                <!-- Notification for when you're looking at old messages -->
                <div
                    class="absolute z-10 top-10 left-0 w-full bg-gray-500/5 bg-opacity-50 text-center py-2 text-sm"
                    x-cloak
                    x-show="isViewingOldMessages"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                >
                    You are viewing older messages.
                    <span
                        class="text-blue-500 hover:text-blue-700 underline cursor-pointer font-semibold"
                        x-on:click="
                            messagesContainer.scrollTo({
                                top: messagesContainer.scrollHeight,
                                behavior: 'smooth',
                            });
                            isViewingOldMessages = false;
                        "
                    >Scroll down</span> to view latest messages.
                </div>

                <!-- Messages -->
                <div
                    class="p-4 overflow-y-auto space-y-4 h-[500px] bg-gray-950/50"
                    id="messages-container"
                    x-data="{
                        scrollToLatestMessage() {
                            // Use animation frame to scroll after the DOM is completely loaded.
                            requestAnimationFrame(() => {
                                $nextTick(() => {
                                    $el.scrollTop = $el.scrollHeight;
                                });
                            });
                        },
                        updateTime() {
                            $nextTick(() => {
                                const messages = document.querySelectorAll('.message-elem');

                                for (const message of messages) {
                                    const dateElem = message.querySelector('.date-elem');
                                    const createdAt = new Date(dateElem.getAttribute('data-created-at'));
                                    const now = new Date();
                                    const diffInSeconds = Math.floor((now - createdAt) / 1000);
                                    let timeAgo = '';

                                    if (diffInSeconds < 10) {
                                        timeAgo = 'now';
                                    } else if (diffInSeconds < 60) {
                                        timeAgo = 'a moment ago';
                                    } else if (diffInSeconds < 3600) {
                                        const minutes = Math.floor(diffInSeconds / 60);
                                        timeAgo = minutes === 1 ? 'a minute ago' : `${minutes} minutes ago`;
                                    } else if (diffInSeconds < 86400) {
                                        const hours = Math.floor(diffInSeconds / 3600);
                                        timeAgo = hours === 1 ? 'an hour ago' : `${hours} hours ago`;
                                    } else {
                                        const days = Math.floor(diffInSeconds / 86400);
                                        timeAgo = days === 1 ? 'a day ago' : `${days} days ago`;
                                    }

                                    dateElem.textContent = `${timeAgo}`;
                                }
                            });
                        }
                    }"
                    x-init="
                        $nextTick(() => {
                            scrollToLatestMessage();
                            updateTime();
                        });
                        updateTime();
                        setInterval(() => {
                            updateTime();
                        }, 1000);
                    "
                    x-on:scroll="checkIfViewingOldMessages($el);"
                    @updated-messages.window="
                        scrollToLatestMessage();
                        updateTime();
                        checkIfViewingOldMessages();
                    "
                >
                    @if ($messages->isEmpty())
                        <div class="w-full h-full flex flex-col items-center justify-center">
                            <h1 class="text-xl">
                                There are currently no messages to display.
                            </h1>
                            <h3 class="text-md">
                                Be the first one to
                                <span
                                    class="text-blue-500 hover:text-blue-700 underline cursor-pointer font-semibold"
                                    @click="
                                        document.querySelector('#chatInputBox').focus();
                                    "
                                >start</span> the chat!
                            </h3>
                        </div>
                    @else
                        @foreach ($messages as $message)
                            <div
                                class="flex flex-row items-start message-elem py-4 w-full rounded-md relative hover:bg-gray-700 hover:bg-opacity-20 transition-all duration-100"
                                wire:key="{{ $message->id }}"
                                x-data="{
                                    mouseOverMessage: false,
                                    messageUserId: {{ $message->user->id }},
                                    messageContent: '{{ $message->content }}',
                                    messageEditedContent: '{{ $message->content }}',
                                    isBeingEdited: false,

                                    enableEditMode() {
                                        this.isBeingEdited = true;
                                        this.$nextTick(() => this.$refs.editInput.focus());
                                    },
                                    disableEditMode() {
                                        this.isBeingEdited = false;
                                        this.messageEditedContent = this.messageContent;
                                    },
                                    editMessage() {
                                        this.isBeingEdited = false;

                                        if (this.messageContent !== this.messageEditedContent) {
                                            $wire.editMessage({{ $message->id }}, this.messageContent, this.messageEditedContent);

                                            this.messageContent = this.messageEditedContent;
                                        }
                                    },
                                }"
                                @mouseenter="
                                    if (messageUserId === {{ auth()->user()->id }}) {
                                        mouseOverMessage = true;
                                    }
                                "
                                @mouseleave="mouseOverMessage = false;"
                            >
                                <!-- Message action buttons -->
                                @if ($message->user->id === auth()->user()->id)
                                    <div
                                        class="flex flex-row absolute top-4 right-4 rounded-md p-1"
                                        x-show="mouseOverMessage"
                                        x-cloak
                                    >
                                        <!-- Edit Button -->
                                        <button
                                            class="rounded-sm p-1 h-5 w-5 hover:text-blue-400"
                                            title="Edit message"
                                            x-on:click="enableEditMode()"
                                            x-show="!isBeingEdited"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="size-3">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>

                                        <!-- Delete Button -->
                                        <button
                                            class="rounded-sm p-1 h-5 w-5 hover:text-blue-400"
                                            title="Delete message"
                                            wire:click="deleteMessage({{ $message->id }})"
                                            x-on:click="resetInactivityTimer()"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="size-3">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                @endif

                                <!-- Message user profile photo -->
                                <img
                                    class="size-8 rounded-full object-cover mx-4"
                                    src="{{ $message->user->profile_photo_path ? Storage::url($message->user->profile_photo_path) : $message->user->getDefaultProfilePictureUrl() }}"
                                    alt=""
                                >

                                <!-- Message information -->
                                <div class="w-4/5">
                                    <div class="flex flex-row items-baseline">
                                        <p class="text-base">{{ $message->user->name }}</p>
                                        <p
                                            class="date-elem text-xs text-gray-400 mx-2"
                                            data-created-at="{{ $message->created_at->toISOString() }}"
                                            wire:ignore
                                        ></p>
                                    </div>

                                    <!-- Message content -->
                                    <div
                                        class=""
                                        x-show="!isBeingEdited"
                                    >
                                        <p class="text-gray-300">
                                            {{ $message->content }}
                                            
                                            @if ($message->is_edited)
                                                <span class="text-sm text-gray-600">
                                                    (edited)
                                                </span>
                                            @endif
                                        </p>

                                        @foreach ($message->files ?? [] as $file)
                                            <!-- Image files -->
                                            @if (str_starts_with($file->file_type, 'image/'))
                                                <img
                                                    class="rounded-lg my-2"
                                                    src="{{ Storage::url($file->uploaded_file_path) }}"
                                                >
                                            @endif
                                        @endforeach
                                    </div>

                                    <!-- Edit message mode -->
                                    <div
                                        class="space-y-2"
                                        x-show="isBeingEdited"
                                        x-cloak
                                    >
                                        <input
                                            type="text"
                                            class="w-full p-2 border-0 rounded text-gray-200 bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                            x-model="messageEditedContent"
                                            x-ref="editInput"
                                            @keydown.escape="disableEditMode()"
                                            @keydown.enter="editMessage()"
                                            @blur="disableEditMode()"
                                        >
                                        <div class="flex flex-row justify-end">
                                            <button
                                                class="w-20 p-1 mr-2 rounded focus:outline-none bg-blue-500 hover:bg-blue-400 focus:bg-blue-400"
                                                @click="editMessage()"
                                            >
                                                Save
                                            </button>
                                            <button
                                                class="w-20 p-1 rounded focus:outline-none bg-gray-500 hover:bg-gray-400 focus:bg-gray-400"
                                                @click="disableEditMode()"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- Uploaded images temp preview -->
                @if ($uploadedFiles)
                    <div class="flex flex-row absolute z-10 bottom-0 left-0 w-full bg-gray-800 p-2">
                        @foreach ($uploadedFiles as $uploadedFile)
                            <div
                                class="text-center relative px-2 mx-1 rounded-lg bg-gray-900"
                                wire:key="{{ $uploadedFile->getFilename() }}"
                            >
                                <button
                                    class="absolute -right-2 -top-2 z-10 border border-gray-700 rounded-lg p-1 text-red-600 bg-gray-900 hover:text-red-500 hover:bg-gray-800"
                                    title="Delete file"
                                    wire:click="deleteUploadedFilePreview('{{ $uploadedFile->getFilename() }}')"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="size-4">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                                <img
                                    src="{{ $uploadedFile->temporaryUrl() }}"
                                    alt="Preview"
                                    class="my-2 w-32 h-32 object-contain bg-gray-900"
                                >
                                <div class="my-2">
                                    {{ $uploadedFile->getClientOriginalName() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div
                class="mt-4 flex items-center rounded-lg relative bg-gray-700"
                x-data="{
                    chatInputBox: document.querySelector('#chatInputBox'),
                    chatMessage: '',
                    maxChatLength: 5000,

                    sendMessage() {
                        this.chatMessage = this.chatMessage.trim();

                        $wire.sendMessage(this.chatMessage);
                        
                        this.chatMessage = '';
                    },
                    addUploadedFile() {
                        const files = this.$event.clipboardData.items;
                        const uploadedFiles = [];

                        for (let i = 0; i < files.length; i++) {
                            const file = files[i];

                            if (file?.kind === 'file' || file.type.startsWith('image/')) {
                                uploadedFiles.push(file.getAsFile());
                            }
                        }

                        if (uploadedFiles.length > 0) {
                            $wire.uploadMultiple('newFileUploads', uploadedFiles,
                                () => {
                                    // Success
                                },
                                error => {
                                    console.error('upload failed: ', error);
                                }
                            );
                        }
                    },
                }"
            >
                <input
                    type="text"
                    maxlength="maxChatLength"
                    id="chatInputBox"
                    class="flex-1 p-4 pr-20 bg-inherit rounded-lg border-0 text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    placeholder="Type a message..."
                    x-data="{
                        userTyping: false,
                        typingTimeoutTime: 5000,
                        typingTimeoutHandler: null,

                        resetTimeoutOnInput() {
                            clearTimeout(this.typingTimeoutHandler);
                            this.typingTimeoutHandler = setTimeout(() => this.updateUserNotTyping(), this.typingTimeoutTime)
                        },
                        updateUserTyping() {
                            this.userTyping = true;
                            $wire.typing('{{ auth()->user()->name }}', true);
                        },
                        updateUserNotTyping() {
                            this.userTyping = false;
                            $wire.typing('{{ auth()->user()->name }}', false);
                        },
                    }"
                    x-model="chatMessage"
                    @input="
                        if (!userTyping) {
                            chatMessage = $el.value;

                            updateUserTyping();
                        }

                        resetTimeoutOnInput();
                        resetInactivityTimer();
                    "
                    @keydown.enter="
                        if (userTyping) {
                            updateUserNotTyping();
                        }

                        sendMessage();
                    "
                    @paste="addUploadedFile()"
                >
                <button
                    title="Send message"
                    class="focus:outline-none p-2 mx-4 absolute right-0 rounded-lg bg-blue-500 hover:bg-blue-400 focus:bg-blue-400"
                    @click="sendMessage()"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                    </svg>
                </button>
            </div>
            <div
                id="is-typing"
                class="absolute z-10 -bottom-6 left-0 w-full py-1 text-xs text-gray-400 flex"
            >
                @php
                    // Filter out the local user's name and re-index the array
                    $usersTyping = array_values(array_filter($usersCurrentlyTyping, fn ($user) => $user !== auth()->user()->name));
                    $typingCount = count($usersTyping);
                @endphp

                @if ($typingCount === 1)
                    <span>
                        <strong>{{ $usersTyping[0] }}</strong> is typing...
                    </span>
                @elseif ($typingCount === 2)
                    <span>
                        <strong>{{ $usersTyping[0] }}</strong> and <strong>{{ $usersTyping[1] }}</strong> are typing...
                    </span>
                @elseif ($typingCount > 2)
                    <span>
                        Multiple people are typing...
                    </span>
                @endif
            </div>
        </section>

        <!-- Users List -->
        <aside class="w-1/6 ml-10 rounded-lg">
            <h2 class="text-xl font-semibold text-blue-500 mb-4">Online users ({{ $onlineUsersNumber }})</h2>
            <input
                type="text"
                class="mb-4 p-2 w-full bg-inherit rounded-lg border-0 text-gray-200"
                placeholder="Search users..."
                wire:model.live="searchUsers"
            >
            @if ($users->isEmpty())
                <p class="">No users found</p>
            @else
                <ul class="">
                    @foreach ($users as $user)
                        <li
                            class="flex items-center justify-between py-2"
                            wire:key="{{ $user->id }}"
                        >
                            <div class="flex flex-row items-center">
                                
                                <img
                                    class="size-8 rounded-full object-cover"
                                    src="{{ $user->profile_photo_path ? Storage::url($user->profile_photo_path) : $user->getDefaultProfilePictureUrl() }}"
                                    alt=""
                                >
                                <span class="mx-2">
                                    {{ $user->name }}
                                </span>
                            </div>

                            <!-- Green button -->
                            <span
                                class="w-2 h-2 rounded-full ml-2"
                                title="{{ $user->activity_status }}"
                                style="background-color: {{ $user->activity_status === 'active' ? 'rgb(34, 197, 94)' : 'rgb(254, 240, 138)' }};"
                            ></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>
    <livewire:toast-notification-component></livewire:toast-notification-component>
    @script
        <script>
            // Websocket connection events
            Echo.join('chatroom')
                .joining(user => {
                    $wire.dispatchSelf('user-connected', {
                        userId: user.id,
                    });

                    toast(user.name + " has joined the chatroom", {
                        type: 'info',
                        position: 'top-center',
                    });
                })
                .leaving(user => {
                    $wire.dispatchSelf('user-disconnected', {
                        userId: user.id,
                    });

                    toast(user.name + " has left the chatroom", {
                        type: 'info',
                        position: 'top-center',
                    });
                });
        </script>
    @endscript
</div>
