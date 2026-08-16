<?php

namespace App\Admin\Resource;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * One record, two routes. No create, no destroy, no {record} parameter — the
 * shape settings-style pages actually have.
 */
final class SingularResource
{
    private string $modelClass = '';

    private string $viewAbility = '';

    private string $editAbility = '';

    private string $page = '';

    private array $rules = [];

    private ?Closure $resolver = null;

    private array $creationAttributes = [];

    private array $relationships = [];

    private ?Closure $afterSave = null;

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function model(string $class): self
    {
        $this->modelClass = $class;

        return $this;
    }

    /** {prefix}.view for reading, {prefix}.edit for writing. */
    public function permission(string $prefix): self
    {
        $this->viewAbility = "{$prefix}.view";
        $this->editAbility = "{$prefix}.edit";

        return $this;
    }

    /**
     * Explicit abilities, for modules whose RBAC does not follow the
     * {prefix}.view / {prefix}.edit pair.
     */
    public function abilities(string $view, string $edit): self
    {
        $this->viewAbility = $view;
        $this->editAbility = $edit;

        return $this;
    }

    public function page(string $inertiaComponent): self
    {
        $this->page = $inertiaComponent;

        return $this;
    }

    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function resolveUsing(Closure $fn): self
    {
        $this->resolver = $fn;

        return $this;
    }

    /** Stamped on the first create only. */
    public function creationAttributes(array $attrs): self
    {
        $this->creationAttributes = $attrs;

        return $this;
    }

    public function relationships(array $names): self
    {
        $this->relationships = $names;

        return $this;
    }

    public function afterSave(Closure $fn): self
    {
        $this->afterSave = $fn;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function modelClass(): string
    {
        return $this->modelClass;
    }

    public function viewAbility(): string
    {
        return $this->viewAbility;
    }

    public function editAbility(): string
    {
        return $this->editAbility;
    }

    public function pageComponent(): string
    {
        return $this->page;
    }

    public function validationRules(): array
    {
        return $this->rules;
    }

    public function creationAttributeValues(): array
    {
        return $this->creationAttributes;
    }

    /** @return string[] */
    public function relationshipNames(): array
    {
        return $this->relationships;
    }

    public function afterSaveCallback(): ?Closure
    {
        return $this->afterSave;
    }

    public function resolve(): ?Model
    {
        if ($this->resolver !== null) {
            return ($this->resolver)();
        }

        $class = $this->modelClass;

        return $class::query()->first();
    }
}
