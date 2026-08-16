<?php

namespace App\Admin\Testing;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Assertions about a schema the SERVER declared (a form or filter field list),
 * plus a real round trip through the route that validates it.
 *
 * Accepts a bare list of field descriptors or a `{fields: [...]}` wrapper, and
 * identifies a field by `key` or by `name`.
 */
final class SchemaProbe
{
    private array $state = [];

    private ?string $routeName = null;

    private array $routeParams = [];

    private ?TestResponse $last = null;

    private function __construct(
        private readonly TestCase $test,
        private readonly TestResponse $testResponse,
        private readonly string $prop,
    ) {}

    public static function make(TestCase $test, TestResponse $r, string $prop = 'fields'): self
    {
        return new self($test, $r, $prop);
    }

    /** The route `submit()` posts to. */
    public function to(string $routeName, array $params = []): self
    {
        $this->routeName = $routeName;
        $this->routeParams = $params;

        return $this;
    }

    public function assertFieldExists(string $key): self
    {
        Assert::assertNotNull($this->field($key), "Field [{$key}] is not in the schema.");

        return $this;
    }

    public function assertFieldDoesNotExist(string $key): self
    {
        Assert::assertNull($this->field($key), "Field [{$key}] leaked into the schema.");

        return $this;
    }

    public function assertFieldRequired(string $key): self
    {
        $field = $this->field($key);

        Assert::assertNotNull($field, "Field [{$key}] is not in the schema.");
        Assert::assertTrue(
            (bool) ($field['required'] ?? false),
            "Field [{$key}] is not declared required.",
        );

        return $this;
    }

    public function assertFieldHidden(string $key): self
    {
        $field = $this->field($key);

        Assert::assertNotNull($field, "Field [{$key}] is not in the schema.");
        Assert::assertTrue((bool) ($field['hidden'] ?? false), "Field [{$key}] is not hidden.");

        return $this;
    }

    public function assertState(array $values): self
    {
        foreach ($values as $key => $value) {
            Assert::assertArrayHasKey($key, $this->state, "No pending value for [{$key}].");
            Assert::assertSame($value, $this->state[$key], "Pending value for [{$key}].");
        }

        return $this;
    }

    public function fill(array $data): self
    {
        $this->state = array_merge($this->state, $data);

        return $this;
    }

    public function submit(string $method = 'post'): TestResponse
    {
        Assert::assertNotNull($this->routeName, 'SchemaProbe::submit() needs a target — call to($routeName) first.');

        $url = route($this->routeName, $this->routeParams);

        return $this->last = match (strtolower($method)) {
            'put' => $this->test->put($url, $this->state),
            'patch' => $this->test->patch($url, $this->state),
            'delete' => $this->test->delete($url, $this->state),
            default => $this->test->post($url, $this->state),
        };
    }

    public function assertHasErrors(array $keys): self
    {
        Assert::assertNotNull($this->last, 'Call submit() before asserting on its errors.');
        $this->last->assertSessionHasErrors($keys);

        return $this;
    }

    public function assertHasNoErrors(): self
    {
        Assert::assertNotNull($this->last, 'Call submit() before asserting on its errors.');
        $this->last->assertSessionHasNoErrors();

        return $this;
    }

    /** @return array<string,mixed>|null */
    private function field(string $key): ?array
    {
        foreach ($this->fields() as $field) {
            if (($field['key'] ?? $field['name'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    /** @return array<int,array<string,mixed>> */
    private function fields(): array
    {
        $value = InertiaProps::normalise(InertiaProps::get($this->testResponse, $this->prop));

        Assert::assertIsArray($value, "Inertia prop [{$this->prop}] is not a schema array.");

        if (isset($value['fields']) && is_array($value['fields'])) {
            $value = $value['fields'];
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
