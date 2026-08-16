<?php

namespace App\Admin\Examples;

/**
 * One shadcn example in the reference catalogue.
 *
 * Mirrors App\Admin\Demo\DemoEntry: every user-facing string is an i18n KEY,
 * never copy, and toClientSchema() is free of Laravel calls so the payload is a
 * pure function of the declaration.
 */
final class ExampleEntry
{
    private ?string $titleKey = null;

    private ?string $descriptionKey = null;

    private string $shell = '';

    private string $origin = 'fetched';

    private ?string $sourceRef = null;

    /** @var array<int,array{path:string,sha256:string}> */
    private array $files = [];

    /** @var string[] */
    private array $dataFiles = [];

    /** @var string[] */
    private array $surfaces = [];

    /** @var string[] */
    private array $registryDependencies = [];

    /** @var string[] */
    private array $npmDependencies = [];

    private bool $available = true;

    private ?string $unavailableReason = null;

    /** @var string[] */
    private array $tags = [];

    private string $since = '2.6.0';

    private ?string $permission = 'examples.view';

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

    /** Relative to resources/js/examples, e.g. 'mail/Page.vue'. */
    public function shell(string $relative): self
    {
        $this->shell = $relative;

        return $this;
    }

    public function origin(string $o): self
    {
        $this->origin = $o === 'authored' ? 'authored' : 'fetched';

        return $this;
    }

    public function sourceRef(?string $sha): self
    {
        $this->sourceRef = $sha;

        return $this;
    }

    /** @param array<int,array{path:string,sha256:string}> $files */
    public function files(array $files): self
    {
        $this->files = array_values($files);

        return $this;
    }

    /** @param string[] $paths */
    public function dataFiles(array $paths): self
    {
        $this->dataFiles = array_values(array_filter($paths, 'is_string'));

        return $this;
    }

    /** @param string[] $keys */
    public function surfaces(array $keys): self
    {
        $this->surfaces = array_values(array_filter($keys, 'is_string'));

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

    public function key(): string
    {
        return $this->key;
    }

    public function shellValue(): string
    {
        return $this->shell;
    }

    public function originValue(): string
    {
        return $this->origin;
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

    /** @return array<int,array{path:string,sha256:string}> */
    public function fileList(): array
    {
        return $this->files;
    }

    public function resolvedTitleKey(): string
    {
        return $this->titleKey ?? "examples.entries.{$this->key}.title";
    }

    public function resolvedDescriptionKey(): string
    {
        return $this->descriptionKey ?? "examples.entries.{$this->key}.description";
    }

    /**
     * @return array{key:string,titleKey:string,descriptionKey:string,shell:string,
     *               origin:string,sourceRef:?string,available:bool,unavailableReason:?string,
     *               registryDependencies:array<int,string>,npmDependencies:array<int,string>,
     *               dataFiles:array<int,string>,surfaces:array<int,string>,
     *               tags:array<int,string>,since:string,fileCount:int}
     */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'titleKey' => $this->resolvedTitleKey(),
            'descriptionKey' => $this->resolvedDescriptionKey(),
            'shell' => $this->shell,
            'origin' => $this->origin,
            'sourceRef' => $this->sourceRef,
            'available' => $this->available,
            'unavailableReason' => $this->unavailableReason,
            'registryDependencies' => $this->registryDependencies,
            'npmDependencies' => $this->npmDependencies,
            'dataFiles' => $this->dataFiles,
            'surfaces' => $this->surfaces,
            'tags' => $this->tags,
            'since' => $this->since,
            'fileCount' => count($this->files),
        ];
    }
}
