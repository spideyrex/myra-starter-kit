<?php

namespace App\Admin\QueryBuilder;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The server-side whitelist. A controller-side literal, never derived from the
 * request — the client's constraint declaration carries no authority.
 */
final class FieldSet
{
    /** @var array<string,FieldSpec> */
    private array $fields = [];

    private int $maxRules = 25;

    private int $maxDepth = 3;

    /** @param  FieldSpec[]  $fields */
    private function __construct(array $fields)
    {
        foreach ($fields as $field) {
            $this->fields[$field->name] = $field;
        }
    }

    /** @param  FieldSpec[]  $fields */
    public static function make(array $fields): self
    {
        return new self($fields);
    }

    public function maxRules(int $n = 25): self
    {
        $this->maxRules = max(1, $n);

        return $this;
    }

    public function maxDepth(int $n = 3): self
    {
        $this->maxDepth = max(1, $n);

        return $this;
    }

    public function maxRuleCount(): int
    {
        return $this->maxRules;
    }

    public function maxDepthAllowed(): int
    {
        return $this->maxDepth;
    }

    public function field(string $name): ?FieldSpec
    {
        return $this->fields[$name] ?? null;
    }

    /** Drops every field the actor may not see. */
    public function visibleTo(?Authenticatable $user): self
    {
        $clone = clone $this;
        $clone->fields = array_filter($this->fields, static fn (FieldSpec $f) => $f->visibleTo($user));

        return $clone;
    }

    /** @return array<string,mixed> */
    public function toClientSchema(?Authenticatable $user): array
    {
        return [
            'maxRules' => $this->maxRules,
            'maxDepth' => $this->maxDepth,
            'fields' => array_values(array_map(
                static fn (FieldSpec $f) => $f->toClientSchema(),
                $this->visibleTo($user)->fields,
            )),
        ];
    }
}
