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
    <div class="w-full flex bg-gray-800 text-white rounded-lg overflow-hidden">
		
        <!-- Users List -->
        <aside class="w-[200px] p-4 border-r border-gray-700">
            <h2 class="text-xl font-semibold text-indigo-400 mb-4">Online users ({{ $users->count() }})</h2>
            <ul class="space-y-2">
                @foreach ($users as $user)
                    <li
                        class="flex items-center p-2"
                        wire:key="{{ $user->id }}"
                    >
                        <!-- Green button -->
                        <span
                            class="w-2 h-2 rounded-full mr-2"
                            title="{{ $user->activity_status }}"
                            style="background-color: {{ $user->activity_status === 'active' ? 'rgb(34, 197, 94)' : 'rgb(254, 240, 138)' }};"
                        ></span>
                        <span wire:model="username">
                            {{ $user->name }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </aside>


        <!-- Chatbox -->
        <section
            class="flex-1 flex flex-col relative"
            x-data="{
                userIsViewingOldMessages: false,
            }"
        >
            
            <!-- Notification for when you're looking at old messages -->
            <div
                x-show="userIsViewingOldMessages"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute z-10 top-0 left-0 w-full bg-gray-500 bg-opacity-50 text-center py-2 text-sm"
            >
                You are viewing older messages.
                <span
                    class="text-blue-500 hover:text-blue-700 underline cursor-pointer font-semibold"
                    x-on:click="
                        const container = document.querySelector('#messages-container');
                        container.scrollTo({
                            top: container.scrollHeight,
                            behavior: 'smooth',
                        });
                        userIsViewingOldMessages = false;
                    "
                >
                    Scroll down
                </span>
                to view latest messages.
            </div>

            <!-- Loading messages overlay -->
            <!-- <div
                class="flex flex-col items-center justify-center absolute z-10 top-0 left-0 w-full h-full bg-gray-800 text-gray-400"
                id="loading-messages-overlay"
            >
                <div class="grid min-h-[140px] w-full place-items-center overflow-x-scroll rounded-lg p-6 lg:overflow-visible">
                    <svg class="w-16 h-16 animate-spin text-gray-900" viewBox="0 0 64 64" fill="none"
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24">
                        <path
                        d="M32 3C35.8083 3 39.5794 3.75011 43.0978 5.20749C46.6163 6.66488 49.8132 8.80101 52.5061 11.4939C55.199 14.1868 57.3351 17.3837 58.7925 20.9022C60.2499 24.4206 61 28.1917 61 32C61 35.8083 60.2499 39.5794 58.7925 43.0978C57.3351 46.6163 55.199 49.8132 52.5061 52.5061C49.8132 55.199 46.6163 57.3351 43.0978 58.7925C39.5794 60.2499 35.8083 61 32 61C28.1917 61 24.4206 60.2499 20.9022 58.7925C17.3837 57.3351 14.1868 55.199 11.4939 52.5061C8.801 49.8132 6.66487 46.6163 5.20749 43.0978C3.7501 39.5794 3 35.8083 3 32C3 28.1917 3.75011 24.4206 5.2075 20.9022C6.66489 17.3837 8.80101 14.1868 11.4939 11.4939C14.1868 8.80099 17.3838 6.66487 20.9022 5.20749C24.4206 3.7501 28.1917 3 32 3L32 3Z"
                        stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path
                        d="M32 3C36.5778 3 41.0906 4.08374 45.1692 6.16256C49.2477 8.24138 52.7762 11.2562 55.466 14.9605C58.1558 18.6647 59.9304 22.9531 60.6448 27.4748C61.3591 31.9965 60.9928 36.6232 59.5759 40.9762"
                        stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                        </path>
                    </svg>
                </div>  
                Loading messages...
            </div> -->

            <!-- Messages -->
            <div
                class="p-4 overflow-y-auto space-y-4 h-[500px]"
                id="messages-container"
                x-data="{
                    offsetWhichTriggersNotificationDivAboutViewingOlderMessages: 100,

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

                                dateElem.textContent = timeAgo;
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
                x-on:scroll="userIsViewingOldMessages = $el.scrollTop + $el.clientHeight + offsetWhichTriggersNotificationDivAboutViewingOlderMessages < $el.scrollHeight;"
                @updated-messages.window="
                    scrollToLatestMessage();
                    updateTime();
                "
            >
                @foreach ($messages as $message)
                    <div
                        class="message-elem p-3 w-full rounded-md relative {{ $message->user->id === auth()->user()->id ? 'bg-gray-700 text-left' : 'text-right' }}"
                    >
                        @if ($message->user->id === auth()->user()->id)
                            <!-- Delete Button -->
                            <button
                                class="text-xs absolute top-2 right-2 text-gray-400 hover:text-gray-200 rounded-sm hover:bg-gray-600 p-1"
                                title="Delete message"
                                wire:click="deleteMessage({{ $message->id }})"
                            >
                                X
                            </button>
                        @endif
                        <p class="text-sm text-gray-400">{{ $message->user->name }}</p>
                        <p>{{ $message->content }}</p>
                        <p
                            class="date-elem text-xs text-gray-300"
                            data-created-at="{{ $message->created_at->toISOString() }}"
                            wire:ignore
                        ></p>
                    </div>
                @endforeach
            </div>

            <div class="px-4 py-6 border-t border-gray-700 flex items-center relative">
                <input
                    type="text"
                    class="flex-1 p-3 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 text-gray-200"
                    placeholder="Type a message..."
                    wire:model="message"
                    x-data="{
                        userTyping: false,
                        typingTimeoutTime: 1000,
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
                    x-on:keydown="
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
                    class="ml-3 bg-indigo-500 hover:bg-indigo-600 px-4 py-2 rounded-lg"
                    wire:click="sendMessage"
                >Send</button>
            </div>
            <div
                id="is-typing"
                class="absolute z-10 bottom-0 left-0 w-full px-6 py-1 text-xs text-gray-400 flex"
                wire:model="usersCurrentlyTyping"
            >
                @php
                    // Filter out the local user's name and re-index the array
                    $usersTyping = array_filter($usersCurrentlyTyping, fn ($user) =>
                        $user !== auth()->user()->name
                    );
                    $usersTyping = array_values($usersTyping);
                @endphp
                @if (count($usersTyping) > 0)
                    <span>{{ implode(', ', $usersTyping) }} typing...</span>
                @endif
            </div>
        </section>
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
