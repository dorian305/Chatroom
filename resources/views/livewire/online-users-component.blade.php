<div
    x-data="{
        localUserId: {{ auth()->user()->id }},
        users: [],
        search: '',
        loading: true,

        get filtered() {
            if (!this.search.trim()) return this.users;

            return this.users.filter(u =>
                u.name.toLowerCase().includes(this.search.toLowerCase())
            );
        },
    }"
    x-init="
        // Wait for the manager to be ready with initial users
        window.onlineUsersManager
            .getUsersWhenReady()
            .then(initialUsers => {
                console.log('Initial users received:', initialUsers);

                users = initialUsers;
                loading = false;
            }).catch(error => {
                console.error('Error getting initial users:', error);

                loading = false;
            });
    "
    x-on:online-users-updated.window="
        users = $event.detail;
    "
    class="space-y-4"
>

    <input
        type="text"
        x-model="search"
        placeholder="Search users…"
        class="w-full px-3 py-2 bg-gray-800 text-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
    />

    <template x-if="search.trim()">
        <p class="text-gray-400 italic">
            Searching for: <strong><span x-text="search"></span></strong>
        </p>
    </template>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 justify-items-center">
        <template x-if="loading">
            <p class="text-gray-400">
                Loading online users...
            </p>
        </template>
        
        <template x-if="!loading && filtered.length === 0">
            <p class="col-span-full text-gray-400">
                <span x-show="search.trim()">No users found matching your search.</span>
            </p>
        </template>

        <template x-for="user in filtered" :key="user.id">
            <div class="overflow-hidden flex flex-col items-center text-center p-4">
                <img
                :src="user.profile_photo_url"
                :alt="`${user.name}'s avatar`"
                class="w-32 h-32 rounded-full object-cover"
                loading="lazy"
                />

                <p class="text-lg font-medium text-white mt-4 flex items-baseline">
                <!-- Always show the name -->
                <span x-text="user.name"></span>

                <!-- Conditionally render the “(you)” with its own styling -->
                <template x-if="user.id === localUserId">
                    <span class="text-sm text-gray-400 ml-2">(you)</span>
                </template>
                </p>
            </div>
            </template>
    </div>
</div>