<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// >>> MYRA v2.5 [B] START
// Per-user dashboard channel. There is no shared dashboard channel, so a tenant
// boundary cannot be crossed by a mis-declared topic. The predicate lives in
// WidgetSignal so the channel test asserts the real implementation.
Broadcast::channel(
    'myra.dashboard.{id}',
    fn ($user, $id) => \App\Admin\Realtime\WidgetSignal::authorizesChannel($user, $id),
);
// <<< MYRA v2.5 [B] END
