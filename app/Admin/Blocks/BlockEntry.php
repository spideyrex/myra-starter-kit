<?php

namespace App\Admin\Blocks;

/**
 * One vendored shadcn block in the reference catalogue.
 *
 * Mirrors App\Admin\Demo\DemoEntry: every user-facing string is an i18n KEY and
 * `toClientSchema()` is free of Laravel calls, so the committed fixture is a
 * pure function of the declaration and cannot drift with the container.
 */
final class BlockEntry
{
    private ?string $titleKey = null;

    private ?string $descriptionKey = null;

    private string $category = 'other';

    private string $entryFile = '';

    /** @var array<int,array{path:string,sha256:string}> */
    private array $files = [];

    /** @var string[] */
    private array $registryDependencies = [];

    /** @var string[] */
    private array $npmDependencies = [];

    private bool $available = true;

    private ?string $unavailableReason = null;

    /** @var string[] */
    private array $tags = [];

    private string $since = '2.6.0';

    private ?string $permission = 'blocks.view';

    private string $viewport = 'full';

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function titleKey(string $k): self
    {
        $this->titleKey = $k;

        return $this;
    }

    public function descriptionKey(string $k): self
    {
        $this->descriptionKey = $k;

        return $this;
    }

    public function category(string $slug): self
    {
        $this->category = $slug;

        return $this;
    }

    public function entryFile(string $relative): self
    {
        $this->entryFile = $relative;

        return $this;
    }

    /** @param array<int,array{path:string,sha256:string}> $files */
    public function files(array $files): self
    {
        $this->files = array_values($files);

        return $this;
    }

    /** @param string[] $deps */
    public function registryDependencies(array $deps): self
    {
        $this->registryDependencies = array_values(array_filter($deps, 'is_string'));

        return $this;
    }

    /** @param string[] $deps */
    public function npmDependencies(array $deps): self
    {
        $this->npmDependencies = array_values(array_filter($deps, 'is_string'));

        return $this;
    }

    public function available(bool $v, ?string $reason = null): self
    {
        $this->available = $v;
        $this->unavailableReason = $v ? null : $reason;

        return $this;
    }

    /** @param string[] $tags */
    public function tags(array $tags): self
    {
        $this->tags = array_values(array_filter($tags, 'is_string'));

        return $this;
    }

    public function since(string $v): self
    {
        $this->since = $v;

        return $this;
    }

    public function permission(?string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    public function viewport(string $default = 'full'): self
    {
        $this->viewport = $default;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function categorySlug(): string
    {
        return $this->category;
    }

    public function entryFileValue(): string
    {
        return $this->entryFile;
    }

    /** @return array<int,array{path:string,sha256:string}> */
    public function fileList(): array
    {
        return $this->files;
    }

    public function permissionAbility(): ?string
    {
        return $this->permission;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function unavailableReasonValue(): ?string
    {
        return $this->unavailableReason;
    }

    public function resolvedTitleKey(): string
    {
        return $this->titleKey ?? "blocks.entries.{$this->key}.title";
    }

    public function resolvedDescriptionKey(): string
    {
        return $this->descriptionKey ?? "blocks.entries.{$this->key}.description";
    }

    /**
     * @return array{key:string,titleKey:string,descriptionKey:string,category:string,
     *               entryFile:string,available:bool,unavailableReason:?string,
     *               registryDependencies:array<int,string>,npmDependencies:array<int,string>,
     *               tags:array<int,string>,since:string,viewport:string}
     */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'titleKey' => $this->resolvedTitleKey(),
            'descriptionKey' => $this->resolvedDescriptionKey(),
            'category' => $this->category,
            'entryFile' => $this->entryFile,
            'available' => $this->available,
            'unavailableReason' => $this->unavailableReason,
            'registryDependencies' => $this->registryDependencies,
            'npmDependencies' => $this->npmDependencies,
            'tags' => $this->tags,
            'since' => $this->since,
            'viewport' => $this->viewport,
        ];
    }
}
