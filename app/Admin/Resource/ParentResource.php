<?php

namespace App\Admin\Resource;

/** Declares the parent half of a nested resource: /admin/{parent}/{id}/{children}. */
final class ParentResource
{
    private string $modelClass = '';

    private string $relationship = '';

    private string $inverse = '';

    private string $foreignKey = '';

    private string $permission = '';

    private string $titleColumn = 'name';

    private function __construct(private readonly string $routePrefix) {}

    /** e.g. 'courses', or 'learning.courses' inside a cluster. */
    public static function make(string $routePrefix): self
    {
        return new self($routePrefix);
    }

    public function model(string $class): self
    {
        $this->modelClass = $class;

        return $this;
    }

    public function relationship(string $hasMany): self
    {
        $this->relationship = $hasMany;

        return $this;
    }

    public function inverse(string $belongsTo): self
    {
        $this->inverse = $belongsTo;

        return $this;
    }

    public function foreignKey(string $column): self
    {
        $this->foreignKey = $column;

        return $this;
    }

    public function permission(string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    public function titleAttribute(string $column = 'name'): self
    {
        $this->titleColumn = $column;

        return $this;
    }

    public function routePrefix(): string
    {
        return $this->routePrefix;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function modelClass(): string
    {
        return $this->modelClass;
    }

    public function relationshipName(): string
    {
        return $this->relationship;
    }

    public function inverseName(): string
    {
        return $this->inverse;
    }

    public function foreignKeyColumn(): string
    {
        return $this->foreignKey;
    }

    public function permissionAbility(): string
    {
        return $this->permission;
    }

    public function titleColumn(): string
    {
        return $this->titleColumn;
    }
}
