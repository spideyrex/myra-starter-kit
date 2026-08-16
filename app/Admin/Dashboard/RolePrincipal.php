<?php

namespace App\Admin\Dashboard;

use App\Models\Role;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Answers abilities from the ROLE's permission set alone — deliberately never
 * touching the Gate.
 *
 * Gate::before returns true for a super-admin on EVERY ability, so a super-admin
 * authoring the viewer dashboard through their own Gate would see a catalogue no
 * viewer will ever see and ship tiles that evaporate at render: a preview that
 * lies. This fails CLOSED (unknown ability → false), so it can only ever be more
 * restrictive than reality. Authoring/preview only — render uses the real user.
 */
final class RolePrincipal implements Authenticatable, Authorizable
{
    public function __construct(public readonly Role $role) {}

    public function can($abilities, $arguments = []): bool
    {
        foreach ($this->abilities($abilities) as $ability) {
            if (! $this->grants($ability)) {
                return false;
            }
        }

        return $this->abilities($abilities) !== [];
    }

    public function canAny($abilities, $arguments = []): bool
    {
        foreach ($this->abilities($abilities) as $ability) {
            if ($this->grants($ability)) {
                return true;
            }
        }

        return false;
    }

    public function cant($abilities, $arguments = []): bool
    {
        return ! $this->can($abilities, $arguments);
    }

    public function cannot($abilities, $arguments = []): bool
    {
        return ! $this->can($abilities, $arguments);
    }

    /** @return array<int,string> */
    private function abilities(mixed $abilities): array
    {
        $list = is_array($abilities) ? $abilities : [$abilities];

        return array_values(array_filter(
            array_map(static fn ($a) => is_string($a) ? $a : null, $list),
            static fn ($a) => $a !== null && $a !== '',
        ));
    }

    private function grants(string $ability): bool
    {
        try {
            return $this->role->hasPermissionTo($ability);
        } catch (\Throwable) {
            return false;
        }
    }

    // Authenticatable — never authenticated, only interrogated.

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return null;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
