class OnlineUsersManager {
    constructor() {
        this.onlineUsers = [];

        this.init();
    }

    init() {
        Echo.join('online-users')
            .here(users => {
                this.onlineUsers = [...users];

                console.log(this.getUsers());
            })
            .joining(user => {
                // Check if user already exists (prevent duplicates)
                const existingUserIndex = this.onlineUsers.findIndex(u => u.id === user.id);
                
                if (existingUserIndex === -1) {
                    this.onlineUsers.push(user);
                }

                console.log(this.getUsers());
            })
            .leaving(user => {
                this.onlineUsers = this.onlineUsers.filter(u => u.id !== user.id);

                console.log(this.getUsers());
            });
    }

    getUsers() {
        return [...this.onlineUsers];
    }

    getCount() {
        return this.onlineUsers.length;
    }

    isUserOnline(userId) {
        return this.onlineUsers.some(user => user.id === userId);
    }
}

window.onlineUsersManager = new OnlineUsersManager();