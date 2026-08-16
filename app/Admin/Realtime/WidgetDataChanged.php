<?php

namespace App\Admin\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A CHANGE SIGNAL, never data. The payload carries report keys and a timestamp;
 * the client refetches through the existing Gate-checked, ownership-scoped
 * widget batch endpoint. Adding a row, an aggregate or a count here would turn
 * a socket into a data channel — do not.
 */
final class WidgetDataChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param  string[]  $topics  Server-minted report keys. NEVER row or aggregate data. */
    public function __construct(
        public readonly int $userId,
        public readonly array $topics,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("myra.dashboard.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'widget.data.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'topics' => array_values($this->topics),
            'at' => now()->getTimestampMs(),
        ];
    }
}
