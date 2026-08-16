<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AiService;
use Tests\Support\FakeAiService;
use Tests\TestCase;

/**
 * ai/assist has been ungated since v2.1. Gating it outright would lock every
 * editor out on deploy, so the gate ships behind a flag that defaults FALSE.
 */
class AssistGateTest extends TestCase
{
    private function body(): array
    {
        return ['action' => 'improve', 'text' => 'hello world'];
    }

    private function fakeProvider(): void
    {
        $this->app->instance(AiService::class, new FakeAiService(['<p>Hello, world.</p>'], true));
    }

    public function test_the_flag_defaults_off(): void
    {
        $this->assertFalse(config('myra.ai.gate_assist'));
    }

    public function test_with_the_flag_off_a_user_without_the_ability_still_reaches_assist(): void
    {
        config()->set('myra.ai.gate_assist', false);
        $this->fakeProvider();

        $user = $this->actingAsUser();
        $this->assertFalse($user->can('ai.use'));

        $this->post(route('ai.assist'), $this->body())->assertOk();
    }

    public function test_with_the_flag_on_the_same_user_is_refused(): void
    {
        config()->set('myra.ai.gate_assist', true);
        $this->fakeProvider();

        $this->actingAsUser();

        $this->post(route('ai.assist'), $this->body())->assertForbidden();
    }

    public function test_with_the_flag_on_an_editor_still_reaches_assist(): void
    {
        config()->set('myra.ai.gate_assist', true);
        $this->fakeProvider();

        $user = $this->actingAsRole('editor');
        $this->assertTrue($user->can('ai.use'), 'The seeder grants editors the back-compat ability.');

        $this->post(route('ai.assist'), $this->body())->assertOk();
    }

    public function test_managers_also_keep_assist_when_the_flag_flips(): void
    {
        config()->set('myra.ai.gate_assist', true);
        $this->fakeProvider();

        $this->actingAsRole('manager');

        $this->post(route('ai.assist'), $this->body())->assertOk();
    }
}
