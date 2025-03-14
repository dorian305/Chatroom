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
            class="flex flex-col w-full relative"
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
                <!-- Notification for when you're looking at old messages -->
                <div
                    class="absolute z-10 top-0 left-0 w-full bg-gray-500 bg-opacity-50 text-center py-2 text-sm"
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
                    >
                        Scroll down
                    </span>
                    to view latest messages.
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

                                    dateElem.textContent = `(${timeAgo})`;
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
                    "
                >
                    @foreach ($messages as $message)
                        <div
                            class="flex flex-row items-start message-elem pb-4 w-full rounded-md relative"
                        >
                            @if ($message->user->id === auth()->user()->id)
                                <!-- Delete Button -->
                                <button
                                    class="text-xs absolute top-2 right-2 text-gray-400 hover:text-gray-200 rounded-sm hover:bg-gray-600 p-1"
                                    title="Delete message"
                                    wire:click="deleteMessage({{ $message->id }})"
                                    x-on:click="checkIfViewingOldMessages()"
                                >
                                    X
                                </button>
                            @endif
                            <img
                                class="size-8 rounded-full object-cover mx-4"
                                src="{{ $message->user->profile_photo_path ? Storage::url($message->user->profile_photo_path) : $message->user->getDefaultProfilePictureUrl() }}"
                                alt=""
                            >
                            <div class="">
                                <div class="flex flex-row items-center">
                                    <p class="text-base text-gray-400">{{ $message->user->name }}</p>
                                    <p
                                        class="date-elem text-xs text-gray-300 mx-2"
                                        data-created-at="{{ $message->created_at->toISOString() }}"
                                        wire:ignore
                                    ></p>
                                </div>
                                <p>{{ $message->content }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 flex items-center relative h-14 rounded-lg bg-gray-700">
                <input
                    type="text"
                    class="flex-1 w-full p-4 bg-inherit rounded-lg border-0 text-gray-200 h-full focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    placeholder="Type a message..."
                    wire:model="message"
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
                        sendMessage() {
                            $wire.sendMessage();
                        }
                    }"
                    x-on:input="
                        if (!userTyping) {
                            updateUserTyping();
                        }

                        resetTimeoutOnInput();
                        resetInactivityTimer();
                        
                    "
                    x-on:keydown.enter="
                        if (userTyping) {
                            updateUserNotTyping();
                        }

                        sendMessage();
                    "
                >
                <button
                    class="bg-indigo-500 hover:bg-indigo-600 focus:bg-indigo-600 focus:outline-none px-4 rounded-r-lg h-full w-24 absolute right-0"
                    wire:click="sendMessage"
                >Send</button>
            </div>
            <div
                id="is-typing"
                class="absolute z-10 -bottom-6 left-0 w-full py-1 text-xs text-gray-400 flex"
                wire:model="usersCurrentlyTyping"
            >
                @php
                    // Filter out the local user's name and re-index the array
                    $usersTyping = array_values(array_filter($usersCurrentlyTyping, fn ($user) => $user !== auth()->user()->name));
                    $typingCount = count($usersTyping);
                @endphp

                @if ($typingCount === 1)
                    <span>{{ $usersTyping[0] }} is typing...</span>
                @elseif ($typingCount === 2)
                    <span>{{ $usersTyping[0] }} and {{ $usersTyping[1] }} are typing...</span>
                @elseif ($typingCount > 2)
                    <span>Multiple people are typing...</span>
                @endif
            </div>
        </section>

        <!-- Users List -->
        <aside class="w-1/4 ml-8 rounded-lg">
            <h2 class="text-xl font-semibold text-indigo-400 mb-4">Online users ({{ $users->count() }})</h2>
            <ul class="">
                @foreach ($users as $user)
                    <li
                        class="flex items-center py-2"
                        wire:key="{{ $user->id }}"
                    >
                        <img
                            class="size-8 rounded-full object-cover m-2"
                            src="{{ $user->profile_photo_path ? Storage::url($user->profile_photo_path) : $user->getDefaultProfilePictureUrl() }}"
                            alt=""
                        >
                        <span wire:model="username">
                            {{ $user->name }}
                        </span>

                        <!-- Green button -->
                        <span
                            class="w-2 h-2 rounded-full ml-2"
                            title="{{ $user->activity_status }}"
                            style="background-color: {{ $user->activity_status === 'active' ? 'rgb(34, 197, 94)' : 'rgb(254, 240, 138)' }};"
                        ></span>
                    </li>
                @endforeach
            </ul>
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
