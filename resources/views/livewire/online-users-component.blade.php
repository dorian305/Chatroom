<div
    x-data="{
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

    {{-- Search --}}
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

    {{-- Users Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <template x-if="loading">
            <p class="col-span-full text-center text-gray-400">
                Loading online users...
            </p>
        </template>
        
        <template x-if="!loading && filtered.length === 0">
            <p class="col-span-full text-center text-gray-400">
                <span x-show="search.trim()">No users found matching your search.</span>
            </p>
        </template>

        <template x-for="user in filtered" :key="user.id">
            <div class="bg-gray-800 hover:bg-gray-700 transition-colors rounded-lg overflow-hidden shadow-md">
                {{-- Profile Photo --}}
                <img
                    :src="user.profile_photo_url"
                    :alt="`${user.name}'s avatar`"
                    class="w-full h-32 object-cover"
                    loading="lazy"
                />

                {{-- Name & Status --}}
                <div class="p-4 flex flex-col items-center text-center">
                    <p class="text-lg font-medium text-white mb-1" x-text="user.name"></p>
                </div>
            </div>
        </template>
    </div>
</div>