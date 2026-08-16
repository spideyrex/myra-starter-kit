<?php

namespace App\Admin\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/** Drives a row / bulk action through the REAL route, gate included. */
final class ActionProbe
{
    private array $payload = [];

    private ?TestResponse $last = null;

    private string $method = 'post';

    private function __construct(
        private readonly TestCase $test,
        private readonly string $routeName,
        private array $routeParams = [],
    ) {}

    public static function make(TestCase $test, string $routeName, array $params = []): self
    {
        return new self($test, $routeName, $params);
    }

    public function method(string $method): self
    {
        $this->method = strtolower($method);

        return $this;
    }

    public function row(Model $record): self
    {
        $this->routeParams = array_merge($this->routeParams, ['id' => $record->getKey()]);

        return $this;
    }

    public function bulk(iterable $records): self
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[] = $record instanceof Model ? $record->getKey() : $record;
        }

        $this->payload['ids'] = $ids;

        return $this;
    }

    public function withPayload(array $data): self
    {
        $this->payload = array_merge($this->payload, $data);

        return $this;
    }

    /** 403 for an actor without the ability, and NOT 403 for one that holds it. */
    public function assertRequiresPermission(string $ability): self
    {
        $original = Auth::user();

        MyraActors::withPermissions($this->test, []);
        $denied = $this->send();
        Assert::assertSame(403, $denied->getStatusCode(), "Route [{$this->routeName}] is reachable without [{$ability}].");

        MyraActors::withPermissions($this->test, [$ability]);
        $allowed = $this->send();
        Assert::assertNotSame(403, $allowed->getStatusCode(), "Route [{$this->routeName}] 403s even with [{$ability}].");
        Assert::assertTrue(
            $allowed->getStatusCode() < 400,
            "Route [{$this->routeName}] returned {$allowed->getStatusCode()} for a holder of [{$ability}].",
        );

        if ($original !== null) {
            $this->test->actingAs($original);
        }

        return $this;
    }

    public function assertForbidden(): self
    {
        $this->send()->assertForbidden();

        return $this;
    }

    public function assertFlashes(string $level, ?string $message = null): self
    {
        $response = $this->last ?? $this->send();
        $response->assertSessionHas($level);

        if ($message !== null) {
            Assert::assertSame($message, session($level), "Flash [{$level}].");
        }

        return $this;
    }

    public function call(): TestResponse
    {
        return $this->send();
    }

    private function send(): TestResponse
    {
        $url = route($this->routeName, $this->routeParams);

        return $this->last = match ($this->method) {
            'put' => $this->test->put($url, $this->payload),
            'patch' => $this->test->patch($url, $this->payload),
            'delete' => $this->test->delete($url, $this->payload),
            'get' => $this->test->get($url . (
                $this->payload === [] ? '' : '?' . http_build_query($this->payload)
            )),
            default => $this->test->post($url, $this->payload),
        };
    }
}
