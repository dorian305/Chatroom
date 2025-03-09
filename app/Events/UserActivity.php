<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $activityStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, ?string $activityStatus)
    {
        $user = User::findOrFail($userId);
        $user->update([
            'activity_status' => $activityStatus,
        ]);

        $this->userId = $user->id;
        $this->activityStatus = $user->activity_status;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chatroom'),
        ];
    }
}
