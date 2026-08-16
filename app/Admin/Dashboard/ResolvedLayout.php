<?php

namespace App\Admin\Dashboard;

/** The outcome of the resolution chain. `payload` is RAW — not yet viewer-filtered. */
final readonly class ResolvedLayout
{
    private function __construct(
        public ?array $payload,
        public string $source,
        public ?string $role,
        public bool $hasRoleDefault,
    ) {}

    public static function personal(array $payload, ?string $role, bool $hasRoleDefault): self
    {
        return new self($payload, 'personal', $role, $hasRoleDefault);
    }

    public static function role(array $payload, string $roleName): self
    {
        return new self($payload, 'role', $roleName, true);
    }

    public static function none(?string $role = null, bool $hasRoleDefault = false): self
    {
        return new self(null, 'none', $role, $hasRoleDefault);
    }

    /** ALWAYS an array, never null — the client writes no null checks for it. */
    public function toInertia(): array
    {
        return [
            'source' => $this->source,
            'role' => $this->role,
            'hasRoleDefault' => $this->hasRoleDefault,
        ];
    }
}
