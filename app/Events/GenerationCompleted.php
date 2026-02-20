<?php

namespace App\Events;

use App\Models\Generation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GenerationCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Generation $generation,
        public int $userId,
        public ?string $resultUrl = null
    ) {
        // Fallback: If no resultUrl passed but model has it, prepare it
        if (!$this->resultUrl) {
            $this->generation->prepareVideoUrl();
            $this->resultUrl = $this->generation->output_data['result'] ?? null;
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'generation.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'generation_id' => $this->generation->id,
            'status' => $this->generation->status,
            'model_name' => $this->generation->aiModel->name ?? 'Unknown',
            'model_slug' => $this->generation->aiModel->slug ?? '',
            'thumbnail_url' => $this->generation->thumbnail_url,
            'result' => $this->resultUrl,
        ];
    }
}
