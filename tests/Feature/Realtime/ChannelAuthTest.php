<?php

namespace Tests\Feature\Realtime;

use App\Admin\Realtime\WidgetSignal;
use Tests\TestCase;

/**
 * `myra.dashboard.{id}` is per-user, so a tenant boundary cannot be crossed by
 * a mis-declared topic. routes/channels.php delegates to the predicate asserted
 * here, which keeps one implementation rather than a copy that can drift.
 */
class ChannelAuthTest extends TestCase
{
    public function test_a_user_may_only_join_their_own_dashboard_channel(): void
    {
        $one = $this->makeUser();
        $two = $this->makeUser();

        $this->assertTrue(WidgetSignal::authorizesChannel($one, $one->id));
        $this->assertFalse(WidgetSignal::authorizesChannel($one, $two->id));
        $this->assertTrue(WidgetSignal::authorizesChannel($two, $two->id));
        $this->assertFalse(WidgetSignal::authorizesChannel($two, $one->id));
    }

    public function test_a_guest_or_a_junk_id_is_refused(): void
    {
        $user = $this->makeUser();

        $this->assertFalse(WidgetSignal::authorizesChannel(null, $user->id));
        $this->assertFalse(WidgetSignal::authorizesChannel($user, 'nope'));
        $this->assertFalse(WidgetSignal::authorizesChannel($user, ''));
        $this->assertFalse(WidgetSignal::authorizesChannel($user, null));
    }

    public function test_the_channel_is_actually_registered(): void
    {
        $source = (string) file_get_contents(base_path('routes/channels.php'));

        $this->assertStringContainsString("'myra.dashboard.{id}'", $source);
        $this->assertStringContainsString('WidgetSignal::authorizesChannel', $source);
    }
}
