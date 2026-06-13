<?php

namespace App\Console\Commands\Myra;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeUserCommand extends Command
{
    protected $signature = 'make:myra-user
        {--admin : Assign the super-admin role}
        {--name= : User name (skips the prompt)}
        {--email= : User email (skips the prompt)}';

    protected $description = 'Create an active, verified user account interactively (optionally a super-admin)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name', 'Admin');
        $email = $this->option('email') ?: $this->ask('Email');

        if (! $email) {
            $this->components->error('Email is required.');
            return self::FAILURE;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->components->error("A user with email {$email} already exists.");
            return self::FAILURE;
        }

        $password = $this->secret('Password') ?: \Illuminate\Support\Str::random(16);

        $role = $this->option('admin')
            ? 'super-admin'
            : $this->choice('Role', Role::orderBy('name')->pluck('name')->all(), 'user');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        if (Role::where('name', $role)->exists()) {
            $user->assignRole($role);
        }

        $this->components->info("User created: {$email} ({$role})");

        return self::SUCCESS;
    }
}
