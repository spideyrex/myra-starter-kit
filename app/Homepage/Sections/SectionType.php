<?php

namespace App\Homepage\Sections;

use App\Support\HtmlSanitizer;

/**
 * One authorable page section.
 *
 * Declared server-side so a package can contribute a section the same way it
 * contributes a template. toClientSchema() is free of Laravel calls, exactly
 * like HomepageTemplate::toClientSchema().
 */
final class SectionType
{
    public const GROUPS = ['content', 'proof', 'conversion', 'layout'];

    private ?string $labelKey = null;

    private ?string $descriptionKey = null;

    private string $icon = 'Layout';

    private string $group = 'content';

    private ?string $titleField = null;

    private int $maxPerPage = 0;

    /** @var array<int,SectionField> */
    private array $fields = [];

    /** @var array<string,array<int,string>|string> */
    private array $variants = [];

    private string $since = '2.7.0';

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function labelKey(string $key): self
    {
        $this->labelKey = $key;

        return $this;
    }

    public function descriptionKey(string $key): self
    {
        $this->descriptionKey = $key;

        return $this;
    }

    public function icon(string $lucideName): self
    {
        $this->icon = $lucideName;

        return $this;
    }

    public function group(string $group): self
    {
        $this->group = in_array($group, self::GROUPS, true) ? $group : 'content';

        return $this;
    }

    public function titleField(string $fieldName): self
    {
        $this->titleField = $fieldName;

        return $this;
    }

    /** 0 = unlimited. Advisory: the editor disables the entry, the write path never rejects. */
    public function maxPerPage(int $n): self
    {
        $this->maxPerPage = max(0, $n);

        return $this;
    }

    /** A FLAT list. Any list field's sub-fields are flat too. */
    public function fields(SectionField ...$fields): self
    {
        $this->fields = array_values($fields);

        return $this;
    }

    /** @param array<string,array<int,string>|string> $spec ['align' => ['center','left'], 'compact' => 'bool'] */
    public function variants(array $spec): self
    {
        $this->variants = $spec;

        return $this;
    }

    public function since(string $v): self
    {
        $this->since = $v;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function iconValue(): string
    {
        return $this->icon;
    }

    public function groupValue(): string
    {
        return $this->group;
    }

    public function titleFieldValue(): ?string
    {
        return $this->titleField;
    }

    public function maxPerPageValue(): int
    {
        return $this->maxPerPage;
    }

    /** @return array<int,SectionField> */
    public function fieldList(): array
    {
        return $this->fields;
    }

    public function field(string $name): ?SectionField
    {
        foreach ($this->fields as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }

    /** @return array<int,string> */
    public function imageFields(): array
    {
        return array_values(array_map(
            fn (SectionField $f) => $f->name(),
            array_filter($this->fields, fn (SectionField $f) => $f->typeValue() === 'image'),
        ));
    }

    public function resolvedLabelKey(): string
    {
        return $this->labelKey ?? "pageBuilder.sections.{$this->key}.label";
    }

    public function resolvedDescriptionKey(): string
    {
        return $this->descriptionKey ?? "pageBuilder.sections.{$this->key}.description";
    }

    public function fieldLabelPrefix(): string
    {
        return "pageBuilder.sections.{$this->key}.fields";
    }

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        $out = [];

        foreach ($this->fields as $field) {
            $out[$field->name()] = $field->defaultValue();
        }

        return $out;
    }

    /**
     * Every declared field, coerced. Undeclared keys are dropped. Never throws.
     *
     * @return array<string,mixed>
     */
    public function normalize(mixed $data): array
    {
        $data = is_array($data) ? $data : [];
        $out = [];

        foreach ($this->fields as $field) {
            $out[$field->name()] = array_key_exists($field->name(), $data)
                ? $field->coerce($data[$field->name()])
                : $field->defaultValue();
        }

        return $out;
    }

    /**
     * Only the keys the author actually set survive — an absent key must stay
     * absent so the template's own variant keeps winning.
     *
     * @return array<string,mixed>
     */
    public function normalizeVariant(mixed $variant): array
    {
        if (! is_array($variant)) {
            return [];
        }

        $out = [];

        foreach ($variant as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $this->variants)) {
                continue;
            }

            $spec = $this->variants[$key];

            if ($spec === 'bool') {
                $out[$key] = is_bool($value)
                    ? $value
                    : (filter_var(is_scalar($value) ? $value : false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false);

                continue;
            }

            $options = is_array($spec) ? array_values(array_filter($spec, 'is_string')) : [];

            if ($options === []) {
                continue;
            }

            $out[$key] = in_array($value, $options, true) ? $value : $options[0];
        }

        return $out;
    }

    /**
     * The write-path pass: normalise, then sanitise every html field.
     *
     * @return array<string,mixed>
     */
    public function sanitize(array $data): array
    {
        $out = $this->normalize($data);

        foreach ($this->fields as $field) {
            if ($field->typeValue() === 'html') {
                $out[$field->name()] = HtmlSanitizer::clean((string) $out[$field->name()]);

                continue;
            }

            if ($field->typeValue() !== 'list') {
                continue;
            }

            $rows = is_array($out[$field->name()]) ? $out[$field->name()] : [];

            foreach ($rows as $i => $row) {
                foreach ($field->subFields() as $sub) {
                    if ($sub->typeValue() === 'html') {
                        $rows[$i][$sub->name()] = HtmlSanitizer::clean((string) ($row[$sub->name()] ?? ''));
                    }
                }
            }

            $out[$field->name()] = $rows;
        }

        return $out;
    }

    /**
     * Validation rules for one row, addressed exactly the way the editor expects.
     *
     * @return array<string,array<int,string>>
     */
    public function rules(int $i): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $key = "blocks.{$i}.data.{$field->name()}";

            $rules[$key] = match ($field->typeValue()) {
                'bool' => ['sometimes', 'boolean'],
                'number' => ['sometimes', 'numeric'],
                'list' => ['sometimes', 'array', 'max:'.$field->maxValue()],
                default => ['sometimes', 'string', 'max:'.$field->maxValue()],
            };

            if ($field->isRequired()) {
                $rules[$key][0] = 'required';
            }

            foreach ($field->subFields() as $sub) {
                $subKey = "blocks.{$i}.data.{$field->name()}.*.{$sub->name()}";

                $rules[$subKey] = match ($sub->typeValue()) {
                    'bool' => ['sometimes', 'boolean'],
                    'number' => ['sometimes', 'numeric'],
                    default => ['sometimes', 'string', 'max:'.$sub->maxValue()],
                };
            }
        }

        return $rules;
    }

    /** @return array<string,mixed> */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'labelKey' => $this->resolvedLabelKey(),
            'descriptionKey' => $this->resolvedDescriptionKey(),
            'icon' => $this->icon,
            'group' => $this->group,
            'titleField' => $this->titleField,
            'maxPerPage' => $this->maxPerPage,
            'variants' => $this->variants,
            'fields' => array_map(
                fn (SectionField $f) => $f->toClientSchema($this->fieldLabelPrefix()),
                $this->fields,
            ),
            'since' => $this->since,
        ];
    }
}
