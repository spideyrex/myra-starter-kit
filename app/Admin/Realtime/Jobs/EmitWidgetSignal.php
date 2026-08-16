<?php

namespace App\Admin\Realtime\Jobs;

use App\Admin\Realtime\WidgetDataChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Signalling never sits in a write request's critical path. */
final class EmitWidgetSignal implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** @param  string[]  $topics */
    public function __construct(
        public readonly int $userId,
        public readonly array $topics,
    ) {}

    public function handle(): void
    {
        if (config('myra.realtime.enabled') !== true || $this->topics === []) {
            return;
        }

        // A dead socket must never fail a worker loudly or retry-storm: the
        // dashboard degrades to polling, which is the default anyway.
        try {
            $pending = broadcast(new WidgetDataChanged($this->userId, $this->topics));
            // PendingBroadcast dispatches in its destructor — force it INSIDE
            // the try, or a missing broker escapes this handler.
            unset($pending);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
