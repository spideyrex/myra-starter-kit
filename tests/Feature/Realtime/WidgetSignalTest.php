<?php

namespace Tests\Feature\Realtime;

use App\Admin\Realtime\Jobs\EmitWidgetSignal;
use App\Admin\Realtime\Jobs\FanOutWidgetSignal;
use App\Admin\Realtime\WidgetDataChanged;
use App\Admin\Realtime\WidgetSignal;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WidgetSignalTest extends TestCase
{
    public function test_nothing_is_dispatched_while_realtime_is_off(): void
    {
        config()->set('myra.realtime.enabled', false);

        $user = $this->actingAsSuperAdmin();

        Bus::fake();

        WidgetSignal::emitTo($user, 'users');
        WidgetSignal::emit('users');

        Bus::assertNothingDispatched();
    }

    public function test_a_truthy_string_flag_is_not_enough(): void
    {
        // The `=== true` comparison is the guard: a mis-set env string must not
        // switch a live site into broadcasting.
        config()->set('myra.realtime.enabled', '1');

        $user = $this->actingAsSuperAdmin();

        Bus::fake();

        WidgetSignal::emitTo($user, 'users');

        Bus::assertNothingDispatched();
    }

    public function test_emit_to_queues_one_job_for_the_acting_user(): void
    {
        config()->set('myra.realtime.enabled', true);

        $user = $this->actingAsSuperAdmin();

        Bus::fake();

        WidgetSignal::emitTo($user, 'users');

        Bus::assertDispatched(
            EmitWidgetSignal::class,
            fn (EmitWidgetSignal $job) => $job->userId === (int) $user->id && $job->topics === ['users'],
        );
    }

    public function test_a_burst_collapses_to_one_job_per_topic_and_user(): void
    {
        config()->set('myra.realtime.enabled', true);

        $user = $this->actingAsSuperAdmin();

        Bus::fake();
        Cache::flush();

        foreach (range(1, 25) as $ignored) {
            WidgetSignal::emitTo($user, 'users');
        }

        Bus::assertDispatchedTimes(EmitWidgetSignal::class, 1);
    }

    public function test_emit_queues_the_audience_walk_instead_of_running_it_in_the_request(): void
    {
        config()->set('myra.realtime.enabled', true);

        $actor = $this->actingAsSuperAdmin();

        // Three more users who WOULD pass the permission check. If emit() still
        // walked the table synchronously there would be four signals, not one.
        User::factory()->count(3)->create()->each(fn (User $u) => $u->assignRole('super-admin'));

        Bus::fake();
        Cache::flush();

        WidgetSignal::emit('users');

        Bus::assertDispatchedTimes(EmitWidgetSignal::class, 1);
        Bus::assertDispatched(
            EmitWidgetSignal::class,
            fn (EmitWidgetSignal $job) => $job->userId === (int) $actor->id,
        );

        Bus::assertDispatchedTimes(FanOutWidgetSignal::class, 1);
        Bus::assertDispatched(
            FanOutWidgetSignal::class,
            fn (FanOutWidgetSignal $job) => $job->topics === ['users'],
        );
    }

    public function test_the_fan_out_job_signals_only_users_who_may_see_the_report(): void
    {
        config()->set('myra.realtime.enabled', true);

        $admin = $this->actingAsSuperAdmin();
        $stranger = $this->makeUser();

        Bus::fake();
        Cache::flush();

        (new FanOutWidgetSignal(['users']))->handle();

        Bus::assertDispatched(
            EmitWidgetSignal::class,
            fn (EmitWidgetSignal $job) => $job->userId === (int) $admin->id,
        );
        Bus::assertNotDispatched(
            EmitWidgetSignal::class,
            fn (EmitWidgetSignal $job) => $job->userId === (int) $stranger->id,
        );
    }

    public function test_the_fan_out_job_is_inert_when_realtime_is_off(): void
    {
        config()->set('myra.realtime.enabled', false);

        $this->actingAsSuperAdmin();

        Bus::fake();

        (new FanOutWidgetSignal(['users']))->handle();

        Bus::assertNothingDispatched();
    }

    public function test_an_unknown_topic_shape_is_dropped(): void
    {
        config()->set('myra.realtime.enabled', true);

        $user = $this->actingAsSuperAdmin();

        Bus::fake();

        WidgetSignal::emitTo($user, '', '  ', 'not a topic!');

        Bus::assertNothingDispatched();
    }

    public function test_the_broadcast_payload_is_a_signal_and_not_data(): void
    {
        $event = new WidgetDataChanged(7, ['users', 'activity']);

        $this->assertSame('private-myra.dashboard.7', $event->broadcastOn()->name);
        $this->assertSame('widget.data.changed', $event->broadcastAs());
        $this->assertSame(['topics', 'at'], array_keys($event->broadcastWith()));
        $this->assertSame(['users', 'activity'], $event->broadcastWith()['topics']);
        $this->assertIsInt($event->broadcastWith()['at']);
    }

    public function test_the_job_is_inert_when_realtime_is_off(): void
    {
        config()->set('myra.realtime.enabled', false);

        // No broadcaster is configured on this deployment; the guard must return
        // before broadcast() is ever reached.
        (new EmitWidgetSignal(1, ['users']))->handle();

        $this->assertTrue(true);
    }

    public function test_a_dead_socket_never_fails_the_worker(): void
    {
        config()->set('myra.realtime.enabled', true);

        // There is no broadcasting connection here, so broadcast() throws. The
        // job must swallow and report it rather than retry-storm.
        (new EmitWidgetSignal(1, ['users']))->handle();

        $this->assertTrue(true);
    }
}
