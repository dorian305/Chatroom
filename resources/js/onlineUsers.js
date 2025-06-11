class OnlineUsersManager {
    constructor() {
        this.onlineUsers = [];
        this.isInitialized = false;
        
        // Create a promise that resolves when Echo connection is established
        this.readyPromise = new Promise(resolve => {
            this.resolveReady = resolve;
        });
        
        this.init();
    }

    init() {
        Echo.join('online-users')
            .here(users => {
                console.log('Echo here() - Initial users:', users);

                this.onlineUsers = users;
                this.isInitialized = true;

                this.resolveReady(users);
                this.emitUpdate();
            })
            .joining(user => {
                console.log('User joining:', user);
                
                // Check if user is already in the list
                if (!this.onlineUsers.some(u => u.id === user.id)) {
                    this.onlineUsers.push(user);
                    console.log('Updated users after join:', this.onlineUsers);
                    this.emitUpdate();
                } else {
                    console.log('User already in list, skipping');
                }
            })
            .leaving(user => {
                console.log('User leaving:', user);

                const initialLength = this.onlineUsers.length;
                this.onlineUsers = this.onlineUsers.filter(u => u.id !== user.id);

                console.log('Updated users after leave:', this.onlineUsers);
                
                // Only emit if we actually removed someone
                if (this.onlineUsers.length !== initialLength) {
                    this.emitUpdate();
                } else {
                    console.log('No user was removed from list');
                }
            })
            .error(error => {
                console.error('Echo error:', error);
            });
    }

    emitUpdate() {
        console.log('Emitting update with users:', this.onlineUsers);

        const event = new CustomEvent('online-users-updated', {
            detail: [...this.onlineUsers] 
        });

        window.dispatchEvent(event);
    }

    async getUsersWhenReady() {
        await this.readyPromise;
        return [...this.onlineUsers];
    }

    getUsers() {
        return [...this.onlineUsers];
    }

    getCount() {
        return this.onlineUsers.length;
    }

    isUserOnline(userId) {
        return this.onlineUsers.some(u => u.id === userId);
    }

    reconnect() {
        if (this.channel) {
            this.channel.leave();
        }
        this.init();
    }
}

window.onlineUsersManager = new OnlineUsersManager();