<?php

namespace App\Admin\Realtime\Jobs;

use App\Admin\Realtime\WidgetSignal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The audience walk. A write request must never pay for the whole users table,
 * so emit() queues this and the worker does the chunking, the permission checks
 * and the per-user EmitWidgetSignal dispatch.
 */
final class FanOutWidgetSignal implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** @param  string[]  $topics */
    public function __construct(public readonly array $topics) {}

    public function handle(): void
    {
        WidgetSignal::fanOut($this->topics);
    }
}
