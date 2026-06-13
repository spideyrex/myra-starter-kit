<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePolicyCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-policy {name : Model name in PascalCase (e.g. Product)}';

    protected $description = 'Generate a model policy whose abilities map to the Shield {prefix}.view/create/edit/delete permissions (Laravel auto-discovers it)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $repl = $this->replacements($name);
        $prefix = $repl['{{ permissionPrefix }}'];

        $created = $this->writeRaw("app/Policies/{$name}Policy.php", $this->policy($name, $prefix));

        if ($created) {
            // Make sure the abilities exist so the policy resolves to real permissions.
            $this->syncPermissions($prefix, ['view', 'create', 'edit', 'delete']);
            $this->newLine();
            $this->components->info("Policy created → app/Policies/{$name}Policy.php");
            $this->components->bulletList([
                "Abilities map to: {$prefix}.view / .create / .edit / .delete",
                "Laravel auto-discovers App\\Policies\\{$name}Policy for App\\Models\\{$name}.",
                "Use it via \$user->can('update', \$model) or @can in views.",
            ]);
        }

        return self::SUCCESS;
    }

    private function policy(string $name, string $prefix): string
    {
        return <<<PHP
<?php

namespace App\\Policies;

use App\\Models\\{$name};
use App\\Models\\User;

class {$name}Policy
{
    public function viewAny(User \$user): bool
    {
        return \$user->can('{$prefix}.view');
    }

    public function view(User \$user, {$name} \$model): bool
    {
        return \$user->can('{$prefix}.view');
    }

    public function create(User \$user): bool
    {
        return \$user->can('{$prefix}.create');
    }

    public function update(User \$user, {$name} \$model): bool
    {
        return \$user->can('{$prefix}.edit');
    }

    public function delete(User \$user, {$name} \$model): bool
    {
        return \$user->can('{$prefix}.delete');
    }

    public function restore(User \$user, {$name} \$model): bool
    {
        return \$user->can('{$prefix}.edit');
    }

    public function forceDelete(User \$user, {$name} \$model): bool
    {
        return \$user->can('{$prefix}.delete');
    }
}

PHP;
    }
}
