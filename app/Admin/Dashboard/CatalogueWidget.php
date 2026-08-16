<?php

namespace App\Admin\Dashboard;

use App\Admin\Report\ReportRequest;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * A browsable widget a user may add to their own dashboard.
 *
 * The fluent setters mirror FieldSpec; the `*Names()` getters are what
 * WidgetInstance re-resolves against on every page load. There is deliberately
 * no widening method: chartTypes() only ever narrows.
 */
final class CatalogueWidget
{
    private const KEY = '/^[a-z0-9][a-z0-9._:-]{0,63}$/i';

    private ?string $titleKey = null;

    private ?string $descriptionKey = null;

    private string $categoryKey = 'dashboardLayout.categories.general';

    private string $icon = 'LayoutGrid';

    private ?string $permission = null;

    private ?string $report = null;

    /** @var array<int,string> */
    private array $dimensions = [];

    /** @var array<int,string> */
    private array $measures = [];

    /** @var array<int,string> */
    private array $chartTypes = [];

    /** @var array<string,mixed> */
    private array $defaults = [];

    private int|array $defaultColSpan = 1;

    private int $maxInstances = 3;

    /** @var array<int,string> */
    private array $tags = [];

    private function __construct(
        private readonly string $key,
        private readonly string $type,
    ) {
        if (preg_match(self::KEY, $key) !== 1) {
            throw new InvalidArgumentException("Invalid catalogue widget key [{$key}].");
        }
    }

    public static function stat(string $key): self
    {
        return new self($key, 'stat');
    }

    public static function chart(string $key): self
    {
        return new self($key, 'chart');
    }

    public static function table(string $key): self
    {
        return new self($key, 'table');
    }

    public function titleKey(string $k): self
    {
        $this->titleKey = $k;

        return $this;
    }

    public function descriptionKey(string $k): self
    {
        $this->descriptionKey = $k;

        return $this;
    }

    public function categoryKey(string $k): self
    {
        $this->categoryKey = $k;

        return $this;
    }

    public function icon(string $lucideName): self
    {
        $this->icon = $lucideName;

        return $this;
    }

    public function permission(string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    public function report(string $reportKey): self
    {
        $this->report = $reportKey;

        return $this;
    }

    /** @param  array<int,string>  $names */
    public function dimensions(array $names): self
    {
        $this->dimensions = self::strings($names);

        return $this;
    }

    /** @param  array<int,string>  $names */
    public function measures(array $names): self
    {
        $this->measures = self::strings($names);

        return $this;
    }

    /**
     * NARROWS ONLY. Intersected against ReportRequest::CHART_TYPES exactly as
     * FieldSpec::operators() intersects against Operator::forType().
     *
     * @param  array<int,string>  $types
     */
    public function chartTypes(array $types): self
    {
        $wanted = self::strings($types);

        $this->chartTypes = array_values(array_filter(
            ReportRequest::CHART_TYPES,
            static fn (string $t) => in_array($t, $wanted, true),
        ));

        return $this;
    }

    /** @param  array<string,mixed>  $binding */
    public function defaults(array $binding): self
    {
        $this->defaults = $binding;

        return $this;
    }

    public function defaultColSpan(int|array $span): self
    {
        $this->defaultColSpan = $span;

        return $this;
    }

    public function maxInstances(int $n = 3): self
    {
        $this->maxInstances = max(1, min($n, LayoutShape::MAX_INSTANCES));

        return $this;
    }

    /** @param  array<int,string>  $tags */
    public function tags(array $tags): self
    {
        $this->tags = self::strings($tags);

        return $this;
    }

    // --- getters -------------------------------------------------------------

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function reportKey(): ?string
    {
        return $this->report;
    }

    public function permissionAbility(): ?string
    {
        return $this->permission;
    }

    /** @return array<int,string> */
    public function dimensionNames(): array
    {
        return $this->dimensions;
    }

    /** @return array<int,string> */
    public function measureNames(): array
    {
        return $this->measures;
    }

    /** @return array<int,string> */
    public function chartTypeNames(): array
    {
        return $this->chartTypes;
    }

    /** @return array<string,mixed> */
    public function defaultBinding(): array
    {
        return $this->defaults;
    }

    public function defaultSpan(): int|array
    {
        return $this->defaultColSpan;
    }

    public function instanceCap(): int
    {
        return $this->maxInstances;
    }

    public function visibleTo(?Authenticatable $user): bool
    {
        if ($this->permission === null) {
            return true;
        }

        return $user instanceof Authorizable && $user->can($this->permission);
    }

    /** NEVER exposes a SQL identifier, a table name, or the report's FieldSet columns. */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'titleKey' => $this->titleKey ?? 'dashboardLayout.untitled',
            'descriptionKey' => $this->descriptionKey,
            'categoryKey' => $this->categoryKey,
            'icon' => $this->icon,
            'dimensions' => $this->dimensions,
            'measures' => $this->measures,
            'chartTypes' => $this->chartTypes,
            'defaults' => (object) $this->defaults,
            'defaultColSpan' => $this->defaultColSpan,
            'maxInstances' => $this->maxInstances,
            'tags' => $this->tags,
        ];
    }

    /** @return array<int,string> */
    private static function strings(array $values): array
    {
        return array_values(array_unique(array_filter(
            $values,
            static fn ($v) => is_string($v) && $v !== '',
        )));
    }
}
