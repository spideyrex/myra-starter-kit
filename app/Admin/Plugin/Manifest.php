<?php

namespace App\Admin\Plugin;

/**
 * Everything a plugin contributes, declared once. Fluent, immutable-ish, and
 * closure-free except for the two route callbacks, so it can be serialised for
 * `myra:about` and for tests.
 */
final class Manifest
{
    private ?string $minVersion = null;

    /** @var array<string,array<int,string>> */
    private array $permissions = [];

    /** @var array<string,class-string> */
    private array $reports = [];

    /** @var array<string,class-string> */
    private array $imports = [];

    /** @var callable[] */
    private array $routes = [];

    /** @var callable[] */
    private array $publicRoutes = [];

    /** @var array<class-string,class-string> */
    private array $policies = [];

    /** @var class-string[] */
    private array $commands = [];

    /** @var string[] */
    private array $migrations = [];

    /** @var array<int,array{path:string,namespace:string}> */
    private array $translations = [];

    /** @var array<int,array<string,mixed>> */
    private array $navItems = [];

    private function __construct(private readonly string $pluginId) {}

    public static function make(string $pluginId): self
    {
        return new self($pluginId);
    }

    // ---------------------------------------------------------------- declare

    /** Minimum Myra version. Below it the plugin fails to register. */
    public function requires(string $minVersion): self
    {
        $this->minVersion = $minVersion;

        return $this;
    }

    /** ['blog' => ['view','create']] — deep-merged into shield.modules. */
    public function permissions(array $modules): self
    {
        foreach ($modules as $module => $abilities) {
            $this->permissions[$module] = array_values(array_unique(array_merge(
                $this->permissions[$module] ?? [],
                array_values((array) $abilities),
            )));
        }

        return $this;
    }

    public function reports(array $map): self
    {
        $this->reports = array_merge($this->reports, $map);

        return $this;
    }

    public function imports(array $map): self
    {
        $this->imports = array_merge($this->imports, $map);

        return $this;
    }

    public function routes(callable $routes): self
    {
        $this->routes[] = $routes;

        return $this;
    }

    public function publicRoutes(callable $routes): self
    {
        $this->publicRoutes[] = $routes;

        return $this;
    }

    public function policies(array $map): self
    {
        $this->policies = array_merge($this->policies, $map);

        return $this;
    }

    public function commands(array $classes): self
    {
        $this->commands = array_values(array_unique(array_merge($this->commands, $classes)));

        return $this;
    }

    public function migrations(string $path): self
    {
        $this->migrations[] = $path;

        return $this;
    }

    public function translations(string $path, string $namespace): self
    {
        $this->translations[] = ['path' => $path, 'namespace' => $namespace];

        return $this;
    }

    /**
     * Sidebar contributions as PLAIN ARRAYS so this class has no compile-time
     * dependency on the navigation bundle. Shape:
     *   ['group','labelKey','href','icon','permission','sort','items']
     */
    public function navItems(array $items): self
    {
        foreach ($items as $item) {
            $this->navItems[] = (array) $item;
        }

        return $this;
    }

    // ----------------------------------------------------------------- read

    public function pluginId(): string
    {
        return $this->pluginId;
    }

    public function minVersion(): ?string
    {
        return $this->minVersion;
    }

    public function permissionModules(): array
    {
        return $this->permissions;
    }

    public function reportDefinitions(): array
    {
        return $this->reports;
    }

    public function importResources(): array
    {
        return $this->imports;
    }

    /** @return callable[] */
    public function routeCallbacks(): array
    {
        return $this->routes;
    }

    /** @return callable[] */
    public function publicRouteCallbacks(): array
    {
        return $this->publicRoutes;
    }

    public function policyMap(): array
    {
        return $this->policies;
    }

    public function commandClasses(): array
    {
        return $this->commands;
    }

    public function migrationPaths(): array
    {
        return $this->migrations;
    }

    /** @return array<int,array{path:string,namespace:string}> */
    public function translationPaths(): array
    {
        return $this->translations;
    }

    public function navItemArrays(): array
    {
        return $this->navItems;
    }

    /** Everything except closures — for myra:about, the demo page and tests. */
    public function toArray(): array
    {
        return [
            'id' => $this->pluginId,
            'requires' => $this->minVersion,
            'permissions' => $this->permissions,
            'reports' => $this->reports,
            'imports' => $this->imports,
            'policies' => $this->policies,
            'commands' => $this->commands,
            'migrations' => $this->migrations,
            'translations' => $this->translations,
            'nav' => $this->navItems,
            'routeGroups' => count($this->routes),
            'publicRouteGroups' => count($this->publicRoutes),
        ];
    }
}
