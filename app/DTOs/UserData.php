<?php

namespace App\DTOs;

readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
        public ?string $phone = null,
        public ?string $avatar = null,
        public string $status = 'active',
        public ?string $role = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'] ?? null,
            phone: $validated['phone'] ?? null,
            avatar: $validated['avatar'] ?? null,
            status: $validated['status'] ?? 'active',
            role: $validated['role'] ?? null,
        );
    }
}
