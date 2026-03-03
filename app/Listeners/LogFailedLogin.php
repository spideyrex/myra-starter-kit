<?php

namespace App\Listeners;

use App\Models\AuthLog;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        AuthLog::create([
            'user_id' => $event->user?->id,
            'email' => $event->credentials['email'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => false,
        ]);
    }
}
