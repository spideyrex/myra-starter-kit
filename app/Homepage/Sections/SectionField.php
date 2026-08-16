<?php

namespace App\Homepage\Sections;

use App\Support\UrlGuard;

/**
 * One authored field on a section type.
 *
 * coerce() is TOTAL: it never throws, for any input, so a malformed stored
 * block can never take the public homepage down.
 */
final class SectionField
{
    /** Exactly the lucide names FeatureGrid.vue already imports — widening this widens the public bundle. */
    public const ICON_ALLOWLIST = [
        'Zap', 'Shield', 'BarChart3', 'Users', 'Lock', 'Globe', 'Rocket', 'Heart', 'Star',
        'Code', 'Database', 'Cloud', 'Settings', 'Mail', 'Bell', 'Search', 'Layers', 'Layout',
    ];

    private const MAX_DEFAULTS = [
        'text' => 500,
        'textarea' => 5000,
        'html' => 200000,
        'url' => 2048,
        'image' => 1024,
        'list' => 20,
    ];

    private ?string $labelKey = null;

    private bool $required = false;

    private mixed $default = null;

    private ?int $max = null;

    /** @var array<int,string> */
    private array $options = [];

    /** @var array<int,SectionField> */
    private array $of = [];

    private function __construct(private readonly string $name, private readonly string $type) {}

    public static function text(string $name): self
    {
        return new self($name, 'text');
    }

    public static function textarea(string $name): self
    {
        return new self($name, 'textarea');
    }

    public static function html(string $name): self
    {
        return new self($name, 'html');
    }

    public static function url(string $name): self
    {
        return new self($name, 'url');
    }

    public static function image(string $name): self
    {
        return new self($name, 'image');
    }

    public static function bool(string $name): self
    {
        return new self($name, 'bool');
    }

    public static function number(string $name): self
    {
        return new self($name, 'number');
    }

    public static function icon(string $name): self
    {
        return new self($name, 'icon');
    }

    /** @param array<int,mixed> $options the FIRST option is the default — reka-ui rejects an empty value */
    public static function select(string $name, array $options): self
    {
        $field = new self($name, 'select');

        $field->options = array_values(array_filter(
            $options,
            fn ($o) => is_string($o) && $o !== '',
        ));

        return $field;
    }

    public static function list(string $name, SectionField ...$of): self
    {
        $field = new self($name, 'list');
        $field->of = array_values($of);

        return $field;
    }

    public function labelKey(string $key): self
    {
        $this->labelKey = $key;

        return $this;
    }

    public function required(): self
    {
        $this->required = true;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    /** String length, or row count for a list. */
    public function max(int $max): self
    {
        $this->max = max(1, $max);

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function typeValue(): string
    {
        return $this->type;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /** @return array<int,SectionField> */
    public function subFields(): array
    {
        return $this->of;
    }

    /** @return array<int,string> */
    public function optionValues(): array
    {
        return $this->options;
    }

    public function maxValue(): int
    {
        return $this->max ?? self::MAX_DEFAULTS[$this->type] ?? 500;
    }

    public function defaultValue(): mixed
    {
        if ($this->default !== null) {
            return $this->default;
        }

        return match ($this->type) {
            'bool' => false,
            'number' => 0,
            'list' => [],
            'select' => $this->options[0] ?? '',
            default => '',
        };
    }

    public function coerce(mixed $value): mixed
    {
        return match ($this->type) {
            'text', 'textarea', 'html' => is_string($value) ? mb_substr($value, 0, $this->maxValue()) : '',
            'url' => is_string($value) ? UrlGuard::safe($value, '') : '',
            'image' => $this->coerceImage($value),
            'bool' => $this->coerceBool($value),
            'number' => $this->coerceNumber($value),
            'select' => in_array($value, $this->options, true) ? $value : $this->defaultValue(),
            'icon' => is_string($value) && in_array($value, self::ICON_ALLOWLIST, true) ? $value : '',
            'list' => $this->coerceList($value),
            default => $this->defaultValue(),
        };
    }

    /** A storage-relative path only: no scheme, no traversal, no absolute root. */
    private function coerceImage(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $path = ltrim(str_replace('\\', '/', trim($value)), '/');

        if ($path === '' || str_contains($path, '..') || UrlGuard::hasScheme($path)) {
            return '';
        }

        return mb_substr($path, 0, $this->maxValue());
    }

    private function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value) || is_object($value) || is_resource($value) || $value === null) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function coerceNumber(mixed $value): int|float
    {
        $fallback = $this->defaultValue();
        $fallback = is_int($fallback) || is_float($fallback) ? $fallback : 0;

        if (is_bool($value) || is_array($value) || is_object($value) || ! is_numeric($value)) {
            return $fallback;
        }

        $float = (float) $value;

        if (! is_finite($float)) {
            return $fallback;
        }

        return is_int($value) || (float) (int) $float === $float ? (int) $float : $float;
    }

    /** @return array<int,array<string,mixed>> */
    private function coerceList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (count($rows) >= $this->maxValue()) {
                break;
            }

            if (! is_array($row)) {
                continue;
            }

            $out = [];

            foreach ($this->of as $sub) {
                $out[$sub->name()] = array_key_exists($sub->name(), $row)
                    ? $sub->coerce($row[$sub->name()])
                    : $sub->defaultValue();
            }

            $rows[] = $out;
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    public function toClientSchema(string $labelPrefix = ''): array
    {
        $schema = [
            'name' => $this->name,
            'type' => $this->type,
            'labelKey' => $this->labelKey ?? ($labelPrefix === '' ? $this->name : $labelPrefix.'.'.$this->name),
            'required' => $this->required,
            'default' => $this->defaultValue(),
            'max' => $this->maxValue(),
        ];

        if ($this->type === 'select') {
            $schema['options'] = $this->options;
        }

        if ($this->type === 'list') {
            $prefix = $labelPrefix === '' ? $this->name : $labelPrefix.'.'.$this->name;
            $schema['of'] = array_map(fn (SectionField $f) => $f->toClientSchema($prefix), $this->of);
        }

        return $schema;
    }
}
