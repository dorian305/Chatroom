<div class="flex items-center justify-center">
    <div class="w-full flex bg-gray-800 text-white rounded-lg overflow-hidden">
        <!-- Users List -->
        <aside class="w-1/4 p-4 border-r border-gray-700">
            <h2 class="text-xl font-semibold text-indigo-400 mb-4">Online users ({{ $users->count() }})</h2>
            <ul class="space-y-2">
                @foreach ($users as $user)
                    <li
                        class="flex items-center p-2"
                        wire:key="{{ $user->id }}"
                    >
                        <!-- Green button -->
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2" title="Online"></span>
                        <span
                            wire:model="username"
                        >
                            {{ $user->name }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </aside>


        <!-- Chatbox -->
        <section class="flex-1 flex flex-col">

            <!-- Messages -->
            <div
                class="p-4 overflow-y-auto space-y-4"
                id="messages-container"
                style="height: 500px"
                x-data="{
                    userViewingOlderMessages: false,

                    scrollToLatestMessage() {
                        $nextTick(() => $el.scrollTop = $el.scrollHeight);
                    },
                    updateTime() {
                        const now = new Date();
                        const diffInSeconds = Math.floor((now - this.createdAt) / 1000);
                        const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

                        if (diffInSeconds < 30) {
                            this.timeAgo = 'now';
                        } else if (diffInSeconds < 60) {
                            this.timeAgo = 'a moment ago';
                        } else if (diffInSeconds < 3600) {
                            this.timeAgo = rtf.format(-Math.floor(diffInSeconds / 60), 'minutes');
                        } else if (diffInSeconds < 86400) {
                            this.timeAgo = rtf.format(-Math.floor(diffInSeconds / 3600), 'hours');
                        } else {
                            this.timeAgo = rtf.format(-Math.floor(diffInSeconds / 86400), 'days');
                        }
                    },
                }"
                @updated-messages.window="scrollToLatestMessage();"
                x-init="scrollToLatestMessage();"
            >
                @foreach ($messages as $message)
                    <div
                        class="p-3 w-full rounded-md relative {{ $message->user->id === auth()->user()->id ? 'bg-gray-700 text-left' : 'text-right' }}"
                        x-data="{
                            updateInterval: null,
                            timeAgo: '',
                            createdAt: new Date('{{ $message->created_at->toISOString() }}'),
                        }"
                        x-init="
                            updateTime();
                            updateInterval = setInterval(() => {
                                updateTime();
                                console.log('updated-time');
                            }, 1000);
                        "
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
                        <p class="text-xs text-gray-300" id="message-date-elem"></p>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-gray-700 flex items-center">
                <input
                    type="text"
                    class="flex-1 p-3 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 text-gray-400"
                    placeholder="Type a message..."
                    wire:keydown.enter="sendMessage"
                    wire:model="message"
                >
                <button
                    class="ml-3 bg-indigo-500 hover:bg-indigo-600 px-4 py-2 rounded-lg"
                    wire:click="sendMessage"
                >Send</button>
            </div>
            <div id="is-typing">
                
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

            // Listen for new messages event
            $wire.on('updated-messages', () => {
                window.dispatchEvent(new CustomEvent('updated-messages'));
            });
        </script>
    @endscript
</div>
