<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppExecutionCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public \App\Models\AppExecution $execution
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->execution->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'app.execution.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'status' => $this->execution->status,
            'app_name' => $this->execution->app->name,
            'app_slug' => $this->execution->app->slug,
        ];
    }
}
